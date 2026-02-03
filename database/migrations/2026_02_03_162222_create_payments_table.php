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
    Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tontine_id')->constrained()->onDelete('cascade');
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        
        // Le numéro du tour pour lequel l'utilisateur paye (ex: Tour n°1)
        $table->integer('round_number'); 
        
        $table->decimal('amount', 15, 2);
        $table->timestamp('paid_at')->useCurrent();
        
        // Pour éviter qu'un membre paye deux fois pour le même tour
        $table->unique(['tontine_id', 'user_id', 'round_number']); 
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
