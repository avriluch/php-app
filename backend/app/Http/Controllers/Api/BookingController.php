<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\Modalidad;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Jobs\EnviarCancelacionReserva;
use App\Jobs\EnviarConfirmacionReserva;
use App\Jobs\EnviarReagendacionReserva;
use App\Models\Booking;
use App\Models\PackagePurchase;
use App\Models\Payment;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function __construct(private readonly AvailabilityService $disponibilidad)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $query = Booking::query()
            ->with(['service', 'professionalProfile.user', 'client', 'payment', 'review'])
            ->orderByDesc('fecha_hora');

        if ($usuario->role === UserRole::Client) {
            $query->where('client_user_id', $usuario->id);
        } elseif ($usuario->role === UserRole::Professional) {
            $perfil = $usuario->professionalProfile;
            abort_unless($perfil, 403, 'Perfil profesional no encontrado.');
            $query->where('professional_profile_id', $perfil->id);
        }
        // Admin ve todo

        if ($estado = $request->string('estado')->toString()) {
            $query->where('estado', $estado);
        }
        if ($desde = $request->string('from')->toString()) {
            $query->where('fecha_hora', '>=', Carbon::parse($desde));
        }
        if ($hasta = $request->string('to')->toString()) {
            $query->where('fecha_hora', '<=', Carbon::parse($hasta));
        }

        $order = $request->string('order', 'desc')->toString() === 'asc' ? 'asc' : 'desc';
        $query->reorder('fecha_hora', $order);

        $reservas = $query->paginate(
            perPage: min((int) $request->input('per_page', 20), 100)
        );

        return response()->json([
            'data' => BookingResource::collection($reservas)->resolve(),
            'meta' => [
                'current_page' => $reservas->currentPage(),
                'last_page' => $reservas->lastPage(),
                'per_page' => $reservas->perPage(),
                'total' => $reservas->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $reserva = Booking::with(['service', 'professionalProfile.user', 'client', 'payment', 'review'])
            ->findOrFail($id);

        $this->autorizarVer($request, $reserva);

        return response()->json(new BookingResource($reserva));
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->role === UserRole::Client, 403, 'Solo clientes pueden reservar.');

        $datos = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'professional_id' => ['required', 'integer', 'exists:professional_profiles,id'],
            'fecha_hora' => ['required', 'date', 'after:now'],
            'modalidad' => ['required', Rule::in(array_column(Modalidad::cases(), 'value'))],
            'package_purchase_id' => ['nullable', 'integer', 'exists:package_purchases,id'],
        ]);

        $fechaHora = Carbon::parse($datos['fecha_hora']);

        return DB::transaction(function () use ($datos, $fechaHora, $usuario) {
            // Lock pesimista sobre el profesional: serializa TODAS sus reservas, de modo que
            // dos clientes no puedan tomar el mismo horario en paralelo (aunque sea con
            // servicios distintos). Debe ir antes del lock del servicio para mantener un
            // orden de bloqueo consistente y evitar deadlocks.
            ProfessionalProfile::lockForUpdate()->findOrFail($datos['professional_id']);

            $servicio = Service::lockForUpdate()->findOrFail($datos['service_id']);

            abort_unless(
                (int) $servicio->professional_profile_id === (int) $datos['professional_id'],
                422,
                'El servicio no pertenece al profesional indicado.',
            );
            abort_unless($servicio->activo, 422, 'El servicio no está activo.');

            // Coherencia de modalidad: si el servicio es solo presencial o solo virtual,
            // la reserva no puede pedir una modalidad distinta. Si el servicio es híbrido,
            // se acepta cualquier modalidad concreta.
            $modalidadServicio = $servicio->modalidad?->value;
            $modalidadReserva = $datos['modalidad'];
            if ($modalidadServicio !== Modalidad::Hibrida->value
                && $modalidadServicio !== $modalidadReserva
            ) {
                throw ValidationException::withMessages([
                    'modalidad' => "El servicio solo admite la modalidad '{$modalidadServicio}'.",
                ]);
            }

            $esPaquete = $servicio->type === ServiceType::Package;
            $compraPaquete = null;

            if ($esPaquete && empty($datos['package_purchase_id'])) {
                throw ValidationException::withMessages([
                    'package_purchase_id' => 'Para reservar una sesión de un paquete, debés enviar package_purchase_id.',
                ]);
            }

            if (! empty($datos['package_purchase_id'])) {
                $compraPaquete = PackagePurchase::lockForUpdate()
                    ->where('id', $datos['package_purchase_id'])
                    ->where('client_user_id', $usuario->id)
                    ->where('service_id', $servicio->id)
                    ->first();

                if (! $compraPaquete) {
                    throw ValidationException::withMessages([
                        'package_purchase_id' => 'Paquete no encontrado para este cliente y servicio.',
                    ]);
                }
                if ($compraPaquete->sesiones_restantes <= 0) {
                    throw ValidationException::withMessages([
                        'package_purchase_id' => 'El paquete no tiene sesiones restantes.',
                    ]);
                }
            }

            if (! $this->disponibilidad->isSlotFree(
                (int) $datos['professional_id'],
                $fechaHora,
                (int) $servicio->duracion,
            )) {
                throw ValidationException::withMessages([
                    'fecha_hora' => 'El horario ya no está disponible.',
                ]);
            }

            $reserva = Booking::create([
                'client_user_id' => $usuario->id,
                'professional_profile_id' => $datos['professional_id'],
                'service_id' => $servicio->id,
                'package_purchase_id' => $compraPaquete?->id,
                'fecha_hora' => $fechaHora,
                'modalidad' => $modalidadReserva,
                'estado' => BookingStatus::Pendiente,
            ]);

            // El Payment se crea solo para sesiones sueltas. Si la reserva consume
            // de un paquete, el cobro vivió en `package_purchases.payment` al comprarlo.
            if (! $compraPaquete) {
                Payment::create([
                    'booking_id' => $reserva->id,
                    'monto' => (float) $servicio->precio,
                    'estado' => PaymentStatus::Pendiente->value,
                ]);
            } else {
                $compraPaquete->decrement('sesiones_restantes');
            }

            $reserva->load(['service', 'professionalProfile.user', 'payment']);

            // Notificación + email asincrónicos tras el commit.
            EnviarConfirmacionReserva::dispatch($reserva->id)->afterCommit();

            return response()->json(new BookingResource($reserva), 201);
        });
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $reserva = Booking::with(['service', 'professionalProfile', 'payment', 'packagePurchase'])
            ->findOrFail($id);

        $this->autorizarVer($request, $reserva);

        if (! $reserva->estado->canTransitionTo(BookingStatus::Cancelada)) {
            throw ValidationException::withMessages([
                'estado' => "No se puede cancelar una reserva en estado '{$reserva->estado->value}'.",
            ]);
        }

        $usuario = $request->user();
        $esCliente = $usuario->role === UserRole::Client
            && (int) $reserva->client_user_id === (int) $usuario->id;
        if ($esCliente) {
            $minHoras = (int) ($reserva->professionalProfile?->cancelacion_horas_minimas ?? 0);
            if ($minHoras > 0 && Carbon::now()->addHours($minHoras)->gt($reserva->fecha_hora)) {
                throw ValidationException::withMessages([
                    'fecha_hora' => "Debe cancelar con al menos {$minHoras}h de anticipación.",
                ]);
            }
        }

        $motivo = $request->input('motivo');

        DB::transaction(function () use ($reserva, $motivo) {
            $reserva->update([
                'estado' => BookingStatus::Cancelada,
                'cancelled_at' => Carbon::now(),
                'cancel_motivo' => $motivo,
            ]);

            if ($reserva->packagePurchase) {
                $reserva->packagePurchase->increment('sesiones_restantes');
            }
        });

        $reserva->refresh()->load(['service', 'professionalProfile.user', 'payment']);

        EnviarCancelacionReserva::dispatch($reserva->id)->afterCommit();

        return response()->json(new BookingResource($reserva));
    }

    public function reschedule(Request $request, int $id): JsonResponse
    {
        $reserva = Booking::with('service')->findOrFail($id);
        $this->autorizarVer($request, $reserva);

        $usuario = $request->user();
        abort_unless(
            $usuario->role === UserRole::Client
                && (int) $reserva->client_user_id === (int) $usuario->id,
            403,
            'Solo el cliente puede reprogramar su reserva.',
        );

        $estadosPermitidos = [BookingStatus::Pendiente, BookingStatus::Confirmada, BookingStatus::Pagada];
        if (! in_array($reserva->estado, $estadosPermitidos, true)) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se reprograman reservas pendientes, confirmadas o pagadas.',
            ]);
        }

        $datos = $request->validate([
            'fecha_hora' => ['required', 'date', 'after:now'],
        ]);

        $fechaAnterior = $reserva->fecha_hora?->format('d/m/Y H:i');
        $nuevaFecha = Carbon::parse($datos['fecha_hora']);

        if (Carbon::now()->addHours(24)->gt($nuevaFecha)) {
            throw ValidationException::withMessages([
                'fecha_hora' => 'Debe reprogramar con al menos 24 horas de anticipación.',
            ]);
        }

        DB::transaction(function () use ($reserva, $nuevaFecha) {
            // Mismo lock pesimista por profesional que en store(), para que reprogramar
            // no choque con otra reserva o reprogramación simultánea en el mismo horario.
            ProfessionalProfile::lockForUpdate()->findOrFail($reserva->professional_profile_id);

            if (! $this->disponibilidad->isSlotFree(
                (int) $reserva->professional_profile_id,
                $nuevaFecha,
                (int) $reserva->service->duracion,
                $reserva->id,
            )) {
                throw ValidationException::withMessages([
                    'fecha_hora' => 'El nuevo horario no está disponible.',
                ]);
            }

            $reserva->update(['fecha_hora' => $nuevaFecha]);
        });

        $reserva->refresh()->load(['service', 'professionalProfile.user', 'payment']);

        EnviarReagendacionReserva::dispatch($reserva->id, $fechaAnterior)->afterCommit();

        return response()->json(new BookingResource($reserva));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $reserva = Booking::with(['service', 'professionalProfile'])->findOrFail($id);

        $usuario = $request->user();
        $esProfesionalDueno = $usuario->role === UserRole::Professional
            && (int) $reserva->professional_profile_id === (int) ($usuario->professionalProfile?->id ?? 0);
        $esAdmin = $usuario->role === UserRole::Admin;
        abort_unless($esProfesionalDueno || $esAdmin, 403);

        $datos = $request->validate([
            'estado' => ['required', Rule::in(array_column(BookingStatus::cases(), 'value'))],
        ]);

        $siguiente = BookingStatus::from($datos['estado']);

        if (! $reserva->estado->canTransitionTo($siguiente)) {
            throw ValidationException::withMessages([
                'estado' => "Transición no permitida: {$reserva->estado->value} → {$siguiente->value}.",
            ]);
        }

        $cambios = ['estado' => $siguiente];

        if ($siguiente === BookingStatus::EnCurso
            && in_array($reserva->modalidad, [Modalidad::Virtual, Modalidad::Hibrida], true)
        ) {
            $cambios['url_video_llamada'] = "booking-{$reserva->id}";
        }

        $reserva->update($cambios);
        $reserva->refresh()->load(['service', 'professionalProfile.user', 'payment']);

        return response()->json(new BookingResource($reserva));
    }

    private function autorizarVer(Request $request, Booking $reserva): void
    {
        $usuario = $request->user();

        if ($usuario->role === UserRole::Admin) {
            return;
        }
        if ($usuario->role === UserRole::Client
            && (int) $reserva->client_user_id === (int) $usuario->id
        ) {
            return;
        }
        if (
            $usuario->role === UserRole::Professional
            && (int) $reserva->professional_profile_id === (int) ($usuario->professionalProfile?->id ?? 0)
        ) {
            return;
        }

        abort(403);
    }
}
