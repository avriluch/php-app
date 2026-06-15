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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalProximityFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_profesionales_dentro_del_radio_km(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('El filtro por cercanía usa funciones trigonométricas de MySQL.');
        }

        $montevideo = Location::create([
            'ciudad' => 'Montevideo',
            'pais' => 'UY',
            'latitud' => -34.901112,
            'longitud' => -56.164531,
        ]);

        $colonia = Location::create([
            'ciudad' => 'Colonia del Sacramento',
            'pais' => 'UY',
            'latitud' => -34.471550,
            'longitud' => -57.844322,
        ]);

        $cerca = $this->crearProfesionalConUbicacion($montevideo, 'Cerca');
        $lejos = $this->crearProfesionalConUbicacion($colonia, 'Lejos');

        $response = $this->getJson('/api/professionals?' . http_build_query([
            'lat' => -34.901112,
            'lng' => -56.164531,
            'radius_km' => 15,
        ]));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $cerca->id)
            ->assertJsonPath('data.0.distance_km', fn ($v) => $v !== null && $v <= 15);

        $this->assertNotContains($lejos->id, collect($response->json('data'))->pluck('id'));
    }

    public function test_orden_por_cercania_requiere_coordenadas(): void
    {
        $this->getJson('/api/professionals?sort=distance')
            ->assertStatus(422);
    }

    private function crearProfesionalConUbicacion(Location $ubicacion, string $nombre): ProfessionalProfile
    {
        $user = User::factory()->create([
            'role' => UserRole::Professional,
            'nombre' => $nombre,
        ]);

        $perfil = ProfessionalProfile::create([
            'user_id' => $user->id,
            'titulo' => "Profesional {$nombre}",
            'location_id' => $ubicacion->id,
        ]);

        Agenda::create([
            'professional_profile_id' => $perfil->id,
            'horario_inicio' => '09:00:00',
            'horario_fin' => '18:00:00',
            'dias_disponibles' => [1, 2, 3, 4, 5],
            'buffer_minutos' => 0,
        ]);

        Service::create([
            'professional_profile_id' => $perfil->id,
            'type' => ServiceType::Session,
            'nombre' => 'Consulta',
            'duracion' => 60,
            'precio' => 1000,
            'modalidad' => Modalidad::Virtual,
        ]);

        return $perfil;
    }
}
