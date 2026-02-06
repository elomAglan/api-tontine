<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TontineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\ContactController; // Import ajouté ici

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- ROUTES PUBLIQUES ---
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login'])->name('login');

// --- ROUTES PROTÉGÉES (AUTH:SANCTUM) ---
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('profile', [AuthController::class, 'profile']);

    // --- TON NOUVEL ANNUAIRE GLOBAL ---
    Route::get('my-contacts', [ContactController::class, 'index']);

    // --- GESTION DES TONTINES ---
    Route::prefix('tontines')->group(function () {
        
        // 1. Actions CRUD
        Route::get('/', [TontineController::class, 'index']);           
        Route::post('/', [TontineController::class, 'store']);          
        Route::get('{id}', [TontineController::class, 'show']);         
        Route::delete('{id}', [TontineController::class, 'destroy']);   

        // 2. Membres & Ordre
        Route::post('{id}/add-member', [TontineController::class, 'addMember']); 
        Route::post('{id}/shuffle', [TontineController::class, 'shuffleMembers']); 

        // 3. Cycle de Vie
        Route::post('{id}/start', [TontineController::class, 'start']); 
        Route::post('{id}/close-round', [TontineController::class, 'closeRound']); 

        // 4. Cotisations & État
        Route::post('{id}/record-payment', [TontineController::class, 'recordPayment']);
        Route::get('{id}/payment-status', [TontineController::class, 'getPaymentStatus']);
        Route::get('{id}/debtors', [TontineController::class, 'getDebtors']);
        Route::get('{id}/history', [TontineController::class, 'getHistory']); 
         
        // 5. Discipline
        Route::post('{id}/apply-penalty', [TontineController::class, 'applyPenalty']);
    });

    // 6. Gestion individuelle des amendes
    Route::post('penalties/{penalty_id}/pay', [TontineController::class, 'payPenalty']);

    // 7. Stats
    Route::get('/dashboard/stats', [DashboardController::class, 'index']);
});