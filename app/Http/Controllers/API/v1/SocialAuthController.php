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
        \Illuminate\Support\Facades\Log::error('Google callback hit', ['url' => request()->fullUrl(), 'code' => request()->input('code') ? substr(request()->input('code'), 0, 10) . '...' : 'MISSING', 'state' => request()->input('state') ? 'present' : 'missing', 'all' => request()->query()]);
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            $msg = $e->getMessage() ?: get_class($e) . ' (code ' . $e->getCode() . ')';
            \Illuminate\Support\Facades\Log::error('Google callback failed', ['error' => $msg, 'exception' => get_class($e), 'trace' => substr($e->getTraceAsString(), 0, 1000), 'request_code' => request()->input('code') ? 'present' : 'missing']);
            return redirect()->to((config('app.frontend_url') ?: config('app.url')) . '/login?error=google_auth_failed&details=' . urlencode(substr($msg, 0, 200)));
        }

        return $this->loginOrRegister($googleUser);
    }

    public function handleGoogleCallbackStateless()
    {
        \Illuminate\Support\Facades\Log::error('Google stateless callback hit', ['url' => request()->fullUrl(), 'code' => request()->input('code') ? substr(request()->input('code'), 0, 10) . '...' : 'MISSING', 'state' => request()->input('state') ? 'present' : 'missing']);
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            $msg = $e->getMessage() ?: get_class($e) . ' (code ' . $e->getCode() . ')';
            \Illuminate\Support\Facades\Log::error('Google stateless callback failed', ['error' => $msg, 'exception' => get_class($e), 'trace' => substr($e->getTraceAsString(), 0, 2000), 'request_code' => request()->input('code') ? 'present' : 'missing']);
            return redirect()->to((config('app.frontend_url') ?: config('app.url')) . '/login?error=google_auth_failed&details=' . urlencode(substr($msg, 0, 200)));
        }

        return $this->loginOrRegister($googleUser);
    }

    private function loginOrRegister($googleUser)
    {
        $email = $googleUser->getEmail();
        $googleId = $googleUser->getId();
        $name = $googleUser->getName() ?: ($email ? explode('@', $email)[0] : 'مستخدم Google');

        if (!$email) {
            \Illuminate\Support\Facades\Log::warning('Google login without email', ['google_id' => $googleId]);
            return redirect()->to((config('app.frontend_url') ?: config('app.url')) . '/login?error=google_auth_failed&details=' . urlencode('البريد غير متاح من Google'));
        }

        $user = User::where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'google_id' => $googleId,
                'password' => bcrypt(Str::random(16)),
            ]);
            try {
                $user->assignRole('customer');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('assignRole failed for google user', ['error' => $e->getMessage()]);
            }
        } else {
            if (!$user->google_id) {
                $user->update(['google_id' => $googleId]);
            }
        }

        if ((isset($user->is_active) && $user->is_active === 0) || $user->is_active === false) {
            return redirect()->to((config('app.frontend_url') ?: config('app.url')) . '/login?error=account_suspended');
        }
        if (isset($user->status) && $user->status === 'suspended') {
            return redirect()->to((config('app.frontend_url') ?: config('app.url')) . '/login?error=account_suspended');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        ActivityLogger::log('logged_in', 'تسجيل دخول عبر Google: ' . $user->name, $user, $user->id);

        return redirect()->to((config('app.frontend_url') ?: config('app.url')) . '/auth/google/callback?token=' . $token);
    }
}
