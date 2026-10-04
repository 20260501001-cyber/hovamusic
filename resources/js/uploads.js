// Kapak ve ses yükleme bileşenleri (Alpine). Kurallar sunucuda da kontrol edilir;
// buradaki ön kontrol, kullanıcının büyük bir dosyayı boşuna göndermesini önler.

const numberFormat = (fraction) => new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 0, maximumFractionDigits: fraction });

export function formatBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${numberFormat(unit === 0 ? 0 : 1).format(value)} ${units[unit]}`;
}

function t(template, params = {}) {
    return Object.entries(params).reduce((text, [key, value]) => text.replaceAll(`:${key}`, value), template);
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function imageSize(file) {
    if ('createImageBitmap' in window) {
        const bitmap = await createImageBitmap(file);
        const size = { width: bitmap.width, height: bitmap.height };
        bitmap.close();

        return size;
    }

    const url = URL.createObjectURL(file);

    try {
        const image = new Image();
        image.src = url;
        await image.decode();

        return { width: image.naturalWidth, height: image.naturalHeight };
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function readHeader(file, length) {
    return new Uint8Array(await file.slice(0, length).arrayBuffer());
}

async function fingerprint(file) {
    const source = `${file.name}:${file.size}:${file.lastModified}`;

    try {
        const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(source));

        return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
    } catch {
        return source.slice(0, 64);
    }
}

class UploadError extends Error {
    constructor(message, fatal = true) {
        super(message);
        this.fatal = fatal;
    }
}

export function coverPicker({ maxBytes, size, messages }) {
    return {
        state: 'idle',
        progress: 0,
        fileName: '',
        errors: [],
        dragging: false,

        async pick(file) {
            if (!file) {
                return;
            }

            this.fileName = file.name;
            this.errors = [];
            const errors = [];
            const isImage = ['image/jpeg', 'image/png'].includes(file.type);

            if (!isImage) {
                return this.fail([messages.format]);
            }

            if (file.size > maxBytes) {
                errors.push(t(messages.too_large, { size: formatBytes(file.size), max: formatBytes(maxBytes) }));
            }

            try {
                const { width, height } = await imageSize(file);

                if (width !== size || height !== size) {
                    errors.push(t(width === height ? messages.dimensions : messages.not_square, { width, height }));
                }
            } catch {
                errors.push(messages.unreadable);
            }

            if (errors.length) {
                return this.fail(errors);
            }

            this.state = 'uploading';
            this.progress = 0;
            this.$wire.upload(
                'upload',
                file,
                () => { this.state = 'idle'; },
                () => this.fail([messages.failed]),
                (event) => { this.progress = event.detail.progress; },
            );
        },

        fail(errors) {
            this.errors = errors;
            this.state = 'error';
        },
    };
}

export function audioUploader({ startUrl, track, maxBytes, messages }) {
    return {
        state: 'idle',
        progress: 0,
        sent: 0,
        total: 0,
        fileName: '',
        error: '',
        dragging: false,
        session: null,
        xhr: null,
        cancelled: false,

        get sentText() {
            return t(messages.uploading_meta, { sent: formatBytes(this.sent), total: formatBytes(this.total) });
        },

        async pick(file) {
            if (!file || this.busy()) {
                return;
            }

            this.fileName = file.name;
            this.error = '';
            this.total = file.size;
            this.sent = 0;
            this.progress = 0;
            this.cancelled = false;

            const dot = file.name.lastIndexOf('.');
            const extension = dot > 0 ? file.name.slice(dot + 1).toLowerCase() : '';

            if (!['wav', 'flac'].includes(extension)) {
                return this.fail(t(messages.extension, { ext: extension ? `.${extension}` : messages.no_extension }));
            }

            if (file.size === 0) {
                return this.fail(messages.empty);
            }

            if (file.size > maxBytes) {
                return this.fail(t(messages.too_large, { size: formatBytes(file.size), max: formatBytes(maxBytes) }));
            }

            const header = await readHeader(file, 12);
            const text = (from, to) => String.fromCharCode(...header.slice(from, to));
            const isWav = text(0, 4) === 'RIFF' && text(8, 12) === 'WAVE';
            const isFlac = text(0, 4) === 'fLaC';

            if (!isWav && !isFlac) {
                return this.fail(messages.not_audio);
            }

            this.state = 'uploading';

            try {
                this.session = await this.json('POST', startUrl, {
                    track,
                    name: file.name,
                    size: file.size,
                    fingerprint: await fingerprint(file),
                });
                await this.send(file);

                if (this.cancelled) {
                    return;
                }

                this.state = 'processing';
                this.progress = 100;
                await this.$wire.audioUploaded(track);
                this.state = 'idle';
            } catch (error) {
                if (!this.cancelled) {
                    this.fail(error instanceof UploadError ? error.message : messages.failed);
                }
            }
        },

        busy() {
            return ['uploading', 'retrying', 'processing'].includes(this.state);
        },

        async send(file) {
            let offset = this.session.offset;
            let attempt = 0;

            while (offset < file.size) {
                if (this.cancelled) {
                    return;
                }

                const end = Math.min(offset + this.session.chunk_size, file.size);

                try {
                    const result = await this.chunk(file.slice(offset, end), offset);
                    attempt = 0;
                    this.state = 'uploading';
                    offset = result.offset;
                    this.sent = offset;
                    this.progress = Math.floor((offset * 100) / file.size);

                    if (result.complete) {
                        return;
                    }
                } catch (error) {
                    if (error.fatal || this.cancelled) {
                        throw error;
                    }

                    attempt++;

                    if (attempt > 6) {
                        throw new UploadError(messages.failed);
                    }

                    this.state = 'retrying';
                    await sleep(Math.min(30000, 1000 * 2 ** (attempt - 1)));

                    try {
                        offset = (await this.json('GET', this.session.url)).offset;
                    } catch {
                        // Sunucuya hâlâ ulaşılamıyor; bir sonraki denemede yeniden sorulur.
                    }
                }
            }
        },

        chunk(blob, offset) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                this.xhr = xhr;
                xhr.open('PATCH', this.session.url);
                xhr.setRequestHeader('Content-Type', 'application/octet-stream');
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('Upload-Offset', String(offset));
                xhr.upload.onprogress = (event) => {
                    this.sent = offset + event.loaded;
                    this.progress = Math.min(99, Math.floor((this.sent * 100) / this.total));
                };
                xhr.onload = () => {
                    let body = {};

                    try {
                        body = JSON.parse(xhr.responseText || '{}');
                    } catch {
                        body = {};
                    }

                    if (xhr.status === 200) {
                        resolve(body);
                    } else if (xhr.status === 409) {
                        resolve({ offset: body.offset ?? offset, complete: false });
                    } else if (xhr.status === 422 || xhr.status === 403 || xhr.status === 404 || xhr.status === 419) {
                        reject(new UploadError(body.message || messages.failed));
                    } else {
                        reject(new UploadError(messages.failed, false));
                    }
                };
                xhr.onerror = () => reject(new UploadError(messages.failed, false));
                xhr.onabort = () => reject(new UploadError(messages.failed, true));
                xhr.send(blob);
            });
        },

        async json(method, url, body = null) {
            const response = await fetch(url, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body ? JSON.stringify(body) : null,
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new UploadError(data.message || messages.failed, response.status < 500 && response.status !== 429);
            }

            return data;
        },

        cancel() {
            this.cancelled = true;
            this.xhr?.abort();

            if (this.session) {
                this.json('DELETE', this.session.url).catch(() => {});
            }

            this.session = null;
            this.state = 'idle';
        },

        fail(message) {
            this.error = message;
            this.state = 'error';
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('hmCoverPicker', coverPicker);
    window.Alpine.data('hmAudioUploader', audioUploader);
});
