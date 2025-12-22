<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Récupérer l'ID du rôle par slug
        $gestionnaireId = DB::table('roles')->where('slug', 'utilisateur_principale')->value('id');

        if (!$gestionnaireId) {
            throw new RuntimeException("Rôle 'utilisateur_principale' introuvable dans la table roles. Exécute d'abord la migration roles.");
        }

        Schema::table('users', function (Blueprint $table) use ($gestionnaireId) {
            $table->foreignId('role_id')
                ->nullable()                 // ✅ OBLIGATOIRE avec nullOnDelete()
                ->default($gestionnaireId)
                ->after('id')
                ->constrained('roles')
                ->nullOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
