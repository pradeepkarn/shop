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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('upid', 20)->unique();
            $table->string('variant_key', 255);
            $table->json('variant_attributes');
            $table->string('barcode', 50)->nullable()->unique();
            $table->decimal('weight', 8, 2)->nullable();
            $table->json('dimensions')->nullable();
            $table->string('qc_status', 20)->default('draft');
            $table->text('qc_notes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'variant_key']);
            $table->index(['product_id', 'qc_status']);
            $table->index('qc_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
