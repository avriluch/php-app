<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Services\PayPalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private readonly PayPalService $paypal)
    {
    }

    /**
     * Historial de pagos del cliente autenticado (reservas + paquetes).
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $pagos = Payment::query()
            ->with(['booking.service', 'packagePurchase.service'])
            ->where(function ($q) use ($usuario) {
                $q->whereHas('booking', fn ($b) => $b->where('client_user_id', $usuario->id))
                    ->orWhereHas('packagePurchase', fn ($p) => $p->where('client_user_id', $usuario->id));
            })
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->input('per_page', 20), 100));

        return response()->json([
            'data' => $pagos->getCollection()->map(fn (Payment $p) => [
                'id' => $p->id,
                'monto' => (float) $p->monto,
                'estado' => $p->estado->value,
                'metodo' => $p->metodo?->value,
                'fecha_pago' => $p->fecha_pago?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
                'referencia_pasarela' => $p->referencia_pasarela,
                'tipo' => $p->booking_id ? 'reserva' : 'paquete',
                'concepto' => $p->booking
                    ? ('Reserva · ' . ($p->booking->service?->nombre ?? 'Servicio'))
                    : ($p->packagePurchase
                        ? ('Paquete · ' . ($p->packagePurchase->service?->nombre ?? 'Paquete'))
                        : 'Pago'),
                'booking_id' => $p->booking_id,
                'package_purchase_id' => $p->package_purchase_id,
            ])->all(),
            'meta' => [
                'current_page' => $pagos->currentPage(),
                'last_page' => $pagos->lastPage(),
                'total' => $pagos->total(),
            ],
        ]);
    }

    private function autorizarPagoCliente(Payment $payment, User $usuario): void
    {
        if ($payment->booking) {
            abort_unless(
                (int) $payment->booking->client_user_id === (int) $usuario->id,
                403,
                'No autorizado.',
            );

            return;
        }

        if ($payment->packagePurchase) {
            abort_unless(
                (int) $payment->packagePurchase->client_user_id === (int) $usuario->id,
                403,
                'No autorizado.',
            );

            return;
        }

        abort(403, 'No autorizado.');
    }

    public function createPayPalOrder(Request $request, int $paymentId): JsonResponse
    {
        $payment = Payment::with(['booking.client', 'packagePurchase'])->findOrFail($paymentId);

        $usuario = $request->user();
        $this->autorizarPagoCliente($payment, $usuario);

        abort_unless(
            $payment->estado === PaymentStatus::Pendiente,
            422,
            'Este pago ya fue procesado.'
        );

        $order = $this->paypal->createOrder(
            amount: (float) $payment->monto,
            currency: 'USD',
            referenceId: 'payment-' . $payment->id,
        );

        if (empty($order['id'])) {
            return response()->json(['message' => 'Error al crear la orden en PayPal.'], 502);
        }

        return response()->json([
            'order_id' => $order['id'],
            'status' => $order['status'],
        ]);
    }

    public function capturePayPalOrder(Request $request, int $paymentId): JsonResponse
    {
        $datos = $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        $payment = Payment::with(['booking', 'booking.client', 'packagePurchase'])->findOrFail($paymentId);

        $usuario = $request->user();
        $this->autorizarPagoCliente($payment, $usuario);

        abort_unless(
            $payment->estado === PaymentStatus::Pendiente,
            422,
            'Este pago ya fue procesado.'
        );

        $capture = $this->paypal->captureOrder($datos['order_id']);

        if (($capture['status'] ?? '') !== 'COMPLETED') {
            return response()->json([
                'message' => 'El pago no pudo completarse.',
                'paypal_status' => $capture['status'] ?? 'unknown',
                'paypal_debug' => $capture,
            ], 422);
        }

        DB::transaction(function () use ($payment, $capture) {
            $captureDetail = $capture['purchase_units'][0]['payments']['captures'][0] ?? [];

            $payment->update([
                'estado' => PaymentStatus::Completado,
                'metodo' => PaymentMethod::Paypal,
                'fecha_pago' => Carbon::now(),
                'referencia_pasarela' => $captureDetail['id'] ?? $capture['id'],
            ]);

            $booking = $payment->booking;
            if ($booking && in_array($booking->estado, [BookingStatus::Pendiente, BookingStatus::Confirmada])) {
                $booking->update(['estado' => BookingStatus::Pagada]);
            }
        });

        return response()->json([
            'message' => 'Pago completado exitosamente.',
            'payment' => [
                'id' => $payment->id,
                'estado' => PaymentStatus::Completado->value,
                'monto' => (float) $payment->monto,
                'metodo' => PaymentMethod::Paypal->value,
                'fecha_pago' => $payment->fecha_pago?->toIso8601String(),
                'referencia_pasarela' => $payment->referencia_pasarela,
            ],
        ]);
    }
}
