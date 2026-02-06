<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('tontine_histories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tontine_id')->constrained()->onDelete('cascade');
        $table->foreignId('user_id')->nullable(); // L'utilisateur concerné par l'action
        $table->string('type'); // 'start', 'payment', 'payout', 'penalty', 'close_round'
        $table->double('amount')->default(0);
        $table->integer('round_number')->nullable();
        $table->string('description');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tontine_histories');
    }
};
