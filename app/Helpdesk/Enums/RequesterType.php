<?php

namespace App\Helpdesk\Enums;

enum RequesterType: string
{
    case Customer = 'customer';

    /** Phase 2. */
    case Seller = 'seller';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
