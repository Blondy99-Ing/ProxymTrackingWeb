<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gps_device_status', function (Blueprint $table) {
            $table->id();

            $table->string('mac_id_gps')->unique();
            $table->string('account_name')->default('tracking')->index();

            $table->string('state')->nullable()->index();         // OFFLINE / ONLINE_MOVING / ONLINE_STATIONARY / DISABLED / UNKNOWN
            $table->boolean('is_online')->nullable()->index();    // true/false/null

            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('last_server_at')->nullable();

            $table->timestamp('offline_started_at')->nullable()->index();
            $table->unsignedInteger('offline_seconds')->nullable();
            $table->unsignedSmallInteger('threshold_minutes')->default(25);

            $table->timestamp('last_change_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_device_status');
    }
};
