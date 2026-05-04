<?php

namespace Tests\Feature\Api;

use App\Models\Authentification;
use App\Models\Patient;
use App\Models\Rdv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_register_and_receive_a_token(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
            ->postJson('/api/register', [
                'nomPatient' => 'Dupont',
                'prenomPatient' => 'Alice',
                'loginPatient' => 'alice.dupont',
                'mdpPatient' => 'MotDePasse!2026',
            ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'token',
                'patient' => ['idPatient', 'nomPatient', 'prenomPatient', 'loginPatient'],
            ]);

        $this->assertDatabaseHas('patients', [
            'loginPatient' => 'alice.dupont',
            'nomPatient' => 'Dupont',
            'prenomPatient' => 'Alice',
        ]);

        $this->assertDatabaseHas('authentification', [
            'idPatient' => 1,
            'ipAppareil' => '192.168.1.50',
        ]);
    }

    public function test_registration_rejects_weak_passwords(): void
    {
        $response = $this->postJson('/api/register', [
            'nomPatient' => 'Dupont',
            'prenomPatient' => 'Alice',
            'loginPatient' => 'alice.dupont',
            'mdpPatient' => 'motdepassefaible',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mdpPatient']);
    }

    public function test_patient_can_login_and_token_is_bound_to_ip_address(): void
    {
        $patient = Patient::query()->create([
            'nomPatient' => 'Dupont',
            'prenomPatient' => 'Alice',
            'loginPatient' => 'alice.dupont',
            'mdpPatient' => password_hash('MotDePasse!2026', PASSWORD_BCRYPT),
        ]);

        $login = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/login', [
                'loginPatient' => 'alice.dupont',
                'mdpPatient' => 'MotDePasse!2026',
            ]);

        $token = $login->json('token');

        $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('patient.idPatient', $patient->idPatient);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/profile')
            ->assertUnauthorized();
    }

    public function test_authenticated_patient_can_see_unavailable_slots_for_a_doctor(): void
    {
        $this->createPatientWithToken('plain-token');
        $otherPatient = Patient::query()->create([
            'nomPatient' => 'Martin',
            'prenomPatient' => 'Leo',
            'loginPatient' => 'leo.martin',
            'mdpPatient' => password_hash('MotDePasse!2026', PASSWORD_BCRYPT),
        ]);

        Rdv::query()->create([
            'dateHeureRdv' => '2030-06-15 10:20:00',
            'idPatient' => $otherPatient->idPatient,
            'nomMedecin' => 'Martin',
            'prenomMedecin' => 'Jean',
            'idMedecin' => 'doc-2',
        ]);

        $this
            ->withHeader('Authorization', 'Bearer plain-token')
            ->getJson('/api/rdv/unavailable-slots?idMedecin=doc-2&date=2030-06-15')
            ->assertOk()
            ->assertJsonPath('idMedecin', 'doc-2')
            ->assertJsonPath('date', '2030-06-15')
            ->assertJsonPath('slots.0', '10:20');
    }

    public function test_authenticated_patient_can_create_and_list_upcoming_rdv(): void
    {
        $patient = $this->createPatientWithToken('plain-token');

        Rdv::query()->create([
            'dateHeureRdv' => now()->subHour(),
            'idPatient' => $patient->idPatient,
            'nomMedecin' => 'Martin',
            'prenomMedecin' => 'Jean',
            'idMedecin' => 'doc-past',
        ]);

        $createResponse = $this
            ->withHeader('Authorization', 'Bearer plain-token')
            ->postJson('/api/rdv', [
                'dateHeureRdv' => now()->addDays(2)->setMinute(40)->setSecond(0)->format('Y-m-d H:i:s'),
                'nomMedecin' => 'Martin',
                'prenomMedecin' => 'Jean',
                'idMedecin' => 'doc-1',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('rdv.nomMedecin', 'Martin')
            ->assertJsonPath('rdv.prenomMedecin', 'Jean')
            ->assertJsonPath('rdv.idMedecin', 'doc-1');

        $this
            ->withHeader('Authorization', 'Bearer plain-token')
            ->getJson('/api/rdv')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nomMedecin', 'Martin')
            ->assertJsonPath('0.dateHeureRdv', now()->addDays(2)->setMinute(40)->setSecond(0)->format('Y-m-d H:i:s'));
    }

    public function test_patient_cannot_book_an_already_reserved_slot(): void
    {
        $this->createPatientWithToken('plain-token');
        $otherPatient = Patient::query()->create([
            'nomPatient' => 'Martin',
            'prenomPatient' => 'Leo',
            'loginPatient' => 'leo.martin',
            'mdpPatient' => password_hash('MotDePasse!2026', PASSWORD_BCRYPT),
        ]);

        $slot = now()->addDays(1)->setMinute(0)->setSecond(0)->format('Y-m-d H:i:s');

        Rdv::query()->create([
            'dateHeureRdv' => $slot,
            'idPatient' => $otherPatient->idPatient,
            'nomMedecin' => 'Martin',
            'prenomMedecin' => 'Jean',
            'idMedecin' => 'doc-2',
        ]);

        $this
            ->withHeader('Authorization', 'Bearer plain-token')
            ->postJson('/api/rdv', [
                'dateHeureRdv' => $slot,
                'nomMedecin' => 'Martin',
                'prenomMedecin' => 'Jean',
                'idMedecin' => 'doc-2',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ce creneau est deja reserve pour ce medecin.');
    }

    public function test_patient_can_update_and_cancel_future_rdv(): void
    {
        $patient = $this->createPatientWithToken('plain-token');

        $rdv = Rdv::query()->create([
            'dateHeureRdv' => now()->addDay()->setMinute(0)->setSecond(0),
            'idPatient' => $patient->idPatient,
            'nomMedecin' => 'Martin',
            'prenomMedecin' => 'Jean',
            'idMedecin' => 'doc-1',
        ]);

        $this
            ->withHeader('Authorization', 'Bearer plain-token')
            ->putJson('/api/rdv/'.$rdv->idRdv, [
                'dateHeureRdv' => now()->addDays(3)->setMinute(20)->setSecond(0)->format('Y-m-d H:i:s'),
                'nomMedecin' => 'Bernard',
                'prenomMedecin' => 'Julie',
                'idMedecin' => 'doc-3',
            ])
            ->assertOk()
            ->assertJsonPath('rdv.nomMedecin', 'Bernard')
            ->assertJsonPath('rdv.idMedecin', 'doc-3');

        $this
            ->withHeader('Authorization', 'Bearer plain-token')
            ->deleteJson('/api/rdv/'.$rdv->idRdv)
            ->assertOk();

        $this->assertDatabaseMissing('rdv', [
            'idRdv' => $rdv->idRdv,
        ]);
    }

    public function test_patient_cannot_modify_or_cancel_past_rdv(): void
    {
        $patient = $this->createPatientWithToken('plain-token');

        $rdv = Rdv::query()->create([
            'dateHeureRdv' => now()->subDay(),
            'idPatient' => $patient->idPatient,
            'nomMedecin' => 'Martin',
            'prenomMedecin' => 'Jean',
            'idMedecin' => 'doc-1',
        ]);

        $this
            ->withHeader('Authorization', 'Bearer plain-token')
            ->putJson('/api/rdv/'.$rdv->idRdv, [
                'dateHeureRdv' => now()->addDays(2)->setMinute(0)->setSecond(0)->format('Y-m-d H:i:s'),
                'nomMedecin' => 'Martin',
                'prenomMedecin' => 'Jean',
                'idMedecin' => 'doc-1',
            ])
            ->assertForbidden();

        $this
            ->withHeader('Authorization', 'Bearer plain-token')
            ->deleteJson('/api/rdv/'.$rdv->idRdv)
            ->assertForbidden();
    }

    public function test_rdv_must_use_20_minute_slots(): void
    {
        $this->createPatientWithToken('plain-token');

        $this
            ->withHeader('Authorization', 'Bearer plain-token')
            ->postJson('/api/rdv', [
                'dateHeureRdv' => now()->addDay()->setMinute(15)->setSecond(0)->format('Y-m-d H:i:s'),
                'nomMedecin' => 'Martin',
                'prenomMedecin' => 'Jean',
                'idMedecin' => 'doc-1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Les rendez-vous doivent etre pris sur des creneaux de 20 minutes.');
    }

    public function test_rdv_must_stay_within_opening_hours(): void
    {
        $this->createPatientWithToken('plain-token');

        $this
            ->withHeader('Authorization', 'Bearer plain-token')
            ->postJson('/api/rdv', [
                'dateHeureRdv' => now()->addDay()->setTime(19, 20)->setSecond(0)->format('Y-m-d H:i:s'),
                'nomMedecin' => 'Martin',
                'prenomMedecin' => 'Jean',
                'idMedecin' => 'doc-1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Les rendez-vous doivent etre pris entre 08:00 et 19:00.');
    }

    private function createPatientWithToken(string $token): Patient
    {
        $patient = Patient::query()->create([
            'nomPatient' => 'Dupont',
            'prenomPatient' => 'Alice',
            'loginPatient' => 'alice.dupont',
            'mdpPatient' => password_hash('MotDePasse!2026', PASSWORD_BCRYPT),
        ]);

        Authentification::query()->create([
            'token' => $token,
            'idPatient' => $patient->idPatient,
            'ipAppareil' => '127.0.0.1',
        ]);

        return $patient;
    }
}
