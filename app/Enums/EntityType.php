<?php

namespace App\Enums;

enum EntityType: string
{
    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return __('finance.entity_types.'.$this->value);
    }

    public function taxForm(): TaxFormType
    {
        return $this === self::Company ? TaxFormType::W8BenE : TaxFormType::W8Ben;
    }
}
