<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Authentification;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomPatient' => ['required', 'string', 'max:100'],
            'prenomPatient' => ['required', 'string', 'max:100'],
            'ruePatient' => ['nullable', 'string', 'max:255'],
            'cpPatient' => ['nullable', 'string', 'max:50'],
            'villePatient' => ['nullable', 'string', 'max:100'],
            'telPatient' => ['nullable', 'string', 'max:50'],
            'loginPatient' => ['required', 'string', 'max:50', 'unique:patients,loginPatient'],
            'mdpPatient' => ['required', 'string'],
        ]);

        $this->ensureStrongPassword($validated['mdpPatient']);

        $result = DB::transaction(function () use ($validated, $request) {
            $patient = Patient::query()->create([
                'nomPatient' => $validated['nomPatient'],
                'prenomPatient' => $validated['prenomPatient'],
                'ruePatient' => $validated['ruePatient'] ?? null,
                'cpPatient' => $validated['cpPatient'] ?? null,
                'villePatient' => $validated['villePatient'] ?? null,
                'telPatient' => $validated['telPatient'] ?? null,
                'loginPatient' => $validated['loginPatient'],
                'mdpPatient' => password_hash($validated['mdpPatient'], PASSWORD_BCRYPT),
            ]);

            $token = $this->issueToken($patient, $request->ip());

            return [$patient, $token];
        });

        return response()->json([
            'message' => 'Inscription reussie.',
            'token' => $result[1],
            'patient' => $this->patientPayload($result[0]),
        ], JsonResponse::HTTP_CREATED);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loginPatient' => ['required', 'string'],
            'mdpPatient' => ['required', 'string'],
        ]);

        $patient = Patient::query()
            ->where('loginPatient', $validated['loginPatient'])
            ->first();

        if (! $patient || ! password_verify($validated['mdpPatient'], $patient->mdpPatient)) {
            throw ValidationException::withMessages([
                'loginPatient' => 'Identifiants invalides.',
            ]);
        }

        $token = $this->issueToken($patient, $request->ip());

        return response()->json([
            'message' => 'Connexion reussie.',
            'token' => $token,
            'patient' => $this->patientPayload($patient),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'patient' => $this->patientPayload($request->user()),
        ]);
    }

    private function ensureStrongPassword(string $password): void
    {
        $matches = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{13,}$/', $password) === 1;

        if (! $matches) {
            throw ValidationException::withMessages([
                'mdpPatient' => 'Le mot de passe doit contenir au moins 13 caracteres avec minuscule, majuscule, chiffre et caractere special.',
            ]);
        }
    }

    private function issueToken(Patient $patient, string $ipAddress): string
    {
        $ipAddress = $this->normalizeIpAddress($ipAddress);

        Authentification::query()
            ->where('idPatient', $patient->idPatient)
            ->where('ipAppareil', $ipAddress)
            ->delete();

        $token = hash('sha256', $patient->idPatient.'|'.$ipAddress.'|'.microtime(true).'|'.bin2hex(random_bytes(20)));

        Authentification::query()->create([
            'token' => $token,
            'idPatient' => $patient->idPatient,
            'ipAppareil' => $ipAddress,
        ]);

        return $token;
    }

    private function patientPayload(Patient $patient): array
    {
        return [
            'idPatient' => $patient->idPatient,
            'nomPatient' => $patient->nomPatient,
            'prenomPatient' => $patient->prenomPatient,
            'ruePatient' => $patient->ruePatient,
            'cpPatient' => $patient->cpPatient,
            'villePatient' => $patient->villePatient,
            'telPatient' => $patient->telPatient,
            'loginPatient' => $patient->loginPatient,
        ];
    }

    private function normalizeIpAddress(string $ipAddress): string
    {
        return $ipAddress === '::1' ? '127.0.0.1' : $ipAddress;
    }
}
