<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that the guest pressed Disconnect themselves.
 *
 * The portal's recovery path re-authorizes any voucher with time remaining so
 * a guest dropped by the network doesn't have to re-type their code. Without
 * this column it could not tell that apart from a deliberate Disconnect, so
 * the redirect back to /portal after disconnecting silently put the device
 * straight back online (as a fresh OPNsense session, which is why the usage
 * counters appeared to reset while the clock kept running).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->timestamp('disconnected_at')->nullable()->after('activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('disconnected_at');
        });
    }
};
