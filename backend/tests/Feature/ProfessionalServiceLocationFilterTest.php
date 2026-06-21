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

class ProfessionalServiceLocationFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_listado_y_filtro_ciudad_usan_ubicacion_del_servicio(): void
    {
        $montevideo = Location::create([
            'ciudad' => 'Montevideo',
            'pais' => 'UY',
            'latitud' => -34.901112,
            'longitud' => -56.164531,
        ]);

        $punta = Location::create([
            'ciudad' => 'Punta del Este',
            'pais' => 'UY',
            'latitud' => -34.961454,
            'longitud' => -54.943254,
        ]);

        $user = User::factory()->create(['role' => UserRole::Professional, 'nombre' => 'Ana']);
        $perfil = ProfessionalProfile::create([
            'user_id' => $user->id,
            'titulo' => 'Coach',
            'location_id' => $montevideo->id,
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
            'nombre' => 'Sesión en Punta',
            'duracion' => 60,
            'precio' => 2000,
            'modalidad' => Modalidad::Presencial,
            'location_id' => $punta->id,
            'activo' => true,
        ]);

        $this->getJson('/api/professionals?ciudad=Punta')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.ubicacion.ciudad', 'Punta del Este');

        $this->getJson('/api/professionals')
            ->assertOk()
            ->assertJsonPath('data.0.ubicacion.ciudad', 'Punta del Este');
    }
}
