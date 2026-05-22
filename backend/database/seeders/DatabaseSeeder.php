<?php

namespace Database\Seeders;

use App\Enums\Modalidad;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Models\Agenda;
use App\Models\Location;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $montevideo = Location::create([
            'ciudad' => 'Montevideo',
            'pais' => 'UY',
            'latitud' => -34.901112,
            'longitud' => -56.164531,
        ]);

        User::create([
            'nombre' => 'Admin',
            'apellido' => 'Sistema',
            'email' => 'admin@demo.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);

        User::create([
            'nombre' => 'Ana',
            'apellido' => 'Cliente',
            'email' => 'cliente@demo.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Client,
            'telefono' => '+59899111222',
        ]);

        $proUser = User::create([
            'nombre' => 'Carlos',
            'apellido' => 'Pérez',
            'email' => 'profesional@demo.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Professional,
        ]);

        $profile = ProfessionalProfile::create([
            'user_id' => $proUser->id,
            'titulo' => 'Nutricionista',
            'descripcion' => 'Consultas presenciales y virtuales.',
            'location_id' => $montevideo->id,
            'cancelacion_horas_minimas' => 24,
        ]);

        Agenda::create([
            'professional_profile_id' => $profile->id,
            'horario_inicio' => '09:00:00',
            'horario_fin' => '18:00:00',
            'dias_disponibles' => [1, 2, 3, 4, 5],
            'buffer_minutos' => 15,
        ]);

        Service::create([
            'professional_profile_id' => $profile->id,
            'type' => ServiceType::Session,
            'nombre' => 'Consulta inicial',
            'descripcion' => 'Primera consulta de evaluación.',
            'duracion' => 60,
            'precio' => 1500,
            'modalidad' => Modalidad::Presencial,
            'location_id' => $montevideo->id,
        ]);

        Service::create([
            'professional_profile_id' => $profile->id,
            'type' => ServiceType::Session,
            'nombre' => 'Seguimiento virtual',
            'descripcion' => 'Control mensual por videollamada.',
            'duracion' => 45,
            'precio' => 1200,
            'modalidad' => Modalidad::Virtual,
        ]);

        Service::create([
            'professional_profile_id' => $profile->id,
            'type' => ServiceType::Package,
            'nombre' => 'Pack 8 sesiones',
            'descripcion' => 'Paquete con descuento.',
            'duracion' => 60,
            'precio' => 10000,
            'modalidad' => Modalidad::Hibrida,
            'cantidad_sesiones' => 8,
        ]);

        $this->command?->info('Usuarios demo (password: password):');
        $this->command?->info('  admin@demo.test');
        $this->command?->info('  cliente@demo.test');
        $this->command?->info('  profesional@demo.test');
    }
}
