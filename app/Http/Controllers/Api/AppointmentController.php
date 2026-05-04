<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rdv;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rdvs = Rdv::query()
            ->where('idPatient', $request->user()->idPatient)
            ->where('dateHeureRdv', '>=', now())
            ->orderBy('dateHeureRdv')
            ->get();

        return response()->json($rdvs->map(fn (Rdv $rdv) => $this->formatRdv($rdv)));
    }

    public function unavailableSlots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idMedecin' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date_format:Y-m-d'],
            'excludeRdvId' => ['nullable', 'integer'],
        ]);

        $query = Rdv::query()
            ->where('idMedecin', $validated['idMedecin'])
            ->whereDate('dateHeureRdv', $validated['date'])
            ->orderBy('dateHeureRdv');

        if (! empty($validated['excludeRdvId'])) {
            $query->where('idRdv', '!=', $validated['excludeRdvId']);
        }

        $slots = $query->get()->map(
            fn (Rdv $rdv) => $rdv->dateHeureRdv->format('H:i')
        )->values();

        return response()->json([
            'date' => $validated['date'],
            'idMedecin' => $validated['idMedecin'],
            'slots' => $slots,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dateHeureRdv' => ['required', 'date'],
            'nomMedecin' => ['required', 'string', 'max:100'],
            'prenomMedecin' => ['required', 'string', 'max:100'],
            'idMedecin' => ['required', 'string', 'max:100'],
        ]);

        $slot = $this->validatedSlot($validated['dateHeureRdv']);
        $this->ensureSlotIsAvailable($slot, $validated['nomMedecin'], $validated['prenomMedecin']);

        $rdv = Rdv::query()->create([
            'dateHeureRdv' => $slot->format('Y-m-d H:i:s'),
            'idPatient' => $request->user()->idPatient,
            'nomMedecin' => $validated['nomMedecin'],
            'prenomMedecin' => $validated['prenomMedecin'],
            'idMedecin' => $validated['idMedecin'],
        ]);

        return response()->json([
            'message' => 'Rendez-vous cree.',
            'rdv' => $this->formatRdv($rdv),
        ], JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, int $appointment): JsonResponse
    {
        $rdv = $this->ownedFutureRdv($request, $appointment);

        $validated = $request->validate([
            'dateHeureRdv' => ['required', 'date'],
            'nomMedecin' => ['required', 'string', 'max:100'],
            'prenomMedecin' => ['required', 'string', 'max:100'],
            'idMedecin' => ['required', 'string', 'max:100'],
        ]);

        $slot = $this->validatedSlot($validated['dateHeureRdv']);
        $this->ensureSlotIsAvailable($slot, $validated['nomMedecin'], $validated['prenomMedecin'], $rdv->idRdv);

        $rdv->update([
            'dateHeureRdv' => $slot->format('Y-m-d H:i:s'),
            'nomMedecin' => $validated['nomMedecin'],
            'prenomMedecin' => $validated['prenomMedecin'],
            'idMedecin' => $validated['idMedecin'],
        ]);

        return response()->json([
            'message' => 'Rendez-vous modifie.',
            'rdv' => $this->formatRdv($rdv->fresh()),
        ]);
    }

    public function destroy(Request $request, int $appointment): JsonResponse
    {
        $rdv = $this->ownedFutureRdv($request, $appointment);
        $payload = $this->formatRdv($rdv);
        $rdv->delete();

        return response()->json([
            'message' => 'Rendez-vous annule.',
            'rdv' => $payload,
        ]);
    }

    private function validatedSlot(string $dateHeureRdv): CarbonImmutable
    {
        $slot = CarbonImmutable::parse($dateHeureRdv)->seconds(0);
        $minutesFromMidnight = ($slot->hour * 60) + $slot->minute;

        if ($slot->lessThanOrEqualTo(now())) {
            abort(response()->json([
                'message' => 'Le rendez-vous doit etre programme dans le futur.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY));
        }

        if ($slot->minute % 20 !== 0) {
            abort(response()->json([
                'message' => 'Les rendez-vous doivent etre pris sur des creneaux de 20 minutes.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY));
        }

        if ($minutesFromMidnight < 8 * 60 || $minutesFromMidnight > 19 * 60) {
            abort(response()->json([
                'message' => 'Les rendez-vous doivent etre pris entre 08:00 et 19:00.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $slot;
    }

    private function ensureSlotIsAvailable(CarbonImmutable $slot, string $nomMedecin, string $prenomMedecin, ?int $exceptId = null): void
    {
        $query = Rdv::query()
            ->where('dateHeureRdv', $slot->format('Y-m-d H:i:s'))
            ->where('nomMedecin', $nomMedecin)
            ->where('prenomMedecin', $prenomMedecin);

        if ($exceptId !== null) {
            $query->where('idRdv', '!=', $exceptId);
        }

        if ($query->exists()) {
            abort(response()->json([
                'message' => 'Ce creneau est deja reserve pour ce medecin.',
            ], JsonResponse::HTTP_CONFLICT));
        }
    }

    private function ownedFutureRdv(Request $request, int $appointmentId): Rdv
    {
        $rdv = Rdv::query()
            ->where('idRdv', $appointmentId)
            ->where('idPatient', $request->user()->idPatient)
            ->firstOrFail();

        if ($rdv->dateHeureRdv->lessThan(now())) {
            abort(response()->json([
                'message' => 'Impossible de modifier ou annuler un rendez-vous passe.',
            ], JsonResponse::HTTP_FORBIDDEN));
        }

        return $rdv;
    }

    private function formatRdv(Rdv $rdv): array
    {
        return [
            'idRdv' => $rdv->idRdv,
            'dateHeureRdv' => $rdv->dateHeureRdv->format('Y-m-d H:i:s'),
            'idPatient' => $rdv->idPatient,
            'nomMedecin' => $rdv->nomMedecin,
            'prenomMedecin' => $rdv->prenomMedecin,
            'idMedecin' => $rdv->idMedecin,
        ];
    }
}
