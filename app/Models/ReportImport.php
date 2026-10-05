<?php

namespace App\Models;

use App\Enums\ReportImportStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'original_name', 'file_path', 'file_sha256', 'report_mapping_id', 'currency', 'periods', 'status', 'row_count',
    'matched_count', 'unmatched_count', 'totals', 'warnings', 'error',
])]
class ReportImport extends Model
{
    use HasPublicUlid;

    protected function casts(): array
    {
        return [
            'status' => ReportImportStatus::class,
            'periods' => 'array',
            'totals' => 'array',
            'warnings' => 'array',
            'approved_at' => 'datetime',
            'reversed_at' => 'datetime',
            'row_count' => 'integer',
            'matched_count' => 'integer',
            'unmatched_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ReportMapping, $this>
     */
    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ReportMapping::class, 'report_mapping_id');
    }

    /**
     * @return HasMany<ReportLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ReportLine::class);
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function isApprovable(): bool
    {
        return $this->status === ReportImportStatus::Preview && empty($this->warnings['blocking'] ?? []);
    }
}
