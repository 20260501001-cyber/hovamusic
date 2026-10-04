<?php

namespace App\Domain\Plans;

use App\Models\User;

/**
 * Plan ve limit kontrollerinin tek giriş noktası. Planlar Faz 4'te gelecek; o zamana
 * kadar geliştirme ve test ortamında her işlem geçer, üretimde (hova.plans.enforce)
 * gönderim ve sanatçı ekleme kapalıdır. Faz 4'te abonelik, yayın limiti ve sanatçı
 * limiti burada bağlanacak.
 */
class PlanGate
{
    public function canSubmitRelease(User $user): GateResult
    {
        return $this->enforced()
            ? GateResult::deny(__('plans.not_ready'))
            : GateResult::allow();
    }

    public function canAddArtist(User $user): GateResult
    {
        return $this->enforced()
            ? GateResult::deny(__('plans.not_ready'))
            : GateResult::allow();
    }

    private function enforced(): bool
    {
        return (bool) config('hova.plans.enforce');
    }
}
