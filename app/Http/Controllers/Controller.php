<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Tontine API",
    version: "1.0.0",
    description: "Documentation de l'API Tontine - Backend",
    contact: new OA\Contact(email: "admin@tontine.com")
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST, // Utilisation de la constante de votre config
    description: "Serveur API Principal"
)]
#[OA\SecurityScheme(
    securityScheme: "sanctum", // Le nom de référence pour vos routes
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Entrez votre jeton (token) reçu lors de la connexion"
)]
abstract class Controller
{
    //
}