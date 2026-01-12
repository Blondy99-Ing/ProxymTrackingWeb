<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sim_gps', function (Blueprint $table) {
            $table->string('account_name', 50)
                ->nullable()
                ->after('objectid')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('sim_gps', function (Blueprint $table) {
            $table->dropIndex(['account_name']);
            $table->dropColumn('account_name');
        });
    }
};
