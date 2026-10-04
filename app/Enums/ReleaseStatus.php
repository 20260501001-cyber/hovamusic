<?php

namespace App\Enums;

enum ReleaseStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case NeedsChanges = 'needs_changes';
    case Approved = 'approved';
    case Delivered = 'delivered';
    case Live = 'live';
    case Rejected = 'rejected';
    case TakedownRequested = 'takedown_requested';
    case TakenDown = 'taken_down';

    public function label(): string
    {
        return __('release.statuses.'.$this->value);
    }

    /**
     * Kullanıcının yayını düzenleyebildiği durumlar. İncelemedeki yayın da
     * kilitlidir; kullanıcı yalnızca taslağı ve düzeltme istenen yayını değiştirir.
     */
    public function isEditableByOwner(): bool
    {
        return in_array($this, [self::Draft, self::NeedsChanges], true);
    }

    public function isSubmittable(): bool
    {
        return $this->isEditableByOwner();
    }
}
