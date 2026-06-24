<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function random(Request $request): JsonResponse
    {
        $raw = Inspiring::quotes()->random();
        [$message, $author] = str($raw)->explode('-');

        return response()->json([
            'quote' => trim($message),
            'name' => trim($author),
        ]);
    }
}
