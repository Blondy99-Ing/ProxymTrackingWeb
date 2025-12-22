<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Récupérer l'ID du rôle admin
        $adminId = DB::table('roles')->where('slug', 'admin')->value('id');

        if (!$adminId) {
            throw new RuntimeException("Rôle 'admin' introuvable dans la table roles. Exécute d'abord la migration roles.");
        }

       Schema::table('employes', function (Blueprint $table) use ($adminId) {
            $table->foreignId('role_id')
                ->nullable()                 // ✅ OBLIGATOIRE avec nullOnDelete()
                ->default($adminId)
                ->after('id')
                ->constrained('roles')
                ->nullOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('employes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
