<?php

use App\Enums\SellerStatus;
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
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('store_name');
            $table->string('store_slug')->unique();
            $table->string('business_type');
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('support_email');
            $table->string('support_phone');
            $table->string('pickup_address_line1');
            $table->string('pickup_address_line2')->nullable();
            $table->string('pickup_city');
            $table->string('pickup_state');
            $table->string('pickup_postal_code');
            $table->string('pickup_country')->default('India');
            $table->text('pan');
            $table->text('gstin')->nullable();
            $table->string('bank_account_holder');
            $table->text('bank_account_number');
            $table->string('ifsc');
            $table->string('status')->default(SellerStatus::Draft->value);
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('bank_updated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_profiles');
    }
};
