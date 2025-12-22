<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            // Nom affiché
            $table->string('name', 80)->unique();

            // Clé technique (utile pour checks dans le code)
            $table->string('slug', 80)->unique();


            // Description optionnelle
            $table->string('description')->nullable();

            $table->timestamps();
        });

        // Insert des rôles
        DB::table('roles')->insert([
            [
                'name' => 'Administrateur',
                'slug' => 'admin',
                'description' => 'Accès total à la plateforme interne',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Call center',
                'slug' => 'call_center',
                'description' => 'Support et assistance utilisateurs de la plateforme interne',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Gestionnaire plateforme Partenaire',
                'slug' => 'gestionnaire_plateforme',
                'description' => 'Gestion principale des opérations de la plateforme partenaire',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Utilisateur plateforme',
                'slug' => 'utilisateur_principale',
                'description' => 'Utilisateur standard de la plateforme',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Utilisateur secondaire de vehicule des partenaires',
                'slug' => 'utilisateur_secondaire',
                'description' => 'Utilisateur secondaire du vehicule',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
