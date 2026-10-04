<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Users\Impersonation;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Panel içi bildirim merkezi. Ödeme, para çekme ve plan bildirimleri de sonraki
 * fazlarda aynı tabloyu (notifications) kullanır.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('panel.notifications.index', [
            'notifications' => $user->notifications()->paginate(20),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Bildirimi okundu yapar ve ilgili sayfaya götürür. Admin görüntüleme modundaysa
     * okundu bilgisi değişmez.
     */
    public function open(Request $request, string $notification, Impersonation $impersonation): RedirectResponse
    {
        $item = $this->find($request, $notification);

        if (! $impersonation->active($request)) {
            $item->markAsRead();
        }

        $url = (string) ($item->data['url'] ?? '');

        return $this->isInternal($request, $url)
            ? redirect()->to($url)
            : redirect()->route('panel.notifications.index');
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $this->find($request, $notification)->markAsRead();

        return back()->with('flash', __('notifications.center.marked'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('flash', __('notifications.center.marked'));
    }

    private function find(Request $request, string $id): DatabaseNotification
    {
        $item = $request->user()->notifications()->whereKey($id)->first();
        abort_if($item === null, 404);

        return $item;
    }

    private function isInternal(Request $request, string $url): bool
    {
        return $url !== '' && parse_url($url, PHP_URL_HOST) === $request->getHost();
    }
}
