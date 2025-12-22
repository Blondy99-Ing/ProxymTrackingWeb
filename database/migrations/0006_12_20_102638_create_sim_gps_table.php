<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sim_gps', function (Blueprint $table) {
            $table->id();

            $table->uuid('objectid')->nullable()->index();
            $table->string('mac_id')->unique();          // macid (IMEI/ID device)
            $table->string('sim_number', 30)->nullable()->unique(); // téléphone SIM (nullable)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_gps');
    }
};
