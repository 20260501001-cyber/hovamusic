<?php

namespace App\Enums;

enum ReportImportStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Preview = 'preview';
    case Failed = 'failed';
    case Approved = 'approved';
    case Reversed = 'reversed';

    public function label(): string
    {
        return __('finance.import_statuses.'.$this->value);
    }
}
