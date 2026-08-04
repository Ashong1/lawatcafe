<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens columns that are about to hold ciphertext (encrypted strings are
 * much longer than their plaintext) and, for the three mac_address columns,
 * adds a deterministic HMAC "blind index" column alongside the
 * non-deterministic encrypted value — the encrypted column can never be
 * queried directly (random IV means the same MAC encrypts differently every
 * time), so exact-match lookups, uniqueness checks, and the repeat-MAC-abuse
 * GROUP BY all move to the hash column instead. See BannedDevice,
 * StaticIpAssignment, Voucher's HasHashedMacAddress trait.
 */
return new class extends Migration
{
    private const MAC_TABLES = ['vouchers', 'banned_devices', 'static_ip_assignments'];

    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('vouchers_mac_address_index');
        });
        Schema::table('banned_devices', function (Blueprint $table) {
            $table->dropUnique('banned_devices_mac_address_unique');
        });
        Schema::table('static_ip_assignments', function (Blueprint $table) {
            $table->dropUnique('static_ip_assignments_mac_address_unique');
        });

        foreach (self::MAC_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->text('mac_address')->nullable()->change();
                $table->string('mac_address_hash', 64)->nullable()->after('mac_address');
            });
        }

        Schema::table('ai_action_audits', function (Blueprint $table) {
            $table->text('input_params')->nullable()->change();
            $table->text('result')->nullable()->change();
        });

        $this->backfillMacTable('vouchers');
        $this->backfillMacTable('banned_devices');
        $this->backfillMacTable('static_ip_assignments');

        Schema::table('vouchers', function (Blueprint $table) {
            $table->index('mac_address_hash', 'vouchers_mac_address_hash_index');
        });
        Schema::table('banned_devices', function (Blueprint $table) {
            $table->unique('mac_address_hash', 'banned_devices_mac_address_hash_unique');
        });
        Schema::table('static_ip_assignments', function (Blueprint $table) {
            $table->unique('mac_address_hash', 'static_ip_assignments_mac_address_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('vouchers_mac_address_hash_index');
        });
        Schema::table('banned_devices', function (Blueprint $table) {
            $table->dropUnique('banned_devices_mac_address_hash_unique');
        });
        Schema::table('static_ip_assignments', function (Blueprint $table) {
            $table->dropUnique('static_ip_assignments_mac_address_hash_unique');
        });

        foreach (self::MAC_TABLES as $tableName) {
            DB::table($tableName)->whereNotNull('mac_address')->orderBy('id')->chunkById(200, function ($rows) use ($tableName) {
                foreach ($rows as $row) {
                    DB::table($tableName)->where('id', $row->id)->update([
                        'mac_address' => Crypt::decryptString($row->mac_address),
                    ]);
                }
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('mac_address')->nullable()->change();
                $table->dropColumn('mac_address_hash');
            });
        }

        Schema::table('vouchers', function (Blueprint $table) {
            $table->index('mac_address', 'vouchers_mac_address_index');
        });
        Schema::table('banned_devices', function (Blueprint $table) {
            $table->unique('mac_address', 'banned_devices_mac_address_unique');
        });
        Schema::table('static_ip_assignments', function (Blueprint $table) {
            $table->unique('mac_address', 'static_ip_assignments_mac_address_unique');
        });

        Schema::table('ai_action_audits', function (Blueprint $table) {
            $table->json('input_params')->nullable()->change();
            $table->json('result')->nullable()->change();
        });
    }

    /**
     * Encrypts existing plaintext MAC addresses in place and derives their
     * blind-index hash. Skips rows whose mac_address already decrypts
     * successfully so this migration is safe to run more than once.
     */
    private function backfillMacTable(string $tableName): void
    {
        DB::table($tableName)->whereNotNull('mac_address')->orderBy('id')->chunkById(200, function ($rows) use ($tableName) {
            foreach ($rows as $row) {
                $plaintext = $this->alreadyEncrypted($row->mac_address) ? Crypt::decryptString($row->mac_address) : $row->mac_address;

                $normalized = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $plaintext));

                DB::table($tableName)->where('id', $row->id)->update([
                    'mac_address' => Crypt::encryptString($plaintext),
                    'mac_address_hash' => hash_hmac('sha256', $normalized, config('app.key')),
                ]);
            }
        });
    }

    private function alreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
};
