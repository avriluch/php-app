<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_leer_y_actualizar_configuracion(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonStructure([
                'nombre_plataforma',
                'registro_abierto',
                'mantenimiento_activo',
                'recordatorio_horas_antes',
            ]);

        $this->putJson('/api/admin/settings', [
            'nombre_plataforma' => 'Mi Plataforma',
            'email_soporte' => 'soporte@test.com',
            'mensaje_mantenimiento' => 'Volvemos pronto.',
            'registro_abierto' => false,
            'mantenimiento_activo' => true,
            'recordatorio_horas_antes' => 12,
            'antelacion_reserva_min_horas' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('settings.nombre_plataforma', 'Mi Plataforma')
            ->assertJsonPath('settings.recordatorio_horas_antes', 12);

        $this->assertDatabaseHas('platform_settings', [
            'nombre_plataforma' => 'Mi Plataforma',
            'registro_abierto' => 0,
            'mantenimiento_activo' => 1,
        ]);
    }

    public function test_cliente_no_puede_editar_configuracion(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Client]));

        $this->putJson('/api/admin/settings', [
            'nombre_plataforma' => 'Hack',
            'email_soporte' => null,
            'mensaje_mantenimiento' => null,
            'registro_abierto' => true,
            'mantenimiento_activo' => false,
            'recordatorio_horas_antes' => 24,
            'antelacion_reserva_min_horas' => 0,
        ])->assertForbidden();
    }

    public function test_registro_cerrado_bloquea_register(): void
    {
        PlatformSetting::current()->update(['registro_abierto' => false]);

        $this->postJson('/api/auth/register', [
            'nombre' => 'Ana',
            'apellido' => 'Test',
            'email' => 'ana@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_mantenimiento_bloquea_api_publica_pero_permite_settings(): void
    {
        PlatformSetting::current()->update([
            'mantenimiento_activo' => true,
            'mensaje_mantenimiento' => 'En obras.',
        ]);

        $this->getJson('/api/professionals')
            ->assertStatus(503)
            ->assertJsonPath('mantenimiento', true);

        $this->getJson('/api/platform-settings')
            ->assertOk()
            ->assertJsonPath('mantenimiento_activo', true);
    }

    public function test_admin_puede_usar_api_con_mantenimiento_activo(): void
    {
        PlatformSetting::current()->update(['mantenimiento_activo' => true]);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->getJson('/api/admin/metrics')->assertOk();
    }
}
