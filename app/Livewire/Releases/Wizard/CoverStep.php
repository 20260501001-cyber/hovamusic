<?php

namespace App\Livewire\Releases\Wizard;

use App\Domain\Media\CoverProcessor;
use App\Domain\Media\CoverRejected;
use App\Domain\Media\MediaUrl;
use App\Domain\Releases\ReleaseValidator;
use App\Models\MediaFile;
use App\Models\Release;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class CoverStep extends WizardStep
{
    use WithFileUploads;

    public const STEP = ReleaseValidator::STEP_COVER;

    /**
     * @var TemporaryUploadedFile|null
     */
    public $upload = null;

    /**
     * @var list<string>
     */
    public array $coverErrors = [];

    public string $rejectedName = '';

    public function mount(Release $release): void
    {
        $this->useRelease($release);
    }

    public function updatedUpload(): void
    {
        $this->touch('cover');
        $this->coverErrors = [];
        $this->rejectedName = '';
        $file = $this->upload;
        $this->upload = null;

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            $media = app(CoverProcessor::class)->store(auth()->user(), $file->getRealPath(), $file->getClientOriginalName());
        } catch (CoverRejected $rejected) {
            $this->coverErrors = $rejected->errors;
            $this->rejectedName = $file->getClientOriginalName();

            return;
        } finally {
            $file->delete();
        }

        $this->replaceCover($media);
    }

    public function removeCover(): void
    {
        $this->replaceCover(null);
        $this->coverErrors = [];
    }

    private function replaceCover(?MediaFile $media): void
    {
        $release = $this->release();
        $previous = $release->cover;

        $release->forceFill(['cover_media_id' => $media?->id])->save();

        if ($previous && ! Release::withTrashed()->where('cover_media_id', $previous->id)->exists()) {
            $previous->deleteWithFile();
        }

        $this->markSaved();
    }

    public function render(): View
    {
        $uploadError = $this->getErrorBag()->first('upload');

        if ($uploadError) {
            $this->coverErrors = [$uploadError];
        }

        $this->forgetRelease();
        $release = $this->release()->load('cover');
        $issues = app(ReleaseValidator::class)->step($release, self::STEP);
        $summary = $this->exposeErrors($issues);
        $settings = app(Settings::class);
        $cover = $release->cover;

        return view('livewire.releases.wizard.cover-step', [
            'release' => $release,
            'summary' => $summary,
            'cover' => $cover,
            'coverUrl' => $cover ? MediaUrl::temporary($cover) : null,
            'coverMeta' => $cover ? __('release.cover.valid_meta', [
                'format' => $cover->format === 'png' ? 'PNG' : 'JPG',
                'size' => Format::bytes($cover->size),
            ]) : null,
            'maxBytes' => $settings->coverMaxBytes(),
            'maxText' => Format::bytes($settings->coverMaxBytes()),
            'messages' => [
                'format' => __('media.cover.format'),
                'unreadable' => __('media.cover.unreadable'),
                'too_large' => __('media.cover.too_large'),
                'dimensions' => __('media.cover.dimensions'),
                'not_square' => __('media.cover.not_square'),
                'failed' => __('media.upload.failed'),
            ],
        ]);
    }
}
