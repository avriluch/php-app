<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PackagePurchase;
use App\Models\Payment;
use App\Models\ProfessionalProfile;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Devuelve un objeto de stats apropiado al rol del usuario autenticado.
     * Pensado para alimentar los dashboards (cliente, profesional, admin).
     */
    public function stats(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $stats = match ($usuario->role) {
            UserRole::Client => $this->statsCliente($usuario),
            UserRole::Professional => $this->statsProfesional($usuario),
            UserRole::Admin => $this->statsAdmin(),
        };

        return response()->json($stats);
    }

    /** @return array<string, mixed> */
    private function statsCliente(User $usuario): array
    {
        $ahora = Carbon::now();

        $proximas = Booking::where('client_user_id', $usuario->id)
            ->whereIn('estado', [
                BookingStatus::Pendiente->value,
                BookingStatus::Confirmada->value,
                BookingStatus::Pagada->value,
            ])
            ->where('fecha_hora', '>=', $ahora)
            ->count();

        $realizadas = Booking::where('client_user_id', $usuario->id)
            ->where('estado', BookingStatus::Finalizada->value)
            ->count();

        $paquetesActivos = PackagePurchase::where('client_user_id', $usuario->id)
            ->where('sesiones_restantes', '>', 0)
            ->count();

        $resenasEscritas = Review::where('client_user_id', $usuario->id)->count();

        return [
            'proximas_reservas' => $proximas,
            'sesiones_realizadas' => $realizadas,
            'paquetes_activos' => $paquetesActivos,
            'resenas_escritas' => $resenasEscritas,
        ];
    }

    /** @return array<string, mixed> */
    private function statsProfesional(User $usuario): array
    {
        $perfil = $usuario->professionalProfile;

        if (! $perfil) {
            return [
                'turnos_hoy' => 0,
                'clientes_activos' => 0,
                'ingresos_mes' => 0,
                'calificacion' => null,
                'calificacion_total' => 0,
            ];
        }

        $hoyInicio = Carbon::today();
        $hoyFin = Carbon::today()->endOfDay();
        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();

        $turnosHoy = Booking::where('professional_profile_id', $perfil->id)
            ->whereBetween('fecha_hora', [$hoyInicio, $hoyFin])
            ->whereNotIn('estado', [
                BookingStatus::Cancelada->value,
                BookingStatus::NoAsistida->value,
            ])
            ->count();

        $reservasMes = Booking::where('professional_profile_id', $perfil->id)
            ->whereBetween('fecha_hora', [$inicioMes, $finMes])
            ->whereNotIn('estado', [
                BookingStatus::Cancelada->value,
                BookingStatus::NoAsistida->value,
            ])
            ->count();

        $ingresosMes = Payment::query()
            ->whereHas('booking', function ($q) use ($perfil, $inicioMes, $finMes) {
                $q->where('professional_profile_id', $perfil->id)
                    ->whereBetween('fecha_hora', [$inicioMes, $finMes]);
            })
            ->where('estado', PaymentStatus::Completado->value)
            ->sum('monto');

        $resenas = Review::where('professional_profile_id', $perfil->id);
        $calificacion = (clone $resenas)->avg('puntaje');
        $calificacionTotal = (clone $resenas)->count();

        return [
            'turnos_hoy' => $turnosHoy,
            'reservas_mes' => $reservasMes,
            'ingresos_mes' => (float) $ingresosMes,
            'calificacion' => $calificacion !== null ? round((float) $calificacion, 2) : null,
            'calificacion_total' => $calificacionTotal,
        ];
    }

    /** @return array<string, mixed> */
    private function statsAdmin(): array
    {
        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();

        return [
            'usuarios_total' => User::count(),
            'clientes' => User::where('role', UserRole::Client->value)->count(),
            'profesionales_activos' => ProfessionalProfile::has('services')->count(),
            'reservas_mes' => Booking::whereBetween('created_at', [$inicioMes, $finMes])->count(),
            'reservas_canceladas_mes' => Booking::where('estado', BookingStatus::Cancelada->value)
                ->whereBetween('cancelled_at', [$inicioMes, $finMes])
                ->count(),
            'ingresos_mes' => (float) Payment::where('estado', PaymentStatus::Completado->value)
                ->whereBetween('fecha_pago', [$inicioMes, $finMes])
                ->sum('monto'),
        ];
    }
}
