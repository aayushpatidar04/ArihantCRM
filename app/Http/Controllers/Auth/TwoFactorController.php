<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function setup(Request $request, TwoFactorService $twoFactor): Response
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return Inertia::render('Auth/TwoFactorSetup', [
                'configured' => true,
            ]);
        }

        if (!$user->two_factor_secret) {
            $user->forceFill([
                'two_factor_secret' => $twoFactor->generateSecret(),
            ])->save();
        }

        $issuer = config('app.name', 'Arihant CRM');
        $label = rawurlencode($issuer.':'.$user->email);
        $issuerParam = rawurlencode($issuer);
        $uri = "otpauth://totp/{$label}?secret={$user->two_factor_secret}&issuer={$issuerParam}&algorithm=SHA1&digits=6&period=30";

        $result = Builder::create()
            ->writer(new SvgWriter())
            ->data($uri)
            ->size(260)
            ->margin(10)
            ->build();

        // $result = (new Builder(
        //     writer: new SvgWriter(),
        //     size: 260,
        //     margin: 10,
        //     data: $uri
        // ))->build();

        return Inertia::render('Auth/TwoFactorSetup', [
            'configured' => false,
            'qrCode' => 'data:image/svg+xml;base64,'.base64_encode($result->getString()),
            'secret' => $user->two_factor_secret,
            'email' => $user->email,
        ]);
    }

    public function confirm(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10'],
        ]);

        if (!$twoFactor->verify($request->user(), $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'The authenticator code is invalid or expired.',
            ]);
        }

        $request->user()->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $twoFactor->recoveryCodes(),
        ])->save();

        $request->session()->put('two_factor_verified', true);

        return redirect()->route('two-factor.recovery-codes');
    }

    public function challenge(): Response|RedirectResponse
    {
        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verifyChallenge(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'recovery' => ['nullable', 'boolean'],
        ]);

        $valid = $request->boolean('recovery')
            ? $twoFactor->useRecoveryCode($request->user(), $data['code'])
            : $twoFactor->verify($request->user(), $data['code']);

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid or expired.',
            ]);
        }

        $request->session()->put('two_factor_verified', true);
        return redirect()->intended(route('dashboard'));
    }

    public function recoveryCodes(Request $request): Response
    {
        return Inertia::render('Auth/TwoFactorRecoveryCodes', [
            'codes' => $request->user()->two_factor_recovery_codes ?? [],
        ]);
    }
}
