<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\Modalidad;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Models\Agenda;
use App\Models\Payment;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SimulatedCardPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_pago_con_tarjeta_simulada_completa_reserva(): void
    {
        [$profUser, $perfil, $servicio, $cliente, $payment] = $this->crearPagoPendiente();

        Sanctum::actingAs($cliente);

        $this->postJson("/api/payments/{$payment->id}/card", [
            'metodo' => 'tarjeta_credito',
            'numero' => '4111111111111111',
            'titular' => 'Cliente Demo',
            'vencimiento' => '12/30',
            'cvv' => '123',
        ])
            ->assertOk()
            ->assertJsonPath('payment.estado', 'completado')
            ->assertJsonPath('payment.metodo', 'tarjeta_credito')
            ->assertJsonPath('ultimos_cuatro', '1111');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Completado, $payment->estado);
        $this->assertStringStartsWith('sim-', $payment->referencia_pasarela);

        $this->assertDatabaseHas('bookings', [
            'client_user_id' => $cliente->id,
            'estado' => BookingStatus::Pagada->value,
        ]);
    }

    public function test_tarjeta_de_prueba_rechazada(): void
    {
        [, , , $cliente, $payment] = $this->crearPagoPendiente();

        Sanctum::actingAs($cliente);

        $this->postJson("/api/payments/{$payment->id}/card", [
            'metodo' => 'tarjeta_debito',
            'numero' => '4000000000000002',
            'titular' => 'Cliente Demo',
            'vencimiento' => '12/30',
            'cvv' => '123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['numero']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'estado' => PaymentStatus::Pendiente->value,
        ]);
    }

    /** @return array{0: User, 1: ProfessionalProfile, 2: Service, 3: User, 4: Payment} */
    private function crearPagoPendiente(): array
    {
        $profUser = User::factory()->create(['role' => UserRole::Professional]);
        $perfil = ProfessionalProfile::create([
            'user_id' => $profUser->id,
            'titulo' => 'Profesional',
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

        $cliente = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($cliente);

        $booking = $this->postJson('/api/bookings', [
            'service_id' => $servicio->id,
            'professional_id' => $perfil->id,
            'fecha_hora' => now()->addWeek()->next('Monday')->setTime(10, 0)->toIso8601String(),
            'modalidad' => 'virtual',
        ])->assertCreated()->json();

        $payment = Payment::where('booking_id', $booking['id'])->firstOrFail();

        return [$profUser, $perfil, $servicio, $cliente, $payment];
    }
}
