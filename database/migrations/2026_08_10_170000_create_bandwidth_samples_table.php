<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled throughput samples, from which the adaptive fair-use loop learns
 * the line speed and the busy hours.
 *
 * `ceiling_mbps` is what makes the estimate trustworthy: throughput is bounded
 * by the caps in force (20 Mbps x two guests never measures more than 40), so
 * only by storing the ceiling can the learner tell cap-limited samples from
 * line-limited ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bandwidth_samples', function (Blueprint $table) {
            $table->id();

            // Not created_at: the reading is about the moment it was taken, and
            // the hour-of-day histogram is built by grouping on it.
            $table->timestamp('sampled_at')->index();

            $table->decimal('down_mbps', 8, 2);
            $table->decimal('up_mbps', 8, 2);

            // From GuestSessionService — paying customers currently authorized,
            // not everything with an ARP entry.
            $table->unsignedSmallInteger('active_guests')->default(0);

            $table->decimal('ceiling_mbps', 8, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bandwidth_samples');
    }
};
