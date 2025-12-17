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
        Schema::table('voitures', function (Blueprint $table) {
    // 🧹 On supprime les anciennes colonnes
    $table->dropColumn([
        'geofence_latitude',
        'geofence_longitude',
        'geofence_radius', // ✅ corrigé ici
        'latitude',
        'longitude',

    ]);

    // ⏰ Plage horaire pour TimeZone
    $table->time('time_zone_start')->nullable()->after('photo');
    $table->time('time_zone_end')->nullable()->after('time_zone_start');

    // 🚦 Vitesse max pour SpeedZone
    $table->integer('speed_zone')->nullable()->after('time_zone_end');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voitures', function (Blueprint $table) {
            $table->dropColumn(['time_zone_start', 'time_zone_end', 'speed_zone']);
        });
    }
};
