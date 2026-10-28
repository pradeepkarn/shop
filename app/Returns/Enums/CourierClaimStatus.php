<?php

namespace App\Returns\Enums;

enum CourierClaimStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Settled = 'settled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted to Courier',
            self::Approved => 'Approved by Courier',
            self::Rejected => 'Claim Rejected',
            self::Settled => 'Settled & Reimbursed',
        };
    }
}
