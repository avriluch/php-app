<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
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

    public function createPayPalOrder(Request $request, int $paymentId): JsonResponse
    {
        $payment = Payment::with('booking.client')->findOrFail($paymentId);

        $usuario = $request->user();
        abort_unless(
            (int) $payment->booking?->client_user_id === (int) $usuario->id,
            403,
            'No autorizado.'
        );

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

        $payment = Payment::with(['booking', 'booking.client'])->findOrFail($paymentId);

        $usuario = $request->user();
        abort_unless(
            (int) $payment->booking?->client_user_id === (int) $usuario->id,
            403,
            'No autorizado.'
        );

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
