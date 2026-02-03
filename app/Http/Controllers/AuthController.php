<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /**
     * INSCRIPTION
     */
    #[OA\Post(
        path: "/api/auth/register",
        summary: "Inscription d'un nouvel utilisateur",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "phone", "password"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Elom"),
                    new OA\Property(property: "phone", type: "string", example: "+22890123456"),
                    new OA\Property(property: "password", type: "string", example: "12345678")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200, 
                description: "Utilisateur créé avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", type: "object"),
                        new OA\Property(property: "token", type: "string", example: "1|abcdefpqrst...")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Erreur de validation (ex: téléphone déjà utilisé)")
        ]
    )]
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'unique:users,phone', 'regex:/^\+\d{8,15}$/'],
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'country_code' => '+228',
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * CONNEXION
     */
    #[OA\Post(
        path: "/api/auth/login",
        summary: "Connexion utilisateur",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone", "password"],
                properties: [
                    new OA\Property(property: "phone", type: "string", example: "+22890123456"),
                    new OA\Property(property: "password", type: "string", example: "12345678")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200, 
                description: "Connexion réussie",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", type: "object"),
                        new OA\Property(property: "token", type: "string")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Identifiants incorrects")
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'regex:/^\+\d{8,15}$/'],
            'password' => ['required'],
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Numéro ou mot de passe incorrect'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * DÉCONNEXION
     */
    #[OA\Post(
        path: "/api/auth/logout",
        summary: "Déconnexion (révoquer le token actuel)",
        security: [["sanctum" => []]], // Utilise bien la référence définie dans Controller.php
        tags: ["Auth"],
        responses: [
            new OA\Response(response: 200, description: "Déconnecté avec succès"),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnecté avec succès',
        ]);
    }

    /**
     * PROFIL
     */
    #[OA\Get(
        path: "/api/auth/profile",
        summary: "Récupérer les infos de l'utilisateur connecté",
        security: [["sanctum" => []]],
        tags: ["Auth"],
        responses: [
            new OA\Response(
                response: 200, 
                description: "Données du profil retournées",
                content: new OA\JsonContent(type: "object")
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )]
    public function profile(Request $request)
    {
        return response()->json($request->user());
    }
}