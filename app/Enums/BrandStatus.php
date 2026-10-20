<?php

namespace App\Enums;

enum BrandStatus: string
{
    case Approved = 'approved';
    case Pending = 'pending';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::Pending => 'Pending Review',
            self::Rejected => 'Rejected',
        };
    }
}
