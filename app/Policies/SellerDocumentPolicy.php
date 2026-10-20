<?php

namespace App\Policies;

use App\Models\SellerDocument;
use App\Models\User;

class SellerDocumentPolicy
{
    /**
     * Determine whether the user can view the seller document.
     */
    public function view(User $user, SellerDocument $document): bool
    {
        return (int) $user->id === (int) $document->sellerProfile->user_id;
    }
}
