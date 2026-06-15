<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\Modalidad;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Models\Agenda;
use App\Models\Booking;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaPausaEntreSesionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pausa_bloquea_turnos_demasiado_cercanos_a_una_reserva(): void
    {
        $tz = config('app.timezone', 'UTC');
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00', $tz));

        $prof = User::factory()->create(['role' => UserRole::Professional]);
        $perfil = ProfessionalProfile::create([
            'user_id' => $prof->id,
            'titulo' => 'Profesional',
        ]);

        Agenda::create([
            'professional_profile_id' => $perfil->id,
            'horario_inicio' => '09:00:00',
            'horario_fin' => '18:00:00',
            'dias_disponibles' => [Carbon::parse('2026-06-10')->dayOfWeek],
            'buffer_minutos' => 0,
            'pausa_entre_sesiones_minutos' => 30,
        ]);

        $servicio = Service::create([
            'professional_profile_id' => $perfil->id,
            'type' => ServiceType::Session,
            'nombre' => 'Consulta',
            'duracion' => 60,
            'precio' => 1000,
            'modalidad' => Modalidad::Virtual,
        ]);

        $cliente = User::factory()->create(['role' => UserRole::Client]);

        Booking::create([
            'client_user_id' => $cliente->id,
            'professional_profile_id' => $perfil->id,
            'service_id' => $servicio->id,
            'fecha_hora' => Carbon::parse('2026-06-10 10:00:00', $tz),
            'modalidad' => Modalidad::Virtual,
            'estado' => BookingStatus::Confirmada,
        ]);

        $response = $this->getJson("/api/professionals/{$perfil->id}/availability?" . http_build_query([
            'service_id' => $servicio->id,
            'from' => '2026-06-10',
            'to' => '2026-06-10',
        ]));

        $response->assertOk();
        $slots = collect($response->json('slots'));

        $slot11 = $slots->first(fn (array $s) => str_contains($s['start'], 'T11:00'));
        $this->assertNotNull($slot11);
        $this->assertFalse($slot11['available'], '11:00 no debería estar libre: sesión 10-11 + 30 min de pausa.');

        $slot1130 = $slots->first(fn (array $s) => str_contains($s['start'], 'T12:00'));
        $this->assertNotNull($slot1130);
        $this->assertTrue($slot1130['available'], '12:00 debería estar libre tras la pausa (sesión 10-11 + 30 min).');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
