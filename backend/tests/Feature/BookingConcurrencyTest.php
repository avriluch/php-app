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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Documenta y verifica la regla del electivo de concurrencia:
 * dos reservas al mismo slot del mismo profesional → la segunda recibe 422.
 *
 * La protección viene de tres capas que conviven en BookingController::store:
 *   1. DB::transaction()
 *   2. Service::lockForUpdate() (y PackagePurchase::lockForUpdate())
 *   3. AvailabilityService::isSlotFree() corriendo dentro de la transacción,
 *      con UNIQUE(professional_profile_id, fecha_hora) como red final en la BD.
 *
 * SQLite in-memory no simula concurrencia real, pero el test ejecuta el camino
 * secuencial completo y deja la regla blindada contra regresiones.
 */
class BookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dos_reservas_al_mismo_slot_la_segunda_recibe_422(): void
    {
        // El job de confirmación dispatchea email + websocket; en tests no
        // queremos conexión real a Redis ni a Reverb.
        Queue::fake();
        Event::fake();

        $ubicacion = Location::create([
            'ciudad' => 'Montevideo',
            'pais' => 'UY',
            'latitud' => -34.901112,
            'longitud' => -56.164531,
        ]);

        $profesionalUser = User::factory()->create(['role' => UserRole::Professional]);

        $perfil = ProfessionalProfile::create([
            'user_id' => $profesionalUser->id,
            'titulo' => 'Nutricionista',
            'location_id' => $ubicacion->id,
            'cancelacion_horas_minimas' => 24,
        ]);

        Agenda::create([
            'professional_profile_id' => $perfil->id,
            'horario_inicio' => '09:00:00',
            'horario_fin' => '18:00:00',
            'dias_disponibles' => [1, 2, 3, 4, 5],
            'buffer_minutos' => 0,
        ]);

        $servicio = Service::create([
            'professional_profile_id' => $perfil->id,
            'type' => ServiceType::Session,
            'nombre' => 'Consulta',
            'duracion' => 60,
            'precio' => 1500,
            'modalidad' => Modalidad::Virtual,
        ]);

        $cliente1 = User::factory()->create(['role' => UserRole::Client]);
        $cliente2 = User::factory()->create(['role' => UserRole::Client]);

        // Próximo lunes a las 10:00: siempre futuro y dentro del horario.
        $slot = Carbon::now()->startOfWeek()->addWeek()->setTime(10, 0)->toIso8601String();

        $payload = [
            'service_id' => $servicio->id,
            'professional_id' => $perfil->id,
            'fecha_hora' => $slot,
            'modalidad' => 'virtual',
        ];

        Sanctum::actingAs($cliente1);
        $this->postJson('/api/bookings', $payload)
            ->assertStatus(201);

        Sanctum::actingAs($cliente2);
        $this->postJson('/api/bookings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_hora']);
    }
}
