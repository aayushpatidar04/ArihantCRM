<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use App\Mail\TwoFactorOtpMail;

class EmailTwoFactorController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Show "we sent you a code" page (also sends the OTP)
    |--------------------------------------------------------------------------
    */
    public function challenge(Request $request)
    {
        $user = $request->user();

        /*
         * Send a fresh OTP every time the challenge page is loaded,
         * unless one was sent very recently (debounce 30s).
         */
        if (
            !$user->email_otp_expires_at ||
            $user->email_otp_expires_at->lt(now()->addSeconds(30))
        ) {
            $this->sendOtp($user);
        }

        return inertia('Auth/TwoFactor/EmailChallenge');
    }

    /*
    |--------------------------------------------------------------------------
    | Verify the OTP
    |--------------------------------------------------------------------------
    */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        /*
         * Throttle verification attempts (5 per 5 minutes per session).
         */
        $throttleKey = 'two-factor:' . $request->session()->getId();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'code' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (
            !$user->email_otp_code ||
            !$user->email_otp_expires_at ||
            $user->email_otp_expires_at->isPast() ||
            !hash_equals((string) $user->email_otp_code, (string) $request->code)
        ) {
            RateLimiter::hit($throttleKey, 300);

            return back()->withErrors([
                'code' => 'The code is invalid or has expired.',
            ]);
        }

        /*
         * Code is valid — clear it and mark the session as verified.
         */
        $user->forceFill([
            'email_otp_code' => null,
            'email_otp_expires_at' => null,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->put('two_factor_verified', true);
        RateLimiter::clear($throttleKey);

        return redirect()->intended(route('dashboard'));
    }

    /*
    |--------------------------------------------------------------------------
    | Resend a fresh OTP
    |--------------------------------------------------------------------------
    */
    public function resend(Request $request)
    {
        $user = $request->user();

        /*
         * Rate-limit resends (1 per 30 seconds).
         */
        $throttleKey = 'two-factor-resend:' . $request->session()->getId();

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'code' => "Please wait {$seconds} seconds before requesting a new code.",
            ]);
        }

        $this->sendOtp($user);
        RateLimiter::hit($throttleKey, 30);

        return back()->with('status', 'A new code has been sent to your email.');
    }

    /*
    |--------------------------------------------------------------------------
    | Generate and email the OTP
    |--------------------------------------------------------------------------
    */
    protected function sendOtp($user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'email_otp_code' => $code,
            'email_otp_expires_at' => now()->addMinutes(10),
        ])->save();

        Mail::to($user->email)->send(new TwoFactorOtpMail($code, $user->name));
    }
}