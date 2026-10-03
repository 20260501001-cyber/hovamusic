<?php

namespace App\Enums;

enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case ReviewEditor = 'review_editor';
    case Finance = 'finance';

    public function label(): string
    {
        return __('admin.roles.'.$this->value);
    }
}
