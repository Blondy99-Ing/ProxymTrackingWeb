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
            // Colonne pour enregistrer le numéro de SIM du GPS associé au véhicule
            $table->string('sim_gps')
                  ->nullable()
                  ->after('mac_id_gps'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voitures', function (Blueprint $table) {
            $table->dropColumn('sim_gps');
        });
    }
};
