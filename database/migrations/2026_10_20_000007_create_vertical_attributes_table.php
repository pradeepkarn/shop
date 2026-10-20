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
        Schema::create('vertical_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vertical_id')->constrained('verticals')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('code', 50);
            $table->string('type', 20);
            $table->json('options')->nullable();
            $table->string('unit', 20)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_variant')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['vertical_id', 'code']);
            $table->index(['vertical_id', 'sort_order']);
            $table->index(['vertical_id', 'is_variant']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vertical_attributes');
    }
};
