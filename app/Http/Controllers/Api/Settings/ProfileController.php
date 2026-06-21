<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileAvatarRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact,
            'avatar_url' => $user->avatar_url,
            'email_verified_at' => $user->email_verified_at,
        ]);
    }

    public function updateAvatar(ProfileAvatarRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->photo_profile) {
            Storage::disk('public')->delete($user->photo_profile);
        }

        $path = $request->file('photo')->store('avatars', 'public');
        $user->forceFill(['photo_profile' => $path])->save();

        return response()->json([
            'status' => 'ok',
            'avatar_url' => $user->avatar_url,
        ]);
    }

    public function destroyAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->photo_profile) {
            Storage::disk('public')->delete($user->photo_profile);
            $user->forceFill(['photo_profile' => null])->save();
        }

        return response()->json(['status' => 'ok', 'avatar_url' => null]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        Auth::logout();

        activity()
            ->performedOn($user)
            ->withProperties(['subject_type' => 'Profile', 'subject_id' => $user->id ?? null])
            ->log('Profile deleted successfully #'.($user->id ?? null));

        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'ok']);
    }
}
