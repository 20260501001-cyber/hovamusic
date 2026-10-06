<?php

namespace App\Http\Controllers\Site;

use App\Domain\Legal\LegalDocuments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceived;
use App\Support\Seo\SeoFactory;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function show(SeoFactory $seo, Settings $settings, LegalDocuments $documents): View
    {
        return view('site.contact', [
            'seo' => $seo->page('contact')->breadcrumb(__('site.nav.contact'), route('contact')),
            'email' => $this->recipient($settings),
            'privacyUrl' => $documents->urlFor('kvkk-aydinlatma'),
            'topics' => collect(ContactMessage::TOPICS)->mapWithKeys(fn (string $topic): array => [$topic => __('site.contact.topics.'.$topic)])->all(),
        ]);
    }

    public function store(StoreContactMessageRequest $request, Settings $settings): RedirectResponse
    {
        $message = ContactMessage::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        $recipient = $this->recipient($settings);

        if ($recipient !== null) {
            Notification::route('mail', $recipient)->notify(new ContactMessageReceived($message));
        }

        return redirect()->route('contact')->with('flash', __('site.contact.sent'));
    }

    private function recipient(Settings $settings): ?string
    {
        $email = trim((string) $settings->get('contact_email')) ?: (string) config('mail.from.address');

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
