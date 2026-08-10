<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Throughput samples, taken on a schedule, from which the adaptive fair-use
 * loop learns two things it has no other way to know: roughly how fast the
 * shop's internet actually is, and which hours of the day are busy.
 *
 * `ceiling_mbps` looks redundant next to the two rate columns and is the whole
 * reason the estimate can be trusted. Observed throughput is bounded by the
 * caps in force at the time — with a 20 Mbps ceiling and two guests you will
 * never measure more than 40, however fast the line is. A sample taken while
 * the guests were pinned against their own caps says nothing about capacity,
 * and only by storing the ceiling can the learner tell those apart from a
 * sample where the line itself was the limit.
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
