<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per network health check (every minute, see NetworkHealthService).
 * The headline figures are columns so the Health page can chart them without
 * decoding JSON; the full per-check results are kept alongside.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_health_checks', function (Blueprint $table) {
            $table->id();
            $table->timestamp('checked_at')->index();
            $table->string('overall', 8);
            $table->decimal('internet_latency_ms', 8, 1)->nullable();
            $table->decimal('internet_loss_pct', 5, 1)->nullable();
            $table->boolean('dns_ok')->nullable();
            $table->unsignedSmallInteger('dhcp_used')->nullable();
            $table->unsignedSmallInteger('dhcp_size')->nullable();
            $table->unsignedSmallInteger('guests_online')->nullable();
            $table->unsignedSmallInteger('infrastructure_down')->default(0);
            $table->json('results');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_health_checks');
    }
};
