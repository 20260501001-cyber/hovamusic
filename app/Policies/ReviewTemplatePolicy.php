<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ReviewTemplate;

class ReviewTemplatePolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isReviewer();
    }

    public function view(Admin $actor, ReviewTemplate $template): bool
    {
        return $actor->isReviewer();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isReviewer();
    }

    public function update(Admin $actor, ReviewTemplate $template): bool
    {
        return $actor->isReviewer();
    }

    public function delete(Admin $actor, ReviewTemplate $template): bool
    {
        return $actor->isReviewer();
    }

    public function deleteAny(Admin $actor): bool
    {
        return $actor->isReviewer();
    }
}
