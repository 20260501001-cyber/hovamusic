<?php

namespace App\Enums;

enum TemplateType: string
{
    case NeedsChanges = 'needs_changes';
    case Rejection = 'rejection';

    public function label(): string
    {
        return __('release.template_types.'.$this->value);
    }
}
