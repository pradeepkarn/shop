<?php

namespace Database\Factories;

use App\Models\SellerDocument;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerDocument>
 */
class SellerDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_profile_id' => SellerProfile::factory(),
            'type' => 'id_proof',
            'path' => 'seller-documents/fake-doc.pdf',
            'original_name' => 'id_proof.pdf',
        ];
    }
}
