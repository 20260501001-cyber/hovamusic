<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\WebhookEvent;

class WebhookEventPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $this->allowed($actor);
    }

    public function view(Admin $actor, WebhookEvent $webhookEvent): bool
    {
        return $this->allowed($actor);
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, WebhookEvent $webhookEvent): bool
    {
        return $this->allowed($actor);
    }

    public function delete(Admin $actor, WebhookEvent $webhookEvent): bool
    {
        return false;
    }

    public function deleteAny(Admin $actor): bool
    {
        return false;
    }

    private function allowed(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }
}
