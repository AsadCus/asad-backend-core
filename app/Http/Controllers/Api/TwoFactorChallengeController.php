<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class TwoFactorChallengeController extends Controller
{
    public function challenge(
        Request $request,
        TwoFactorAuthenticationProvider $provider,
    ): JsonResponse {
        $loginId = $request->session()->get('login.id');
        $remember = (bool) $request->session()->get('login.remember', false);

        if (! $loginId) {
            throw ValidationException::withMessages([
                'code' => [__('The provided two factor authentication code was invalid.')],
            ]);
        }

        $user = User::find($loginId);

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => [__('The provided two factor authentication code was invalid.')],
            ]);
        }

        $code = $request->input('code');
        $recovery = $request->input('recovery_code');

        if ($recovery) {
            $code = collect($user->recoveryCodes())->first(
                fn ($c) => hash_equals($c, $recovery),
            );
            if (! $code) {
                throw ValidationException::withMessages([
                    'recovery_code' => [__('The provided two factor recovery code was invalid.')],
                ]);
            }
            $user->replaceRecoveryCode($recovery);
        } else {
            if (! $code) {
                throw ValidationException::withMessages([
                    'code' => [__('The two factor authentication code is required.')],
                ]);
            }
            if (! $provider->verify(decrypt($user->two_factor_secret), $code)) {
                throw ValidationException::withMessages([
                    'code' => [__('The provided two factor authentication code was invalid.')],
                ]);
            }
        }

        $request->session()->forget(['login.id', 'login.remember']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return response()->json(['user' => $user]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $request->session()->forget(['login.id', 'login.remember']);
        return response()->json(['status' => 'ok']);
    }
}
