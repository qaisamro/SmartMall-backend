<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Helpers\ActivityLogger;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function redirectToGoogleJson()
    {
        return response()->json([
            'url' => Socialite::driver('google')->stateless()->redirect()->getTargetUrl()
        ]);
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->to(config('app.frontend_url') . '/login?error=google_auth_failed');
        }

        return $this->loginOrRegister($googleUser);
    }

    public function handleGoogleCallbackStateless()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return redirect()->to(config('app.frontend_url') . '/login?error=google_auth_failed');
        }

        return $this->loginOrRegister($googleUser);
    }

    private function loginOrRegister($googleUser)
    {
        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => bcrypt(Str::random(16)),
            ]);
            $user->assignRole('customer');
        } else {
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        }

        if (!$user->is_active) {
            return redirect()->to(config('app.frontend_url') . '/login?error=account_suspended');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        ActivityLogger::log('logged_in', 'تسجيل دخول عبر Google: ' . $user->name, $user, $user->id);

        return redirect()->to(config('app.frontend_url') . '/auth/google/callback?token=' . $token);
    }
}
