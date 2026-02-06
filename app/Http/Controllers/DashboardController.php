<?php

namespace App\Http\Controllers;

use App\Models\Tontine;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 401);
            }

            // Récupérer les tontines actives
            $activeGroups = $user->tontines()
                ->where('tontines.status', 'active')
                ->withCount('users')
                ->get();

            $globalExpected = 0;
            $globalPaid = 0;
            $lateAlerts = 0;

            $mappedGroups = $activeGroups->map(function($tontine) use (&$globalExpected, &$globalPaid, &$lateAlerts) {
                $currentRound = (int) ($tontine->current_turn ?? 1);
                $usersCount = (int) ($tontine->users_count ?? 0);
                $amount = (double) ($tontine->amount ?? 0);

                // Statistiques par tontine
                $expectedForThisTontine = $amount * $usersCount;
                
                // Nombre de paiements pour le tour actuel
                $paidMembersCount = Payment::where('tontine_id', $tontine->id)
                    ->where('round_number', $currentRound)
                    ->count();

                $paidAmountForThisTontine = $paidMembersCount * $amount;

                // Cumul global
                $globalExpected += $expectedForThisTontine;
                $globalPaid += $paidAmountForThisTontine;

                // Logique d'alerte
                if ($tontine->frequency_days > 0) {
                    $deadline = Carbon::parse($tontine->updated_at)->addDays((int)$tontine->frequency_days);
                    if ($paidAmountForThisTontine < $expectedForThisTontine && $deadline->isPast()) {
                        $lateAlerts++;
                    }
                }

                // Bénéficiaire
                $beneficiary = $tontine->users()
                    ->wherePivot('turn_order', $currentRound)
                    ->first();

                return [
                    'id' => (int) $tontine->id,
                    'name' => (string) $tontine->name,
                    'amount' => $amount,
                    'status' => (string) $tontine->status,
                    'current_turn' => $currentRound,
                    'users_count' => $usersCount,
                    'paid_count' => (int) $paidMembersCount,
                    'beneficiary_name' => $beneficiary ? (string) $beneficiary->name : 'À déterminer',
                ];
            });

            $recoveryRate = $globalExpected > 0 ? round(($globalPaid / $globalExpected) * 100) : 0;

            // ON RETOURNE TOUJOURS UN TABLEAU PLAT
            return response()->json([
                'success' => true,
                'total_to_collect' => (int) max(0, $globalExpected - $globalPaid),
                'recovery_rate' => (int) $recoveryRate,
                'late_alerts' => (int) $lateAlerts,
                'active_groups_count' => (int) $activeGroups->count(),
                'recent_groups' => $mappedGroups->take(5)->values()->all()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Erreur Dashboard: ' . $e->getMessage()
            ], 500);
        }
    }
}