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
    public function getStatus(int $id): JsonResponse
    {
        $tontine = Tontine::with(['users', 'payments'])->findOrFail($id);
        
        // 1. Déterminer le tour actuel (basé sur la date)
        $currentRound = $tontine->current_turn; // Utilise la logique de date qu'on a fait avant

        // 2. Trouver le bénéficiaire du pot pour ce tour
        // Celui dont le turn_order == currentRound
        $beneficiary = $tontine->users()
            ->wherePivot('turn_order', $currentRound)
            ->first();

        // 3. Calculer le montant total du pot actuel
        $potAmount = $tontine->amount * $tontine->users()->count();

        // 4. Liste de tous les membres avec leur état de paiement pour ce tour
        $membersStatus = $tontine->users->map(function($user) use ($id, $currentRound) {
            $hasPaid = Payment::where('tontine_id', $id)
                ->where('user_id', $user->id)
                ->where('round_number', $currentRound)
                ->exists();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'has_paid' => $hasPaid,
                'amount_to_pay' => $hasPaid ? 0 : $user->pivot->amount // Optionnel
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'current_round' => $currentRound,
                'pot_total' => $potAmount,
                'beneficiary' => $beneficiary ? [
                    'name' => $beneficiary->name,
                    'phone' => $beneficiary->phone
                ] : null,
                'payments_status' => $membersStatus
            ]
        ]);
    }
}