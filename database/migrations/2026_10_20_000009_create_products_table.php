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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vertical_id')->constrained('verticals')->onDelete('restrict');
            $table->foreignId('brand_id')->constrained('brands')->onDelete('restrict');
            $table->string('title', 255);
            $table->string('slug', 255);
            $table->text('description');
            $table->json('highlights')->nullable();
            $table->json('specs')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('seller_profiles')->nullOnDelete();
            $table->string('qc_status', 20)->default('draft');
            $table->text('qc_notes')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('pending_changes')->nullable();
            $table->timestamps();

            $table->index(['vertical_id', 'qc_status']);
            $table->index(['brand_id', 'qc_status']);
            $table->index('qc_status');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
