<?php

namespace App\Http\Controllers;

use App\Models\Tontine;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            $user = Auth::user();

            // 1. Récupérer toutes les tontines actives de l'utilisateur
            $activeGroups = $user->tontines()
                ->where('tontines.status', 'active')
                ->withCount('users')
                ->get();

            $globalExpected = 0;
            $globalPaid = 0;
            $lateAlerts = 0;

            // 2. Parcourir chaque groupe individuellement pour calculer les stats
            foreach ($activeGroups as $tontine) {
                $currentTurn = $tontine->current_turn ?? 1;
                $membersCount = $tontine->users_count; // Grâce au withCount('users')

                // A. Montant attendu pour CE tour de CETTE tontine
                $expectedForThisGroup = $tontine->amount * $membersCount;
                $globalExpected += $expectedForThisGroup;

                // B. Montant déjà payé pour CE tour précis de CETTE tontine
                $paidForThisGroup = Payment::where('tontine_id', $tontine->id)
                    ->where('round_number', $currentTurn)
                    ->sum('amount');
                $globalPaid += $paidForThisGroup;

                // C. Vérifier si cette tontine est en retard (Alerte)
                // Si la date limite du tour est dépassée et que tout n'est pas payé
                if ($tontine->is_overdue && ($paidForThisGroup < $expectedForThisGroup)) {
                    $lateAlerts++;
                }
            }

            // 3. Calculs finaux globaux
            $totalToCollect = $globalExpected - $globalPaid;
            
            $recoveryRate = $globalExpected > 0 
                ? round(($globalPaid / $globalExpected) * 100) 
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_to_collect' => (int) $totalToCollect,
                    'recovery_rate' => (int) $recoveryRate,
                    'late_alerts' => (int) $lateAlerts,
                    'active_groups' => $activeGroups->take(5) // Les 5 plus récentes pour la liste
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}