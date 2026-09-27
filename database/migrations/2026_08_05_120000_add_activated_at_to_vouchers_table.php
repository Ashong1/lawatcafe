<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits "the guest redeemed this code" from "the firewall has let this device
 * through".
 *
 * Granting internet at redemption lets the phone's captive-network assistant
 * see its connectivity probe succeed while the success page is still loading,
 * and the OS destroys the window before the guest can read their remaining
 * time. So redemption and activation are two steps, and this column tells
 * them apart.
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
