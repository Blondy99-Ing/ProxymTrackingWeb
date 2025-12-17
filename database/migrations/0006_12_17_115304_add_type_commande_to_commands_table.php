<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->string('type_commande', 30)
                ->nullable()
                ->after('status')
                ->index();
            // Exemples: coupure_moteur | allumage_moteur
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropIndex(['type_commande']);
            $table->dropColumn('type_commande');
        });
    }
};
