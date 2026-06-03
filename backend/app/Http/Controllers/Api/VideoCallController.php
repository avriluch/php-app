<?php

namespace App\Http\Controllers\Api;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use App\Enums\BookingStatus;
use App\Enums\Modalidad;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoCallController extends Controller
{
    public function token(Request $request, int $id): JsonResponse
    {
        $reserva = Booking::with(['professionalProfile'])->findOrFail($id);
        $usuario = $request->user();

        $esCliente = $usuario->role === UserRole::Client
            && (int) $reserva->client_user_id === (int) $usuario->id;
        $esProfesional = $usuario->role === UserRole::Professional
            && (int) $reserva->professional_profile_id === (int) ($usuario->professionalProfile?->id ?? 0);

        abort_unless($esCliente || $esProfesional, 403, 'No tenés acceso a esta videollamada.');

        abort_unless(
            in_array($reserva->modalidad, [Modalidad::Virtual, Modalidad::Hibrida], true),
            422,
            'Esta reserva no es de modalidad virtual.',
        );

        abort_unless(
            $reserva->estado === BookingStatus::EnCurso,
            422,
            'La sesión todavía no está en curso.',
        );

        $roomName = "booking-{$reserva->id}";
        $nombre = trim("{$usuario->nombre} {$usuario->apellido}") ?: $usuario->email;

        $tokenOptions = (new AccessTokenOptions())
            ->setIdentity("user-{$usuario->id}")
            ->setName($nombre);

        $videoGrant = (new VideoGrant())
            ->setRoomJoin(true)
            ->setRoomName($roomName)
            ->setCanPublish(true)
            ->setCanSubscribe(true);

        $token = (new AccessToken(
            config('services.livekit.api_key'),
            config('services.livekit.api_secret'),
        ))
            ->init($tokenOptions)
            ->setGrant($videoGrant)
            ->toJwt();

        return response()->json([
            'token' => $token,
            'url'   => config('services.livekit.url'),
            'room'  => $roomName,
        ]);
    }
}
