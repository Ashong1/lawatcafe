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
        Schema::create('static_ip_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('mac_address')->unique();
            $table->string('ip_address')->unique();
            $table->string('hostname')->nullable();
            // OPNsense-side identifiers, needed to update/delete this exact
            // reservation later — the subnet a reservation lives under can't
            // be inferred from the IP alone once multiple Kea subnets exist.
            $table->string('kea_subnet_uuid')->nullable();
            $table->string('kea_reservation_uuid')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('static_ip_assignments');
    }
};
