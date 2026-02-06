<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    /**
     * Récupère la liste unique des membres appartenant aux mêmes tontines que l'utilisateur connecté.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        // 1. On récupère les IDs de toutes les tontines où l'utilisateur est inscrit
        $myTontineIds = $user->tontines()->pluck('tontines.id');

        // 2. On cherche tous les utilisateurs qui sont dans ces tontines
        $contacts = User::whereHas('tontines', function ($query) use ($myTontineIds) {
            $query->whereIn('tontines.id', $myTontineIds);
        })
        ->where('id', '!=', $user->id) // On s'exclut soi-même de la liste
        ->distinct() // Pour éviter les doublons si on partage plusieurs tontines avec la même personne
        ->orderBy('name', 'asc')
        ->get(['id', 'name', 'phone']); // On ne récupère que les infos utiles

        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }
}