<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SignatureController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'signature' => ['required', 'string'],
        ]);

        $dataUrl = $request->string('signature')->value();

        if (! preg_match('/^data:(image\/(?:png|jpeg|webp));base64,/', $dataUrl, $matches)) {
            abort(422, 'Invalid signature format.');
        }

        $ext = match ($matches[1]) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        $imageData = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $dataUrl));

        $user = $request->user();

        if ($user->signature_path) {
            Storage::disk('public')->delete($user->signature_path);
        }

        $path = 'signatures/'.$user->id.'_'.time().'.'.$ext;
        Storage::disk('public')->put($path, $imageData);

        $user->forceFill(['signature_path' => $path])->save();

        return response()->json([
            'status' => 'ok',
            'signature_url' => $user->signature_url,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->signature_path) {
            Storage::disk('public')->delete($user->signature_path);
            $user->forceFill(['signature_path' => null])->save();
        }

        return response()->json(['status' => 'ok', 'signature_url' => null]);
    }
}
