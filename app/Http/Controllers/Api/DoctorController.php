<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class DoctorController extends Controller
{
    public function index(): JsonResponse
    {
        $response = Http::timeout(15)->acceptJson()->get(
            'https://data.issy.com/api/explore/v2.1/catalog/datasets/medecins-generalistes-et-infirmiers/records',
            [
                'limit' => 100,
                'where' => 'specialite = "MEDECIN GENERALISTE"',
            ]
        );

        if (! $response->successful()) {
            return response()->json([
                'message' => 'Impossible de recuperer la liste des medecins pour le moment.',
            ], JsonResponse::HTTP_BAD_GATEWAY);
        }

        $results = collect($response->json('results', []))
            ->map(function (array $doctor) {
                $position = $doctor['geolocalisation'] ?? $doctor['geo_point_2d'] ?? null;
                $nom = trim((string) ($doctor['nom'] ?? ''));
                $prenom = trim((string) ($doctor['prenom'] ?? ''));
                $recordId = (string) ($doctor['recordid'] ?? '');

                if (! is_array($position) || $nom === '' || $recordId === '' || ! isset($position['lat'], $position['lon'])) {
                    return null;
                }

                return [
                    'idMedecin' => $recordId,
                    'nomMedecin' => $nom,
                    'prenomMedecin' => $prenom,
                    'adresse' => $doctor['adresse'] ?? null,
                    'specialite' => $doctor['specialite'] ?? null,
                    'latitude' => $position['lat'],
                    'longitude' => $position['lon'],
                ];
            })
            ->filter()
            ->values();

        return response()->json($results->all());
    }
}
