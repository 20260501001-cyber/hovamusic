<?php

namespace App\Domain\Users;

use App\Models\Admin;
use App\Models\ImpersonationLog;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Admin'in kullanıcı panelini o kullanıcı gibi görüntülemesi. Yalnızca görüntüleme:
 * değişiklik yapan istekler BlockWhileImpersonating ile engellenir. Başlangıç sebep
 * ve IP ile, bitiş zamanıyla impersonation_logs tablosuna; ikisi de audit log'a yazılır.
 * Admin oturumu açık kalır; kullanıcı oturumu aynı tarayıcı oturumunda web guard'ıyla açılır.
 */
class Impersonation
{
    public const SESSION_KEY = 'impersonation';

    public function __construct(private readonly AuditLogger $audit) {}

    public function start(Admin $admin, User $user, string $reason, Request $request): ImpersonationLog
    {
        Gate::forUser($admin)->authorize('impersonate', $user);

        if ($this->active($request)) {
            $this->end($request);
        }

        $log = ImpersonationLog::create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'reason' => Str::limit(trim($reason), 500, ''),
            'ip_address' => $request->ip(),
            'started_at' => now(),
        ]);

        $this->session($request)?->put(self::SESSION_KEY, [
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'log_id' => $log->id,
        ]);

        Auth::guard('web')->login($user);

        $this->audit->record('user.impersonation_started', $user, ['reason' => $log->reason, 'log_id' => $log->id], $admin);

        return $log;
    }

    public function end(Request $request): ?ImpersonationLog
    {
        $data = $this->session($request)?->pull(self::SESSION_KEY);

        if (! is_array($data)) {
            return null;
        }

        $log = ImpersonationLog::query()->find($data['log_id'] ?? null);

        if ($log !== null && $log->ended_at === null) {
            $log->forceFill(['ended_at' => now()])->save();
        }

        // logoutCurrentDevice, kullanıcının "beni hatırla" anahtarını değiştirmez;
        // böylece kullanıcının kendi cihazlarındaki oturumları etkilenmez.
        if (Auth::guard('web')->id() === ($data['user_id'] ?? null)) {
            Auth::guard('web')->logoutCurrentDevice();
        }

        $admin = Admin::query()->find($data['admin_id'] ?? null);

        if ($log !== null) {
            $this->audit->record('user.impersonation_ended', $log->user, ['log_id' => $log->id], $admin);
        }

        return $log;
    }

    /**
     * Oturumda görüntüleme kaydı var mı. Admin oturumu kapanmışsa ya da kullanıcı
     * değişmişse kayıt geçersizdir.
     */
    public function active(?Request $request = null): bool
    {
        $data = $this->session($request)?->get(self::SESSION_KEY);

        return is_array($data)
            && Auth::guard('admin')->id() === ($data['admin_id'] ?? null)
            && Auth::guard('web')->id() === ($data['user_id'] ?? null);
    }

    public function pending(?Request $request = null): bool
    {
        return $this->session($request)?->has(self::SESSION_KEY) ?? false;
    }

    public function admin(?Request $request = null): ?Admin
    {
        return $this->active($request) ? Auth::guard('admin')->user() : null;
    }

    /**
     * İsteğin oturumu; istek dışında (komut, test) uygulamanın oturum deposu.
     */
    private function session(?Request $request): ?Session
    {
        $request ??= request();

        if ($request->hasSession()) {
            return $request->session();
        }

        return app()->bound('session.store') ? app('session.store') : null;
    }
}
