<?php

namespace App\Http\Controllers;

use App\Models\Tontine;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Contributions", description: "Gestion des paiements et des gains")]
class ContributionController extends Controller
{
    /**
     * 1. ENREGISTRER UN PAIEMENT (ADMIN SEUL)
     */
    #[OA\Post(
        path: "/api/tontines/{id}/pay",
        summary: "Enregistrer la cotisation d'un membre",
        security: [["sanctum" => []]],
        tags: ["Contributions"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["user_id", "round_number"],
                properties: [
                    new OA\Property(property: "user_id", type: "integer"),
                    new OA\Property(property: "round_number", type: "integer", description: "Le numéro du tour actuel")
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Paiement validé")]
    )]
    public function store(Request $request, int $id): JsonResponse
    {
        $tontine = Tontine::findOrFail($id);

        // Seul l'admin peut valider un paiement reçu
        if ($tontine->creator_id !== $request->user()->id) {
            return response()->json(['message' => 'Action réservée à l\'admin'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'round_number' => 'required|integer|min:1',
        ]);

        // Vérifier si déjà payé
        $exists = Payment::where('tontine_id', $id)
            ->where('user_id', $validated['user_id'])
            ->where('round_number', $validated['round_number'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Cotisation déjà enregistrée pour ce tour'], 422);
        }

        $payment = Payment::create([
            'tontine_id' => $id,
            'user_id' => $validated['user_id'],
            'round_number' => $validated['round_number'],
            'amount' => $tontine->amount, // Montant fixe de la tontine
            'paid_at' => now()
        ]);

        return response()->json(['success' => true, 'data' => $payment]);
    }

    /**
     * 2. ÉTAT DES COTISATIONS ET BÉNÉFICIAIRE
     */
    #[OA\Get(
        path: "/api/tontines/{id}/status",
        summary: "Voir qui a payé et qui reçoit le pot",
        security: [["sanctum" => []]],
        tags: ["Contributions"],
        responses: [new OA\Response(response: 200, description: "État récupéré")]
    )]
    public function getStatus(Request $request, int $id): JsonResponse
    {
        // --- LOGS DE DEBUG ---
        $user = $request->user();
        \Log::info("--- DEBUG STATUS TONTINE ---");
        \Log::info("Utilisateur connecté ID : " . ($user ? $user->id : 'NON AUTHENTIFIÉ'));
        \Log::info("Requête pour Tontine ID : " . $id);

        try {
            $tontine = Tontine::with(['users', 'payments'])->findOrFail($id);
            
            // 1. Déterminer le tour actuel
            $currentRound = $tontine->current_turn ?? 1;
            \Log::info("Tour actuel détecté : " . $currentRound);

            // 2. Trouver le bénéficiaire
            $beneficiary = $tontine->users()
                ->wherePivot('turn_order', $currentRound)
                ->first();
            
            \Log::info("Bénéficiaire trouvé : " . ($beneficiary ? $beneficiary->name : 'AUCUN'));

            // 3. Calculer le montant total
            $potAmount = $tontine->amount * $tontine->users()->count();

            // 4. Liste de tous les membres avec état de paiement
            $membersStatus = $tontine->users->map(function($u) use ($id, $currentRound) {
                $hasPaid = Payment::where('tontine_id', $id)
                    ->where('user_id', $u->id)
                    ->where('round_number', $currentRound)
                    ->exists();

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'has_paid' => $hasPaid,
                ];
            });

            \Log::info("Nombre de membres traités : " . $membersStatus->count());
            \Log::info("----------------------------");

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tontine->id,
                    'status' => $tontine->status,
                    'current_round' => $currentRound,
                    'pot_total' => $potAmount,
                    'beneficiary' => $beneficiary ? [
                        'name' => $beneficiary->name,
                        'phone' => $beneficiary->phone
                    ] : null,
                    'members' => $membersStatus // Note: Renommé pour correspondre à ton code Flutter
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error("ERREUR DANS getStatus : " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}