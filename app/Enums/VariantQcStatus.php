<?php

namespace App\Enums;

enum VariantQcStatus: string
{
    case Draft = 'draft';
    case QcPending = 'qc_pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::QcPending => 'QC Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
