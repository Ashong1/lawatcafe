<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The previous migration widened ai_action_audits.input_params/result to
 * text for the new encrypted:array cast but only backfilled the mac_address
 * columns — existing rows were left as plaintext JSON, which the cast then
 * fails to decrypt. This encrypts them in place.
 */
return new class extends Migration
{
    private const COLUMNS = ['input_params', 'result'];

    public function up(): void
    {
        DB::table('ai_action_audits')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $update = [];
                foreach (self::COLUMNS as $column) {
                    if ($row->$column !== null && ! $this->alreadyEncrypted($row->$column)) {
                        $update[$column] = Crypt::encryptString($row->$column);
                    }
                }
                if ($update) {
                    DB::table('ai_action_audits')->where('id', $row->id)->update($update);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('ai_action_audits')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $update = [];
                foreach (self::COLUMNS as $column) {
                    if ($row->$column !== null && $this->alreadyEncrypted($row->$column)) {
                        $update[$column] = Crypt::decryptString($row->$column);
                    }
                }
                if ($update) {
                    DB::table('ai_action_audits')->where('id', $row->id)->update($update);
                }
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
