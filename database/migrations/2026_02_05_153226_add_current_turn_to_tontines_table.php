<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $blueprint) {
            // On ajoute la colonne. Par défaut on commence au tour 1.
            // On la place après 'status' pour que la table reste propre.
            $blueprint->integer('current_turn')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tontines', function (Blueprint $blueprint) {
            $blueprint->dropColumn('current_turn');
        });
    }
};