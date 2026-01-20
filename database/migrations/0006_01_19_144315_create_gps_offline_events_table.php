<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gps_offline_historique', function (Blueprint $table) {
            $table->id();

            $table->string('mac_id_gps')->index();
            $table->string('account_name')->default('tracking')->index();

            $table->timestamp('started_at')->index();     // moment offline (last_seen + threshold)
            $table->timestamp('detected_at')->nullable(); // moment où le cron l’a constaté

            $table->timestamp('ended_at')->nullable()->index();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->timestamp('last_seen_at')->nullable();   // heart_time
            $table->timestamp('last_server_at')->nullable(); // server_time

            $table->unsignedSmallInteger('threshold_minutes')->default(25);

            $table->json('meta')->nullable();
            $table->timestamps();

            // utile pour requêtes "event ouvert"
            $table->index(['mac_id_gps', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_offline_historique');
    }
};
