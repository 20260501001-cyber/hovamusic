<?php

namespace Database\Seeders;

use App\Domain\Billing\PlanHistoryRecorder;
use App\Enums\AdminRole;
use App\Enums\SubscriptionStatus;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Yalnızca local ortamda çalışır ve tekrar çalıştırılabilir. Şifreler: "demo-sifre-2026".
 * Demo planlar Polar'a bağlı değildir (satın alınamaz); demo kullanıcılara elle
 * açılmış bir yıllık abonelik verilir ki yayın gönderme akışı denenebilsin.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $artist = User::query()->where('email', 'sanatci@demo.hovamusic.test')->first()
            ?? User::factory()->create([
                'name' => 'Demo Sanatçı',
                'email' => 'sanatci@demo.hovamusic.test',
                'password' => 'demo-sifre-2026',
            ]);

        $label = User::query()->where('email', 'label@demo.hovamusic.test')->first()
            ?? User::factory()->label()->create([
                'name' => 'Demo Plak Şirketi',
                'email' => 'label@demo.hovamusic.test',
                'password' => 'demo-sifre-2026',
            ]);

        $admin = Admin::query()->firstOrCreate(
            ['email' => 'admin@demo.hovamusic.test'],
            ['name' => 'Demo Admin', 'password' => 'demo-sifre-2026', 'is_active' => true],
        );
        $admin->syncRoles([AdminRole::SuperAdmin->value]);

        $artistPlan = Plan::query()->firstOrCreate(['name' => 'Demo Sanatçı Planı'], [
            'audience' => 'artist',
            'interval' => 'year',
            'price_usd' => '0.00',
            'release_limit' => 10,
            'artist_limit' => 2,
            'revenue_share_pct' => '85.00',
            'description' => 'Yalnızca geliştirme ortamı için.',
            'is_active' => false,
        ]);

        $labelPlan = Plan::query()->firstOrCreate(['name' => 'Demo Plak Şirketi Planı'], [
            'audience' => 'label',
            'interval' => 'year',
            'price_usd' => '0.00',
            'release_limit' => null,
            'artist_limit' => 20,
            'revenue_share_pct' => '85.00',
            'description' => 'Yalnızca geliştirme ortamı için.',
            'is_active' => false,
        ]);

        foreach ([[$artist, $artistPlan], [$label, $labelPlan]] as [$user, $plan]) {
            if ($user->activeSubscription() !== null) {
                continue;
            }

            $subscription = new Subscription([
                'plan_id' => $plan->id,
                'provider' => 'manual',
                'status' => SubscriptionStatus::Active,
                'started_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
            ]);
            $subscription->user()->associate($user);
            $subscription->save();

            app(PlanHistoryRecorder::class)->sync($user);
        }

        $this->call([FaqSeeder::class, DemoContentSeeder::class]);
    }
}
