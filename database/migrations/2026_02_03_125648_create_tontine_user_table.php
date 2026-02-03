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
        Schema::create('tontine_user', function (Blueprint $table) {
            $table->id();
            
            // Liaisons indispensables
            $table->foreignId('tontine_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            /**
             * GESTION DES TOURS
             */
            // turn_order : Le numéro de passage (1, 2, 3...). 
            // Nullable car au début, personne n'a d'ordre tant que l'admin n'a pas mixé ou choisi.
            $table->integer('turn_order')->nullable(); 

            /**
             * RÔLES ET ÉTATS
             */
            // role : Permet de savoir qui est le chef dans ce groupe précis
            $table->enum('role', ['admin', 'member'])->default('member');

            // status : 'pending' tant que l'utilisateur n'a pas accepté ou que le tour n'a pas commencé
            $table->enum('status', ['pending', 'active'])->default('pending');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tontine_user');
    }
};