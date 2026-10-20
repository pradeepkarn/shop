<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->onDelete('cascade');
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->string('sku', 100);
            $table->unsignedInteger('mrp'); // In paise
            $table->unsignedInteger('selling_price'); // In paise, <= mrp
            $table->unsignedInteger('stock')->default(0);
            $table->string('status', 20)->default('draft');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['seller_profile_id', 'sku']);
            $table->unique(['seller_profile_id', 'product_variant_id']);
            $table->index(['seller_profile_id', 'status']);
            $table->index(['product_variant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
