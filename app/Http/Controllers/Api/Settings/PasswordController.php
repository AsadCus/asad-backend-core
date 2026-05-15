<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        activity()
            ->performedOn($request->user())
            ->withProperties(['subject_type' => 'Password', 'subject_id' => $request->user()->id ?? null])
            ->log('Password updated successfully #'.($request->user()->id ?? null));

        return response()->json(['status' => 'ok']);
    }
}
