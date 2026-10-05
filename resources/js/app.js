import './uploads';

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-cookie-settings]')) {
        const banner = document.querySelector('[data-cookie-banner]');
        banner?.removeAttribute('hidden');
        banner?.querySelector('details')?.setAttribute('open', '');
        banner?.querySelector('input:not([disabled]), button')?.focus();
    }

    const opener = event.target.closest('[data-drawer-open]');
    const closer = event.target.closest('[data-drawer-close]');

    if (opener) {
        const drawer = document.getElementById(opener.dataset.drawerOpen);
        drawer?.classList.add('is-open');
        drawer?.querySelector('a, button')?.focus();
    }

    if (closer) {
        closer.closest('.hm-drawer')?.classList.remove('is-open');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('.hm-drawer.is-open').forEach((drawer) => drawer.classList.remove('is-open'));
    }
});

document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();

        return;
    }

    const button = event.target.querySelector('button[type="submit"][data-loading-text]');

    if (button && !button.disabled) {
        button.setAttribute('aria-busy', 'true');
        button.classList.add('is-loading');
        button.querySelector('span:last-child')?.replaceChildren(button.dataset.loadingText);
    }
});

// Para çekme formu: tahmini Wise ücreti ve gönderilecek tutar (yalnızca gösterim;
// asıl hesap sunucuda yapılır). Kuruş cinsinden tam sayıyla hesaplanır.
const toCents = (value) => {
    const text = String(value ?? '').trim().replace(/\s|\$/g, '');
    const normalized = text.includes(',') ? text.replace(/\./g, '').replace(',', '.') : text;

    if (!/^\d+(\.\d{1,2})?$/.test(normalized)) {
        return null;
    }

    const [whole, fraction = ''] = normalized.split('.');

    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
};

const formatUsd = (cents) => {
    const sign = cents < 0 ? '−' : '';
    const abs = Math.abs(cents);
    const whole = String(Math.floor(abs / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${sign}$${whole},${String(abs % 100).padStart(2, '0')}`;
};

document.addEventListener('input', (event) => {
    const input = event.target.closest('[data-fee-input]');
    const form = input?.closest('[data-fee-estimate]');

    if (!form) {
        return;
    }

    const amount = toCents(input.value);
    const fixed = toCents(form.dataset.feeFixed) ?? 0;
    const pct = Number(form.dataset.feePct || 0);
    const feeOutput = form.querySelector('[data-fee-output]');
    const netOutput = form.querySelector('[data-net-output]');

    if (amount === null || amount === 0) {
        feeOutput.textContent = '—';
        netOutput.textContent = '—';

        return;
    }

    const fee = fixed + Math.round((amount * pct) / 100);
    feeOutput.textContent = formatUsd(fee);
    netOutput.textContent = formatUsd(Math.max(amount - fee, 0));
});
