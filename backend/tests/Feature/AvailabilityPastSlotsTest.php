<?php

namespace Tests\Feature;

use App\Enums\Modalidad;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Models\Agenda;
use App\Models\Location;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityPastSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_ofrece_slots_que_ya_pasaron_el_mismo_dia(): void
    {
        $tz = config('app.timezone', 'UTC');
        Carbon::setTestNow(Carbon::parse('2026-06-04 20:00:00', $tz));

        $ubicacion = Location::create([
            'ciudad' => 'Montevideo',
            'pais' => 'UY',
            'latitud' => -34.901112,
            'longitud' => -56.164531,
        ]);

        $perfil = ProfessionalProfile::create([
            'user_id' => User::factory()->create(['role' => UserRole::Professional])->id,
            'titulo' => 'Coach',
            'location_id' => $ubicacion->id,
        ]);

        Agenda::create([
            'professional_profile_id' => $perfil->id,
            'horario_inicio' => '09:00:00',
            'horario_fin' => '22:00:00',
            'dias_disponibles' => [Carbon::now()->dayOfWeek],
            'buffer_minutos' => 0,
        ]);

        $servicio = Service::create([
            'professional_profile_id' => $perfil->id,
            'type' => ServiceType::Session,
            'nombre' => 'Sesión',
            'duracion' => 60,
            'precio' => 1000,
            'modalidad' => Modalidad::Virtual,
        ]);

        $hoy = Carbon::now()->toDateString();

        $response = $this->getJson("/api/professionals/{$perfil->id}/availability?" . http_build_query([
            'service_id' => $servicio->id,
            'from' => $hoy,
            'to' => $hoy,
        ]));

        $response->assertOk();
        $slots = collect($response->json('slots'));

        $this->assertTrue(
            $slots->every(fn (array $s) => Carbon::parse($s['start'])->gte(Carbon::now())),
            'Ningún slot debería empezar en el pasado.',
        );

        $slot16 = $slots->first(fn (array $s) => str_contains($s['start'], 'T16:') || str_contains($s['start'], ' 16:'));
        $this->assertNull($slot16, 'No debería listarse un turno de las 16:00 si ya son las 20:00.');

        $slot21 = $slots->first(fn (array $s) => str_contains($s['start'], 'T21:') || str_contains($s['start'], ' 21:'));
        $this->assertNotNull($slot21, 'Debería existir un turno futuro (21:00).');
        $this->assertTrue($slot21['available']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
