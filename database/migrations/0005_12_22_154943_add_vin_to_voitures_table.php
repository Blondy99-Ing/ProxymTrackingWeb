<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voitures', function (Blueprint $table) {
            // VIN standard = 17 caractères
            $table->string('vin', 17)->nullable()->unique()->after('immatriculation');
        });
    }

    public function down(): void
    {
        Schema::table('voitures', function (Blueprint $table) {
            $table->dropUnique(['vin']);
            $table->dropColumn('vin');
        });
    }
};
