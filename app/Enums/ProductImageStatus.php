<?php

namespace App\Enums;

enum ProductImageStatus: string
{
    case Live = 'live';
    case PendingAdd = 'pending_add';
    case PendingRemove = 'pending_remove';

    public function label(): string
    {
        return match ($this) {
            self::Live => 'Live',
            self::PendingAdd => 'Pending Add',
            self::PendingRemove => 'Pending Remove',
        };
    }
}
