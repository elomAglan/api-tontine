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
        Schema::create('tontines', function (Blueprint $table) {
            $table->id();
            $table->string('name'); 
            $table->decimal('amount', 15, 2); 
            $table->integer('frequency_days'); 
            
            // start_date est nullable car on ne la définit qu'au clic sur "Démarrer"
            $table->date('start_date')->nullable(); 

            // Identifiant du créateur (Admin)
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');

            /**
             * LOGIQUE DE GESTION ET TRANSPARENCE
             */
            // Statut global du groupe
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');

            // Pour prouver aux membres si l'ordre est aléatoire ou manuel
            $table->enum('order_type', ['manual', 'random', 'not_defined'])->default('not_defined');

            // Le verrou qui empêche de modifier l'ordre après un mixage ou le début
            $table->boolean('order_locked')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tontines');
    }
};