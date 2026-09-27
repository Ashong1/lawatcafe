<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits "the guest redeemed this code" from "the firewall let this device
 * through": granting internet at redemption lets the phone's sign-in window
 * see its probe succeed and close before the guest can read their time.
 *
 * Not the session clock — that stays used_at, so expiry maths is unchanged.
 * It answers "was this voucher ever let through?", which lets the portal
 * re-authorize an abandoned redemption without undoing a deliberate
 * Disconnect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('used_at');
        });

        // Every already-redeemed voucher predates this split and was authorized
        // at redemption time. Backfilling from used_at keeps them out of the
        // "never activated, safe to auto-authorize" branch — otherwise the next
        // portal visit by an old device would silently re-open a session the
        // guest had disconnected or that had already been reaped.
        DB::table('vouchers')
            ->where('is_used', true)
            ->whereNotNull('used_at')
            ->update(['activated_at' => DB::raw('used_at')]);
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('activated_at');
        });
    }
};
