<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits "the guest redeemed this code" from "the firewall has let this device
 * through", which used to be the same instant.
 *
 * Granting internet at redemption meant the phone's captive-network assistant
 * saw its connectivity probe succeed while the success page was still in
 * flight, so the OS destroyed the window before the guest could read their
 * remaining time. Redemption and activation are now two steps, and this column
 * is what tells them apart.
 *
 * It is deliberately NOT the session clock — that stays used_at, unchanged, so
 * every existing expiry calculation keeps working. This only answers "has this
 * voucher ever been let through?", which is what lets the portal safely
 * re-authorize an abandoned redemption without also undoing a guest's
 * deliberate Disconnect.
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
