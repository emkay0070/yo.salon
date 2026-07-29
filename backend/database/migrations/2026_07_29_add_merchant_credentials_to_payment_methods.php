<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // Merchant identification
            $table->string('merchant_id')->nullable()->after('account_identifier');
            
            // API credentials (encrypted)
            $table->text('api_key')->nullable()->after('merchant_id');
            $table->text('api_secret')->nullable()->after('api_key');
            $table->string('api_subscription_key')->nullable()->after('api_secret');
            
            // Provider-specific configuration
            $table->string('environment')->default('sandbox')->after('api_subscription_key'); // sandbox or production
            $table->timestamp('credentials_verified_at')->nullable()->after('environment');
            
            // Index for quick lookup
            $table->index(['salon_id', 'provider', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex(['salon_id', 'provider', 'is_active']);
            $table->dropColumn([
                'merchant_id',
                'api_key',
                'api_secret',
                'api_subscription_key',
                'environment',
                'credentials_verified_at',
            ]);
        });
    }
};
