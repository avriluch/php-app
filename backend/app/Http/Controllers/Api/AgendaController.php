<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agenda;
use App\Models\AgendaException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $perfil = $request->user()->professionalProfile;
        abort_unless($perfil, 404, 'Perfil profesional no encontrado.');

        $agenda = $perfil->agenda()->with('exceptions')->first();

        return response()->json([
            'agenda' => $agenda ? [
                'id' => $agenda->id,
                'horario_inicio' => substr($agenda->horario_inicio, 0, 5),
                'horario_fin' => substr($agenda->horario_fin, 0, 5),
                'dias_disponibles' => array_map('intval', $agenda->dias_disponibles ?? []),
                'buffer_minutos' => (int) $agenda->buffer_minutos,
                'exceptions' => $agenda->exceptions->map(fn ($e) => [
                    'id' => $e->id,
                    'fecha' => $e->fecha->toDateString(),
                    'motivo' => $e->motivo,
                ])->all(),
            ] : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $perfil = $request->user()->professionalProfile;
        abort_unless($perfil, 404, 'Perfil profesional no encontrado.');

        $datos = $request->validate([
            'horario_inicio' => ['required', 'date_format:H:i'],
            'horario_fin' => ['required', 'date_format:H:i', 'after:horario_inicio'],
            'dias_disponibles' => ['required', 'array', 'min:1'],
            'dias_disponibles.*' => ['integer', 'between:0,6'],
            'buffer_minutos' => ['required', 'integer', 'between:0,240'],
        ]);

        $agenda = Agenda::updateOrCreate(
            ['professional_profile_id' => $perfil->id],
            [
                'horario_inicio' => $datos['horario_inicio'] . ':00',
                'horario_fin' => $datos['horario_fin'] . ':00',
                'dias_disponibles' => array_values(array_unique(array_map('intval', $datos['dias_disponibles']))),
                'buffer_minutos' => $datos['buffer_minutos'],
            ],
        );

        return response()->json([
            'agenda' => [
                'id' => $agenda->id,
                'horario_inicio' => substr($agenda->horario_inicio, 0, 5),
                'horario_fin' => substr($agenda->horario_fin, 0, 5),
                'dias_disponibles' => array_map('intval', $agenda->dias_disponibles),
                'buffer_minutos' => (int) $agenda->buffer_minutos,
            ],
        ]);
    }

    public function storeException(Request $request): JsonResponse
    {
        $perfil = $request->user()->professionalProfile;
        $agenda = $perfil?->agenda;
        abort_unless($agenda, 422, 'Configurá primero tu agenda.');

        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        $excepcion = AgendaException::create([
            'agenda_id' => $agenda->id,
            'fecha' => $datos['fecha'],
            'motivo' => $datos['motivo'],
        ]);

        return response()->json([
            'id' => $excepcion->id,
            'fecha' => $excepcion->fecha->toDateString(),
            'motivo' => $excepcion->motivo,
        ], 201);
    }

    public function destroyException(Request $request, int $id): JsonResponse
    {
        $perfil = $request->user()->professionalProfile;
        $agenda = $perfil?->agenda;
        abort_unless($agenda, 404);

        $excepcion = AgendaException::where('agenda_id', $agenda->id)
            ->where('id', $id)
            ->firstOrFail();

        $excepcion->delete();

        return response()->json(['deleted' => true]);
    }
}
