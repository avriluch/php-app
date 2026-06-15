<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;

class PlatformSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(PlatformSetting::current()->toPublicArray());
    }
}
