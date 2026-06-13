<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\Modalidad;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Models\Agenda;
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
 * Test end-to-end de los flujos centrales de la plataforma, pegando contra
 * la API real (rutas + middleware + validación + servicios + DB).
 */
class EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Crea profesional + perfil + agenda (Lun-Vie 09-18). @return array{0:User,1:ProfessionalProfile} */
    private function crearProfesional(): array
    {
        $user = User::factory()->create(['role' => UserRole::Professional]);

        $perfil = ProfessionalProfile::create([
            'user_id' => $user->id,
            'titulo' => 'Profesional de prueba',
            'cancelacion_horas_minimas' => 24,
        ]);

        Agenda::create([
            'professional_profile_id' => $perfil->id,
            'horario_inicio' => '09:00:00',
            'horario_fin' => '18:00:00',
            'dias_disponibles' => [1, 2, 3, 4, 5],
            'buffer_minutos' => 0,
        ]);

        return [$user, $perfil];
    }

    private function proximoLunes(int $hora): string
    {
        return Carbon::now()->startOfWeek()->addWeek()->setTime($hora, 0)->toIso8601String();
    }

    public function test_ciclo_completo_de_paquete(): void
    {
        Queue::fake();
        Event::fake();

        [$profUser, $perfil] = $this->crearProfesional();

        // 1) El profesional crea un servicio tipo PAQUETE vía API.
        Sanctum::actingAs($profUser);
        $resp = $this->postJson('/api/professional/services', [
            'type' => ServiceType::Package->value,
            'nombre' => 'Pack 4 sesiones',
            'precio' => 4000,
            'modalidad' => Modalidad::Virtual->value,
            'duracion' => 45,
            'cantidad_sesiones' => 4,
        ])->assertStatus(201);

        $serviceId = $resp->json('id');

        // 2) El cliente compra el paquete.
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($cliente);

        $compra = $this->postJson('/api/package-purchases', ['service_id' => $serviceId])
            ->assertStatus(201)
            ->assertJsonPath('sesiones_restantes', 4)
            ->json();

        $purchaseId = $compra['id'];

        // Se creó un pago pendiente asociado al paquete.
        $this->assertDatabaseHas('payments', [
            'package_purchase_id' => $purchaseId,
            'estado' => 'pendiente',
        ]);

        // 3) El cliente reserva una sesión consumiendo el paquete.
        $reserva = $this->postJson('/api/bookings', [
            'service_id' => $serviceId,
            'professional_id' => $perfil->id,
            'fecha_hora' => $this->proximoLunes(10),
            'modalidad' => 'virtual',
            'package_purchase_id' => $purchaseId,
        ])->assertStatus(201)->json();

        $bookingId = $reserva['id'];

        // 4) Se descontó una sesión (4 → 3).
        $this->assertDatabaseHas('package_purchases', [
            'id' => $purchaseId,
            'sesiones_restantes' => 3,
        ]);

        // 5) La reserva de paquete NO genera un pago propio (el cobro fue al comprar).
        $this->assertDatabaseMissing('payments', ['booking_id' => $bookingId]);

        // 6) Al cancelar, se reintegra la sesión (3 → 4).
        $this->patchJson("/api/bookings/{$bookingId}/cancel", ['motivo' => 'test'])
            ->assertStatus(200);

        $this->assertDatabaseHas('package_purchases', [
            'id' => $purchaseId,
            'sesiones_restantes' => 4,
        ]);
    }

    public function test_no_permite_reservar_paquete_sin_sesiones(): void
    {
        Queue::fake();
        Event::fake();

        [$profUser, $perfil] = $this->crearProfesional();

        $servicio = Service::create([
            'professional_profile_id' => $perfil->id,
            'type' => ServiceType::Package,
            'nombre' => 'Pack 1 sesión',
            'duracion' => 45,
            'precio' => 1000,
            'modalidad' => Modalidad::Virtual,
            'cantidad_sesiones' => 1,
        ]);

        $cliente = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($cliente);

        $purchaseId = $this->postJson('/api/package-purchases', ['service_id' => $servicio->id])
            ->assertStatus(201)->json('id');

        $payloadBase = [
            'service_id' => $servicio->id,
            'professional_id' => $perfil->id,
            'modalidad' => 'virtual',
            'package_purchase_id' => $purchaseId,
        ];

        // Primera reserva: consume la única sesión.
        $this->postJson('/api/bookings', [...$payloadBase, 'fecha_hora' => $this->proximoLunes(10)])
            ->assertStatus(201);

        // Segunda reserva (otro horario): ya no quedan sesiones → 422.
        $this->postJson('/api/bookings', [...$payloadBase, 'fecha_hora' => $this->proximoLunes(12)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['package_purchase_id']);
    }

    public function test_reserva_simple_ciclo_de_estados_y_metricas(): void
    {
        Queue::fake();
        Event::fake();

        [$profUser, $perfil] = $this->crearProfesional();

        $servicio = Service::create([
            'professional_profile_id' => $perfil->id,
            'type' => ServiceType::Session,
            'nombre' => 'Consulta',
            'duracion' => 60,
            'precio' => 1500,
            'modalidad' => Modalidad::Virtual,
        ]);

        $cliente = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($cliente);

        $bookingId = $this->postJson('/api/bookings', [
            'service_id' => $servicio->id,
            'professional_id' => $perfil->id,
            'fecha_hora' => $this->proximoLunes(11),
            'modalidad' => 'virtual',
        ])->assertStatus(201)->json('id');

        // La reserva suelta genera un pago pendiente.
        $this->assertDatabaseHas('payments', ['booking_id' => $bookingId, 'estado' => 'pendiente']);

        // El profesional avanza el ciclo de estados.
        Sanctum::actingAs($profUser);
        foreach (['confirmada', 'pagada', 'en_curso', 'finalizada'] as $estado) {
            $this->patchJson("/api/bookings/{$bookingId}/status", ['estado' => $estado])
                ->assertStatus(200)
                ->assertJsonPath('estado', $estado);
        }

        // Al entrar "en_curso" en modalidad virtual se generó la sala de videollamada.
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'estado' => BookingStatus::Finalizada->value,
            'url_video_llamada' => "booking-{$bookingId}",
        ]);

        // Transición inválida: finalizada → en_curso.
        $this->patchJson("/api/bookings/{$bookingId}/status", ['estado' => 'en_curso'])
            ->assertStatus(422);

        // Las métricas del profesional reflejan la actividad del mes.
        $this->getJson('/api/me/stats')
            ->assertStatus(200)
            ->assertJsonPath('reservas_mes', 1);
    }

    public function test_admin_suspende_usuario_y_bloquea_login(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $cliente = User::factory()->create(['role' => UserRole::Client]); // password = 'password'

        // El cliente puede loguearse normalmente.
        $this->postJson('/api/auth/login', ['email' => $cliente->email, 'password' => 'password'])
            ->assertStatus(200);

        // El admin lo suspende.
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/users/{$cliente->id}/status", ['activo' => false])
            ->assertStatus(200)
            ->assertJsonPath('activo', false);

        // Ahora el login queda bloqueado.
        $this->postJson('/api/auth/login', ['email' => $cliente->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // El admin no puede suspenderse a sí mismo.
        $this->patchJson("/api/admin/users/{$admin->id}/status", ['activo' => false])
            ->assertStatus(422);
    }

    public function test_admin_cambia_rol_a_profesional_crea_perfil(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/users/{$cliente->id}/role", ['role' => 'professional'])
            ->assertStatus(200)
            ->assertJsonPath('role', 'professional');

        $this->assertDatabaseHas('users', ['id' => $cliente->id, 'role' => 'professional']);
        $this->assertDatabaseHas('professional_profiles', ['user_id' => $cliente->id]);
    }

    public function test_historial_de_pagos_y_actualizacion_de_perfil_profesional(): void
    {
        Queue::fake();
        Event::fake();

        [$profUser, $perfil] = $this->crearProfesional();

        // El profesional actualiza su perfil/política de cancelación.
        Sanctum::actingAs($profUser);
        $this->putJson('/api/professional/profile', [
            'titulo' => 'Nutricionista deportiva',
            'descripcion' => 'Diez años de experiencia.',
            'cancelacion_horas_minimas' => 48,
        ])->assertStatus(200);

        $this->assertDatabaseHas('professional_profiles', [
            'id' => $perfil->id,
            'titulo' => 'Nutricionista deportiva',
            'cancelacion_horas_minimas' => 48,
        ]);

        $servicio = Service::create([
            'professional_profile_id' => $perfil->id,
            'type' => ServiceType::Session,
            'nombre' => 'Consulta nutricional',
            'duracion' => 45,
            'precio' => 2000,
            'modalidad' => Modalidad::Virtual,
        ]);

        // El cliente reserva (genera un pago pendiente) y consulta su historial.
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($cliente);

        $this->postJson('/api/bookings', [
            'service_id' => $servicio->id,
            'professional_id' => $perfil->id,
            'fecha_hora' => $this->proximoLunes(15),
            'modalidad' => 'virtual',
        ])->assertStatus(201);

        $this->getJson('/api/payments')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.estado', 'pendiente')
            ->assertJsonPath('data.0.tipo', 'reserva');
    }
}
