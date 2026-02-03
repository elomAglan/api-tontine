<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TontineController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// ROUTES PUBLIQUES
// ==========================================
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login'])->name('login');


// ==========================================
// ROUTES PROTÉGÉES (AUTH:SANCTUM)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    
    // --- Authentification & Profil ---
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('profile', [AuthController::class, 'profile']);

    // --- Gestion des Groupes de Tontine ---
    Route::prefix('tontines')->group(function () {
        
        // 1. Actions CRUD de base
        Route::get('/', [TontineController::class, 'index']);             // Lister mes groupes
        Route::post('/', [TontineController::class, 'store']);            // Créer un groupe
        Route::get('{id}', [TontineController::class, 'show']);           // Détails d'un groupe
        Route::delete('{id}', [TontineController::class, 'destroy']);     // Supprimer définitivement

        // 2. Gestion des Membres & Administration
        Route::post('{id}/add-member', [TontineController::class, 'addMember']);                  // Inviter
        Route::delete('{id}/remove-member/{user_id}', [TontineController::class, 'removeMember']); // Retirer
        Route::put('{id}/transfer-admin', [TontineController::class, 'transferAdmin']);           // Déléguer

        // 3. Gestion de l'Ordre de passage
        Route::post('{id}/shuffle', [TontineController::class, 'shuffleMembers']);    // Aléatoire
        Route::put('{id}/reorder', [TontineController::class, 'updateMemberOrder']);  // Manuel

        // 4. Cycle de Vie & Suivi
        Route::post('{id}/start', [TontineController::class, 'start']);               // Lancer la tontine
        Route::get('{id}/history', [TontineController::class, 'getHistory']);         // Calendrier des tours

        // 5. Cotisations (Argent)
        Route::post('{id}/record-payment', [TontineController::class, 'recordPayment']);  // Valider un paiement
        Route::get('{id}/payment-status', [TontineController::class, 'getPaymentStatus']); // État du tour actuel
        Route::get('{id}/debtors', [TontineController::class, 'getDebtors']);              // Liste des retardataires

        // 6. Discipline (Amendes)
        Route::post('{id}/apply-penalty', [TontineController::class, 'applyPenalty']);     // Mettre une amende
    });

    // 7. Routes spécifiques pour les amendes (hors préfixe tontines car lié à l'ID amende)
    Route::post('penalties/{penalty_id}/pay', [TontineController::class, 'payPenalty']);   // Payer une amende
});