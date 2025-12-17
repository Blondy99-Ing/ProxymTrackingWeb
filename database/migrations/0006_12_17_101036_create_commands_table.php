<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commands', function (Blueprint $table) {
            $table->id();

            // ✅ Nullable + Foreign keys
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('employe_id')
                ->nullable()
                ->constrained('employes')
                ->nullOnDelete();

            // ✅ Not nullable (si tu veux aussi nullable, dis-moi)
            $table->foreignId('vehicule_id')
                ->constrained('voitures')
                ->cascadeOnDelete();

            $table->string('CmdNo', 50)->unique();
            $table->string('status', 30)->default('pending');

            $table->timestamps(); // created_at + updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commands');
    }
};
