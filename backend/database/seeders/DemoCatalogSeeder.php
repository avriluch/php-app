<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\Modalidad;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Models\Agenda;
use App\Models\Booking;
use App\Models\Location;
use App\Models\PlatformSetting;
use App\Models\ProfessionalProfile;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        PlatformSetting::current()->update([
            'nombre_plataforma' => 'ServiConnect',
            'email_soporte' => 'soporte@serviconnect.test',
            'registro_abierto' => true,
            'mantenimiento_activo' => false,
            'recordatorio_horas_antes' => 24,
            'antelacion_reserva_min_horas' => 1,
        ]);

        $password = Hash::make('password');

        // Montevideo: un punto por barrio para que el mapa muestre profesionales repartidos.
        $mvPocitos = Location::create([
            'ciudad' => 'Montevideo — Pocitos',
            'pais' => 'UY',
            'latitud' => -34.909400,
            'longitud' => -56.155900,
        ]);
        $mvCordon = Location::create([
            'ciudad' => 'Montevideo — Cordón',
            'pais' => 'UY',
            'latitud' => -34.901100,
            'longitud' => -56.179200,
        ]);
        $mvCiudadVieja = Location::create([
            'ciudad' => 'Montevideo — Ciudad Vieja',
            'pais' => 'UY',
            'latitud' => -34.906700,
            'longitud' => -56.208900,
        ]);
        $mvCentro = Location::create([
            'ciudad' => 'Montevideo — Centro',
            'pais' => 'UY',
            'latitud' => -34.905500,
            'longitud' => -56.191200,
        ]);
        $mvPrado = Location::create([
            'ciudad' => 'Montevideo — Prado',
            'pais' => 'UY',
            'latitud' => -34.857500,
            'longitud' => -56.188000,
        ]);
        $mvMalvin = Location::create([
            'ciudad' => 'Montevideo — Malvín',
            'pais' => 'UY',
            'latitud' => -34.891000,
            'longitud' => -56.105000,
        ]);
        $mvBuceo = Location::create([
            'ciudad' => 'Montevideo — Buceo',
            'pais' => 'UY',
            'latitud' => -34.877500,
            'longitud' => -56.134000,
        ]);
        $mvParqueRodo = Location::create([
            'ciudad' => 'Montevideo — Parque Rodó',
            'pais' => 'UY',
            'latitud' => -34.914000,
            'longitud' => -56.166500,
        ]);

        $puntaMansa = Location::create([
            'ciudad' => 'Punta del Este — Playa Mansa',
            'pais' => 'UY',
            'latitud' => -34.955000,
            'longitud' => -54.942000,
        ]);

        $puntaPuerto = Location::create([
            'ciudad' => 'Punta del Este — Puerto',
            'pais' => 'UY',
            'latitud' => -34.968500,
            'longitud' => -54.951500,
        ]);

        $colonia = Location::create([
            'ciudad' => 'Colonia del Sacramento',
            'pais' => 'UY',
            'latitud' => -34.471550,
            'longitud' => -57.844322,
        ]);

        User::create([
            'nombre' => 'Admin',
            'apellido' => 'Sistema',
            'email' => 'admin@demo.test',
            'password' => $password,
            'role' => UserRole::Admin,
        ]);

        $clientes = $this->crearClientes($password);
        $clientePrincipal = $clientes[0];

        $perfilCarlos = $this->crearProfesional(
            $password,
            [
                'nombre' => 'Carlos',
                'apellido' => 'Pérez',
                'email' => 'profesional@demo.test',
                'telefono' => '+59899111001',
            ],
            [
                'titulo' => 'Nutricionista',
                'descripcion' => 'Consultas presenciales y virtuales. Especialista en hábitos saludables.',
                'location_id' => $mvPocitos->id,
                'cancelacion_horas_minimas' => 24,
            ],
            [
                'horario_inicio' => '09:00:00',
                'horario_fin' => '18:00:00',
                'dias_disponibles' => [1, 2, 3, 4, 5],
                'buffer_minutos' => 15,
                'pausa_entre_sesiones_minutos' => 10,
            ],
            [
                [
                    'type' => ServiceType::Session,
                    'nombre' => 'Consulta inicial',
                    'descripcion' => 'Primera consulta de evaluación.',
                    'duracion' => 60,
                    'precio' => 1500,
                    'modalidad' => Modalidad::Presencial,
                    'location_id' => $mvPocitos->id,
                ],
                [
                    'type' => ServiceType::Session,
                    'nombre' => 'Seguimiento virtual',
                    'descripcion' => 'Control mensual por videollamada.',
                    'duracion' => 45,
                    'precio' => 1200,
                    'modalidad' => Modalidad::Virtual,
                ],
                [
                    'type' => ServiceType::Package,
                    'nombre' => 'Pack 8 sesiones',
                    'descripcion' => 'Paquete con descuento.',
                    'duracion' => 60,
                    'precio' => 10000,
                    'modalidad' => Modalidad::Hibrida,
                    'cantidad_sesiones' => 8,
                ],
            ],
        );

        $perfilValentina = $this->crearProfesional(
            $password,
            ['nombre' => 'Valentina', 'apellido' => 'Ruiz', 'email' => 'valentina.ruiz@demo.test', 'telefono' => '+59899222001'],
            ['titulo' => 'Psicóloga clínica', 'descripcion' => 'Terapia individual online y presencial.', 'location_id' => $mvCordon->id, 'cancelacion_horas_minimas' => 12],
            ['horario_inicio' => '10:00:00', 'horario_fin' => '19:00:00', 'dias_disponibles' => [1, 2, 3, 4, 5], 'buffer_minutos' => 10],
            [
                ['type' => ServiceType::Session, 'nombre' => 'Sesión psicología', 'duracion' => 50, 'precio' => 1800, 'modalidad' => Modalidad::Virtual],
                ['type' => ServiceType::Session, 'nombre' => 'Sesión presencial', 'duracion' => 50, 'precio' => 2200, 'modalidad' => Modalidad::Presencial, 'location_id' => $mvCordon->id],
            ],
        );

        $perfilMartin = $this->crearProfesional(
            $password,
            ['nombre' => 'Martín', 'apellido' => 'Gómez', 'email' => 'martin.gomez@demo.test', 'telefono' => '+59899333001'],
            ['titulo' => 'Entrenador personal', 'descripcion' => 'Fuerza, cardio y planes a medida en Punta del Este.', 'location_id' => $puntaMansa->id, 'cancelacion_horas_minimas' => 48],
            ['horario_inicio' => '07:00:00', 'horario_fin' => '14:00:00', 'dias_disponibles' => [1, 2, 3, 4, 5, 6], 'buffer_minutos' => 20],
            [
                ['type' => ServiceType::Session, 'nombre' => 'Entrenamiento 1h', 'duracion' => 60, 'precio' => 3500, 'modalidad' => Modalidad::Presencial, 'location_id' => $puntaMansa->id],
                ['type' => ServiceType::Package, 'nombre' => 'Pack 10 entrenamientos', 'duracion' => 60, 'precio' => 28000, 'modalidad' => Modalidad::Presencial, 'cantidad_sesiones' => 10, 'location_id' => $puntaMansa->id],
            ],
        );

        $this->crearProfesional(
            $password,
            ['nombre' => 'Laura', 'apellido' => 'Fernández', 'email' => 'laura.fernandez@demo.test'],
            ['titulo' => 'Abogada laboral', 'descripcion' => 'Asesoría en derecho laboral uruguayo.', 'location_id' => $mvCiudadVieja->id, 'cancelacion_horas_minimas' => 24],
            ['horario_inicio' => '14:00:00', 'horario_fin' => '20:00:00', 'dias_disponibles' => [1, 2, 3, 4, 5], 'buffer_minutos' => 0],
            [
                ['type' => ServiceType::Session, 'nombre' => 'Consulta legal', 'duracion' => 40, 'precio' => 4000, 'modalidad' => Modalidad::Presencial, 'location_id' => $mvCiudadVieja->id],
            ],
        );

        $this->crearProfesional(
            $password,
            ['nombre' => 'Diego', 'apellido' => 'Morales', 'email' => 'diego.morales@demo.test'],
            ['titulo' => 'Profesor de inglés', 'descripcion' => 'Clases para todos los niveles, 100% virtual.', 'location_id' => $mvCentro->id, 'cancelacion_horas_minimas' => 6],
            ['horario_inicio' => '08:00:00', 'horario_fin' => '22:00:00', 'dias_disponibles' => [0, 1, 2, 3, 4, 5, 6], 'buffer_minutos' => 5],
            [
                ['type' => ServiceType::Session, 'nombre' => 'Clase de inglés', 'duracion' => 45, 'precio' => 900, 'modalidad' => Modalidad::Virtual],
                ['type' => ServiceType::Package, 'nombre' => 'Pack 12 clases', 'duracion' => 45, 'precio' => 9000, 'modalidad' => Modalidad::Virtual, 'cantidad_sesiones' => 12],
            ],
        );

        $this->crearProfesional(
            $password,
            ['nombre' => 'Sofía', 'apellido' => 'Acuña', 'email' => 'sofia.acuna@demo.test'],
            ['titulo' => 'Instructora de yoga', 'descripcion' => 'Hatha y vinyasa en Colonia y online.', 'location_id' => $colonia->id, 'cancelacion_horas_minimas' => 12],
            ['horario_inicio' => '09:00:00', 'horario_fin' => '17:00:00', 'dias_disponibles' => [2, 3, 4, 5, 6], 'buffer_minutos' => 15],
            [
                ['type' => ServiceType::Session, 'nombre' => 'Clase de yoga', 'duracion' => 75, 'precio' => 1100, 'modalidad' => Modalidad::Hibrida, 'location_id' => $colonia->id],
            ],
        );

        $this->crearProfesional(
            $password,
            ['nombre' => 'Jorge', 'apellido' => 'Castells', 'email' => 'jorge.castells@demo.test'],
            ['titulo' => 'Contador público', 'descripcion' => 'Impuestos, monotributo y asesoría para pymes.', 'location_id' => $mvPrado->id, 'cancelacion_horas_minimas' => 24],
            ['horario_inicio' => '09:00:00', 'horario_fin' => '13:00:00', 'dias_disponibles' => [1, 2, 3, 4, 5], 'buffer_minutos' => 0],
            [
                ['type' => ServiceType::Session, 'nombre' => 'Asesoría contable', 'duracion' => 30, 'precio' => 800, 'modalidad' => Modalidad::Virtual],
            ],
        );

        $this->crearProfesional(
            $password,
            ['nombre' => 'Patricia', 'apellido' => 'Lima', 'email' => 'patricia.lima@demo.test'],
            ['titulo' => 'Fisioterapeuta', 'descripcion' => 'Rehabilitación y dolor lumbar.', 'location_id' => $mvMalvin->id, 'cancelacion_horas_minimas' => 24],
            ['horario_inicio' => '11:00:00', 'horario_fin' => '19:00:00', 'dias_disponibles' => [1, 2, 3, 4, 5], 'buffer_minutos' => 10],
            [
                ['type' => ServiceType::Session, 'nombre' => 'Sesión kinesiología', 'duracion' => 50, 'precio' => 2000, 'modalidad' => Modalidad::Presencial, 'location_id' => $mvMalvin->id],
                ['type' => ServiceType::Package, 'nombre' => 'Pack 6 sesiones', 'duracion' => 50, 'precio' => 10000, 'modalidad' => Modalidad::Presencial, 'cantidad_sesiones' => 6, 'location_id' => $mvMalvin->id],
            ],
        );

        $this->crearProfesional(
            $password,
            ['nombre' => 'Andrés', 'apellido' => 'Vega', 'email' => 'andres.vega@demo.test'],
            ['titulo' => 'Diseñador UX', 'descripcion' => 'Portfolio, wireframes y research.', 'location_id' => $mvBuceo->id, 'cancelacion_horas_minimas' => 12],
            ['horario_inicio' => '15:00:00', 'horario_fin' => '21:00:00', 'dias_disponibles' => [1, 3, 4, 5], 'buffer_minutos' => 0],
            [
                ['type' => ServiceType::Session, 'nombre' => 'Mentoría UX', 'duracion' => 60, 'precio' => 2500, 'modalidad' => Modalidad::Virtual],
            ],
        );

        $this->crearProfesional(
            $password,
            ['nombre' => 'Carmen', 'apellido' => 'Ortiz', 'email' => 'carmen.ortiz@demo.test'],
            ['titulo' => 'Coach de carrera', 'descripcion' => 'CV, entrevistas y búsqueda laboral.', 'location_id' => $mvParqueRodo->id, 'cancelacion_horas_minimas' => 24],
            ['horario_inicio' => '10:00:00', 'horario_fin' => '18:00:00', 'dias_disponibles' => [1, 2, 3, 4, 5], 'buffer_minutos' => 15],
            [
                ['type' => ServiceType::Session, 'nombre' => 'Sesión coaching', 'duracion' => 55, 'precio' => 1600, 'modalidad' => Modalidad::Hibrida, 'location_id' => $mvParqueRodo->id],
            ],
        );

        $this->crearProfesional(
            $password,
            ['nombre' => 'Ricardo', 'apellido' => 'Núñez', 'email' => 'ricardo.nunez@demo.test'],
            ['titulo' => 'Chef y cocina saludable', 'descripcion' => 'Talleres presenciales en Punta del Este.', 'location_id' => $puntaPuerto->id, 'cancelacion_horas_minimas' => 48],
            ['horario_inicio' => '16:00:00', 'horario_fin' => '21:00:00', 'dias_disponibles' => [5, 6, 0], 'buffer_minutos' => 30],
            [
                ['type' => ServiceType::Session, 'nombre' => 'Taller cocina', 'duracion' => 120, 'precio' => 4500, 'modalidad' => Modalidad::Presencial, 'location_id' => $puntaPuerto->id],
            ],
        );

        // Reseñas para probar filtro por calificación (rating_min).
        $this->crearResena($clientePrincipal, $perfilCarlos, 5.0, 'Excelente profesional, muy claro.');
        $this->crearResena($clientes[1], $perfilCarlos, 4.5, 'Muy buena atención.');
        $this->crearResena($clientes[2], $perfilValentina, 5.0, 'Me ayudó muchísimo.');
        $this->crearResena($clientes[3], $perfilMartin, 3.5, 'Buen entrenamiento, horarios algo rígidos.');

        $this->imprimirCredenciales($clientes);
    }

    /** @return list<User> */
    private function crearClientes(string $password): array
    {
        $datos = [
            ['nombre' => 'Ana', 'apellido' => 'Cliente', 'email' => 'cliente@demo.test', 'telefono' => '+59899111222'],
            ['nombre' => 'Bruno', 'apellido' => 'Martínez', 'email' => 'bruno.martinez@demo.test', 'telefono' => '+59899222333'],
            ['nombre' => 'María', 'apellido' => 'García', 'email' => 'maria.garcia@demo.test', 'telefono' => '+59899333444'],
            ['nombre' => 'Juan', 'apellido' => 'Rodríguez', 'email' => 'juan.rodriguez@demo.test'],
            ['nombre' => 'Sofía', 'apellido' => 'Herrera', 'email' => 'sofia.herrera@demo.test', 'telefono' => '+59899555666'],
            ['nombre' => 'Pedro', 'apellido' => 'Silva', 'email' => 'pedro.silva@demo.test'],
        ];

        $clientes = [];
        foreach ($datos as $row) {
            $clientes[] = User::create([
                ...$row,
                'password' => $password,
                'role' => UserRole::Client,
            ]);
        }

        return $clientes;
    }

    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $agendaData
     * @param  list<array<string, mixed>>  $servicios
     */
    private function crearProfesional(
        string $password,
        array $userData,
        array $profileData,
        array $agendaData,
        array $servicios,
    ): ProfessionalProfile {
        $user = User::create([
            ...$userData,
            'password' => $password,
            'role' => UserRole::Professional,
        ]);

        $profile = ProfessionalProfile::create([
            ...$profileData,
            'user_id' => $user->id,
        ]);

        Agenda::create([
            ...$agendaData,
            'professional_profile_id' => $profile->id,
        ]);

        foreach ($servicios as $svc) {
            Service::create([
                ...$svc,
                'professional_profile_id' => $profile->id,
                'activo' => true,
            ]);
        }

        return $profile;
    }

    private function crearResena(
        User $cliente,
        ProfessionalProfile $perfil,
        float $puntaje,
        string $comentario,
    ): void {
        $servicio = $perfil->services()->first();
        if (! $servicio) {
            return;
        }

        $reserva = Booking::create([
            'client_user_id' => $cliente->id,
            'professional_profile_id' => $perfil->id,
            'service_id' => $servicio->id,
            'fecha_hora' => Carbon::now()->subDays(random_int(10, 60)),
            'modalidad' => $servicio->modalidad->value,
            'estado' => BookingStatus::Finalizada,
        ]);

        Review::create([
            'booking_id' => $reserva->id,
            'professional_profile_id' => $perfil->id,
            'client_user_id' => $cliente->id,
            'puntaje' => $puntaje,
            'comentario' => $comentario,
            'fecha' => Carbon::now()->subDays(random_int(1, 9)),
        ]);
    }

    /** @param  list<User>  $clientes */
    private function imprimirCredenciales(array $clientes): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('');
        $this->command->info('=== Datos demo (contraseña: password) ===');
        $this->command->info('Admin: admin@demo.test');
        $this->command->info('');
        $this->command->info('Clientes:');
        foreach ($clientes as $c) {
            $this->command->info("  {$c->email} — {$c->nombre} {$c->apellido}");
        }
        $this->command->info('');
        $this->command->info('Profesionales (10 + Carlos):');
        $emails = [
            'profesional@demo.test',
            'valentina.ruiz@demo.test',
            'martin.gomez@demo.test',
            'laura.fernandez@demo.test',
            'diego.morales@demo.test',
            'sofia.acuna@demo.test',
            'jorge.castells@demo.test',
            'patricia.lima@demo.test',
            'andres.vega@demo.test',
            'carmen.ortiz@demo.test',
            'ricardo.nunez@demo.test',
        ];
        foreach ($emails as $email) {
            $this->command->info("  {$email}");
        }
        $this->command->info('');
        $this->command->info('Búsqueda: probá "psicóloga", "yoga", "inglés", "Punta", etc.');
        $this->command->info('Filtros: virtual / presencial / paquete, ciudad, precio, estrellas.');
    }
}
