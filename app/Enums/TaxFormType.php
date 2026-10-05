<?php

namespace App\Enums;

enum TaxFormType: string
{
    case W8Ben = 'w8ben';
    case W8BenE = 'w8bene';

    public function label(): string
    {
        return __('finance.tax_forms.types.'.$this->value);
    }
}
