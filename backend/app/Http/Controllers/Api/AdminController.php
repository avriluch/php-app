<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Endpoint pendiente de implementación.',
            'endpoint' => 'GET /admin/users',
        ], 501);
    }

    public function metrics(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Endpoint pendiente de implementación.',
            'endpoint' => 'GET /admin/metrics',
        ], 501);
    }
}
