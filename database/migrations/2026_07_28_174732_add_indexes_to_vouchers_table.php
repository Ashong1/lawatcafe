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
        Schema::table('vouchers', function (Blueprint $table) {
            // (is_used, ip_address) covers the captive-portal lookup — "is this IP
            // already an active session?" — run on nearly every guest page load.
            $table->index(['is_used', 'ip_address'], 'vouchers_is_used_ip_address_index');
            $table->index('mac_address', 'vouchers_mac_address_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('vouchers_is_used_ip_address_index');
            $table->dropIndex('vouchers_mac_address_index');
        });
    }
};
