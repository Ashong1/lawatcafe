<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What guests do on the captive portal (page visits, codes tried, connections,
 * "Need more time?", dropped sessions), for the Portal report page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('voucher_code', 32)->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_events');
    }
};
