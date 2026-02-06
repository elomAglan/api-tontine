<?php

namespace App\Http\Controllers;

use App\Models\Tontine;
use App\Models\User;
use App\Models\Payment;
use App\Models\Penalty;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Tontines", description: "Gestion complète : Groupes, Membres, Cotisations et Discipline")]
class TontineController extends Controller
{
    /**
     * Helper : Vérifie si l'utilisateur est l'administrateur.
     */
    private function isAdmin(Tontine $tontine, int $userId): bool
    {
        return (int)$tontine->creator_id === $userId;
    }

    /**
     * 1. LISTER LES TONTINES
     */
    #[OA\Get(
        path: "/api/tontines",
        summary: "Lister les tontines de l'utilisateur connecté",
        security: [["sanctum" => []]],
        tags: ["Tontines"],
        responses: [new OA\Response(response: 200, description: "Succès")]
    )]
    public function index(Request $request): JsonResponse
    {
        $tontines = $request->user()->tontines()
            ->with(['users' => function ($query) {
                $query->select('users.id', 'users.name', 'users.phone')->orderBy('tontine_user.turn_order');
            }])->latest()->get();

        return response()->json(['success' => true, 'data' => $tontines]);
    }

    /**
     * 2. CRÉER UNE TONTINE
     */
    #[OA\Post(
        path: "/api/tontines",
        summary: "Créer un nouveau groupe de tontine",
        security: [["sanctum" => []]],
        tags: ["Tontines"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "amount", "frequency_days"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Tontine Famille"),
                    new OA\Property(property: "amount", type: "number", example: 50000),
                    new OA\Property(property: "frequency_days", type: "integer", example: 30),
                    new OA\Property(property: "late_fee", type: "number", example: 1000)
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: "Créée")]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1',
            'frequency_days' => 'required|integer|min:1',
            'late_fee' => 'nullable|numeric|min:0'
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $tontine = Tontine::create([
                'name' => $validated['name'],
                'amount' => $validated['amount'],
                'frequency_days' => $validated['frequency_days'],
                'late_fee' => $validated['late_fee'] ?? 0,
                'creator_id' => $request->user()->id,
                'status' => 'pending',
                'current_turn' => 1, // Ajouté pour initialiser la colonne
                'order_type' => 'not_defined',
                'order_locked' => false
            ]);

            $tontine->users()->attach($request->user()->id, [
                'role' => 'admin',
                'status' => 'active',
                'turn_order' => null
            ]);

            return response()->json(['success' => true, 'data' => $tontine], 201);
        });
    }

    /**
     * 3. DÉTAILS D'UNE TONTINE
     */
    #[OA\Get(
        path: "/api/tontines/{id}",
        summary: "Détails complets d'une tontine",
        security: [["sanctum" => []]],
        tags: ["Tontines"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Succès")]
    )]
    public function show(int $id): JsonResponse
    {
        $tontine = Tontine::with(['users' => function ($q) {
            $q->orderBy('tontine_user.turn_order');
        }])->findOrFail($id);
        
        return response()->json(['success' => true, 'data' => $tontine]);
    }

    /**
     * 4. AJOUTER UN MEMBRE
     */
    #[OA\Post(
        path: "/api/tontines/{id}/add-member",
        summary: "Ajouter un membre par son numéro de téléphone",
        security: [["sanctum" => []]],
        tags: ["Tontines"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Ajouté")]
    )]
    public function addMember(Request $request, int $id): JsonResponse
    {
        $tontine = Tontine::findOrFail($id);
        if (!$this->isAdmin($tontine, $request->user()->id)) return response()->json(['message' => 'Interdit'], 403);

        $userToAdd = User::where('phone', $request->phone)->first();
        if (!$userToAdd) return response()->json(['message' => 'Utilisateur introuvable'], 404);

        $tontine->users()->syncWithoutDetaching([$userToAdd->id => ['role' => 'member', 'status' => 'pending']]);
        return response()->json(['message' => 'Membre ajouté avec succès']);
    }

    /**
     * 5. MIXAGE ALÉATOIRE
     */
    #[OA\Post(
        path: "/api/tontines/{id}/shuffle",
        summary: "Générer un ordre de passage aléatoire",
        security: [["sanctum" => []]],
        tags: ["Tontines"],
        responses: [new OA\Response(response: 200, description: "Succès")]
    )]
    public function shuffleMembers(Request $request, int $id): JsonResponse
    {
        $tontine = Tontine::with('users')->findOrFail($id);
        if (!$this->isAdmin($tontine, $request->user()->id)) return response()->json(['message' => 'Interdit'], 403);

        $memberIds = $tontine->users()->pluck('users.id')->toArray();
        shuffle($memberIds);

        DB::transaction(function () use ($tontine, $memberIds) {
            foreach ($memberIds as $index => $userId) {
                $tontine->users()->updateExistingPivot($userId, ['turn_order' => $index + 1]);
            }
            $tontine->update(['order_type' => 'random', 'order_locked' => true]);
        });

        return response()->json(['message' => 'Ordre aléatoire généré et verrouillé']);
    }

 /**
     * 6. DÉMARRER LA TONTINE (TRI + ACTIVATION + HISTORIQUE)
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $tontine = Tontine::with('users')->findOrFail($id);

        // 1. Sécurité : Vérifier si c'est bien l'admin
        if (!$this->isAdmin($tontine, $request->user()->id)) {
            return response()->json(['success' => false, 'message' => 'Interdit'], 403);
        }

        // 2. Sécurité : Vérifier le nombre de membres
        $membersCount = $tontine->users()->count();
        if ($membersCount < 2) {
            return response()->json(['success' => false, 'message' => 'Il faut au moins 2 membres pour démarrer'], 422);
        }

        return DB::transaction(function () use ($tontine, $membersCount) {
            // 3. LOGIQUE DE TRI : On utilise inRandomOrder() pour un vrai mélange SQL
            if (!$tontine->order_locked) {
                $memberIds = $tontine->users()
                    ->inRandomOrder()
                    ->pluck('users.id')
                    ->toArray();

                foreach ($memberIds as $index => $userId) {
                    $tontine->users()->updateExistingPivot($userId, [
                        'turn_order' => $index + 1
                    ]);
                }
            }

            // 4. ACTIVATION : On met à jour les infos de la tontine
            $tontine->update([
                'status' => 'active',
                'start_date' => now(),
                'current_turn' => 1,
                'order_type' => $tontine->order_type === 'not_defined' ? 'random' : $tontine->order_type,
                'order_locked' => true
            ]);

            // 5. HISTORIQUE : Enregistrement
            $tontine->logActivity(
                'start', 
                "La tontine a été démarrée avec $membersCount membres. Ordre de passage généré et verrouillé."
            );

            return response()->json([
                'success' => true,
                'message' => 'Tontine démarrée avec succès !',
                'data' => $tontine->load(['users' => function($q) {
                    $q->orderBy('tontine_user.turn_order');
                }])
            ]);
        });
    }

    /**
     * 7. ENREGISTRER UN PAIEMENT + HISTORIQUE
     */
    public function recordPayment(Request $request, int $id): JsonResponse
    {
        $tontine = Tontine::findOrFail($id);
        
        // 1. Vérification Admin
        if (!$this->isAdmin($tontine, $request->user()->id)) {
            return response()->json(['success' => false, 'message' => 'Interdit'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'round_number' => 'required|integer|min:1',
        ]);

        // 2. Vérifier si déjà payé
        $exists = Payment::where('tontine_id', $id)
            ->where('user_id', $validated['user_id'])
            ->where('round_number', $validated['round_number'])
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Ce membre a déjà payé pour ce tour'], 422);
        }

        return DB::transaction(function () use ($tontine, $validated, $id) {
            // 3. Créer le paiement
            $payment = Payment::create([
                'tontine_id' => $id,
                'user_id' => $validated['user_id'],
                'round_number' => $validated['round_number'],
                'amount' => $tontine->amount,
                'paid_at' => now()
            ]);

            // 4. Récupérer le nom de l'utilisateur pour une description claire
            $member = User::find($validated['user_id']);

            // 5. AJOUT À L'HISTORIQUE
            $tontine->logActivity(
                'payment', 
                "Cotisation de {$member->name} reçue pour le tour {$validated['round_number']}.",
                $member->id,
                $tontine->amount
            );

            return response()->json([
                'success' => true, 
                'message' => 'Paiement validé et enregistré dans l\'historique',
                'data' => $payment
            ]);
        });
    }

    /**
     * 8. ÉTAT DU TOUR ACTUEL (AMÉLIORÉ)
     */
    #[OA\Get(
        path: "/api/tontines/{id}/payment-status",
        summary: "Voir qui a payé et qui reçoit le pot",
        security: [["sanctum" => []]],
        tags: ["Tontines"],
        responses: [new OA\Response(response: 200, description: "Succès")]
    )]
    public function getPaymentStatus(int $id): JsonResponse
    {
        try {
            $tontine = Tontine::with('users')->findOrFail($id);
            $currentRound = $tontine->current_turn ?? 1;

            $beneficiary = $tontine->users()->wherePivot('turn_order', $currentRound)->first();
            $totalPot = $tontine->amount * $tontine->users->count();

            $payments = Payment::where('tontine_id', $id)
                ->where('round_number', $currentRound)
                ->pluck('user_id')->toArray();

            $memberStatus = $tontine->users->map(fn($u) => [
                'id' => $u->id, 
                'name' => $u->name, 
                'has_paid' => in_array($u->id, $payments),
                // Indique si la personne est en retard par rapport à la deadline calculée
                'is_late' => !in_array($u->id, $payments) && $tontine->is_overdue 
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tontine->id,
                    'status' => $tontine->status,
                    'current_round' => $currentRound,
                    'round_deadline' => $tontine->round_deadline, // Utilise ton modèle
                    'days_left' => $tontine->days_left, // Utilise ton modèle
                    'total_pot' => $totalPot,
                    'beneficiary' => $beneficiary ? ['name' => $beneficiary->name] : null,
                    'members' => $memberStatus
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 9. LISTER LES DÉBITEURS
     */
    #[OA\Get(
        path: "/api/tontines/{id}/debtors",
        summary: "Lister les membres en retard de paiement",
        security: [["sanctum" => []]],
        tags: ["Tontines"],
        responses: [new OA\Response(response: 200, description: "Succès")]
    )]
    public function getDebtors(int $id): JsonResponse
    {
        $tontine = Tontine::with(['users', 'payments', 'penalties'])->findOrFail($id);
        $currentRound = $tontine->current_turn ?? 1;

        $debtors = $tontine->users->map(function ($user) use ($tontine, $currentRound) {
            $unpaidRounds = [];
            for ($i = 1; $i <= $currentRound; $i++) {
                if (!$tontine->payments->where('user_id', $user->id)->where('round_number', $i)->exists()) {
                    $unpaidRounds[] = $i;
                }
            }

            if (empty($unpaidRounds)) return null;

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'missed_rounds' => $unpaidRounds,
                'total_debt' => count($unpaidRounds) * $tontine->amount,
                'unpaid_penalties' => $tontine->penalties->where('user_id', $user->id)->where('status', 'unpaid')->sum('amount')
            ];
        })->filter()->values();

        return response()->json(['success' => true, 'data' => $debtors]);
    }

    /**
     * 10. APPLIQUER UNE AMENDE
     */
    #[OA\Post(
        path: "/api/tontines/{id}/apply-penalty",
        summary: "Appliquer une amende manuelle à un membre",
        security: [["sanctum" => []]],
        tags: ["Tontines"],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: "user_id", type: "integer"),
            new OA\Property(property: "round_number", type: "integer"),
            new OA\Property(property: "amount", type: "number")
        ])),
        responses: [new OA\Response(response: 200, description: "Succès")]
    )]
   public function applyPenalty(Request $request, int $id): JsonResponse
{
    $tontine = Tontine::findOrFail($id);
    if (!$this->isAdmin($tontine, $request->user()->id)) return response()->json(['message' => 'Interdit'], 403);

    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
        'round_number' => 'required|integer',
        'amount' => 'nullable|numeric'
    ]);

    return DB::transaction(function () use ($tontine, $validated) {
        $penalty = Penalty::create([
            'tontine_id' => $tontine->id,
            'user_id' => $validated['user_id'],
            'round_number' => $validated['round_number'],
            'amount' => $validated['amount'] ?? $tontine->late_fee,
            'status' => 'unpaid'
        ]);

        $member = User::find($validated['user_id']);
        
        // ENREGISTREMENT AUTOMATIQUE
        $tontine->logActivity(
            'penalty', 
            "Amende de {$penalty->amount} appliquée à {$member->name} (Tour {$validated['round_number']}).",
            $member->id,
            $penalty->amount
        );

        return response()->json(['success' => true, 'data' => $penalty]);
    });
}

    /**
     * 11. PAYER UNE AMENDE
     */
    #[OA\Post(
        path: "/api/penalties/{penalty_id}/pay",
        summary: "Marquer une amende comme payée",
        security: [["sanctum" => []]],
        tags: ["Tontines"],
        responses: [new OA\Response(response: 200, description: "Succès")]
    )]
    public function payPenalty(int $penalty_id): JsonResponse
    {
        $penalty = Penalty::findOrFail($penalty_id);
        $penalty->update(['status' => 'paid']);
        return response()->json(['message' => 'Amende réglée avec succès']);
    }

    /**
     * 12. SUPPRIMER TONTINE
     */
    #[OA\Delete(
        path: "/api/tontines/{id}",
        summary: "Supprimer définitivement le groupe",
        security: [["sanctum" => []]],
        tags: ["Tontines"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Succès")]
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tontine = Tontine::findOrFail($id);
        if (!$this->isAdmin($tontine, $request->user()->id)) return response()->json(['message' => 'Interdit'], 403);

        $tontine->users()->detach();
        $tontine->delete();
        return response()->json(['message' => 'Tontine supprimée']);
    }

    /**
     * 13. CLOTURER LE TOUR (CORRIGÉ POUR LA NOUVELLE LOGIQUE)
     */
    #[OA\Post(
        path: "/api/tontines/{id}/close-round",
        summary: "Clôturer le tour actuel et passer au bénéficiaire suivant",
        security: [["sanctum" => []]],
        tags: ["Tontines"]
    )]
   public function closeRound(Request $request, int $id): JsonResponse
{
    $tontine = Tontine::with('users')->findOrFail($id);
    
    if (!$this->isAdmin($tontine, $request->user()->id)) {
        return response()->json(['message' => 'Interdit'], 403);
    }

    $currentRound = (int)($tontine->current_turn ?? 1);

    return DB::transaction(function () use ($tontine, $currentRound) {
        $nextTurn = $currentRound + 1;
        
        // 1. Récupérer le bénéficiaire du tour actuel pour l'historique
        $currentBeneficiary = $tontine->users()
            ->wherePivot('turn_order', $currentRound)
            ->first();

        if ($nextTurn > $tontine->users->count()) {
            $tontine->update(['status' => 'completed']);

            // LOG : Fin de la tontine
            $tontine->logActivity(
                'info', 
                "Le dernier tour ($currentRound) est terminé. La tontine est désormais clôturée."
            );

            return response()->json([
                'success' => true, 
                'message' => 'Tontine terminée !', 
                'status' => 'completed'
            ]);
        }

        // 2. Mise à jour du tour
        $tontine->update(['current_turn' => $nextTurn]);

        // 3. ENREGISTREMENT AUTOMATIQUE DANS L'HISTORIQUE
        $beneficiaryName = $currentBeneficiary ? $currentBeneficiary->name : "Inconnu";
        
        $tontine->logActivity(
            'info', 
            "Tour $currentRound clôturé (Bénéficiaire: $beneficiaryName). Passage au tour $nextTurn.",
            $currentBeneficiary ? $currentBeneficiary->id : null
        );

        return response()->json([
            'success' => true, 
            'message' => 'Tour suivant activé !',
            'data' => ['next_round' => $nextTurn]
        ]);
    });
}

    /**
 * 14. RÉCUPÉRER L'HISTORIQUE COMPLET D'UNE TONTINE
 */
public function getHistory(int $id): JsonResponse
{
    // On vérifie que la tontine existe
    $tontine = Tontine::findOrFail($id);

    // On récupère l'historique trié du plus récent au plus ancien
    // On inclut les infos de l'utilisateur (id et nom) lié à l'action
    $history = $tontine->histories()
        ->with('user:id,name') 
        ->latest()
        ->get();

    return response()->json([
        'success' => true,
        'data' => $history
    ]);
}



   

}