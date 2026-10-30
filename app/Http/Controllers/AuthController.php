<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Login');
    }

    public function showRegister(Request $request): Response
    {
        $registration = null;
        $token = $request->string('status_token')->toString();

        if ($token !== '') {
            $registration = $this->registrationForToken($token);
        }

        return Inertia::render('Register', ['registration' => $registration]);
    }

    public function register(Request $request): RedirectResponse
    {
        $key = 'registration|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['email' => 'Too many registration attempts. Try again later.'])->withInput();
        }

        RateLimiter::hit($key, 3600);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'requested_role' => ['required', 'in:instructor,inventory_clerk,sales_personnel'],
            'affiliation' => ['required', 'string', 'max:255'],
        ]);

        $token = Str::random(64);

        $user = \App\Models\User::create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'password' => $data['password'],
            'role' => 'sales_personnel',
            'requested_role' => $data['requested_role'],
            'affiliation' => $data['affiliation'],
            'status' => 'pending',
            'registration_status_token' => hash('sha256', $token),
            'submitted_at' => now(),
        ]);

        return redirect('/register?status_token=' . urlencode($token));
    }

    private function registrationForToken(string $token): ?array
    {
        $user = \App\Models\User::query()
            ->where('registration_status_token', hash('sha256', $token))
            ->first();

        if (! $user) {
            return null;
        }

        return [
            'status' => $user->status,
            'submitted_at' => $user->submitted_at?->toIso8601String(),
            'requested_role' => $user->requested_role,
        ];
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $key = Str::transliterate(Str::lower($credentials['email']) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'email' => 'Too many sign-in attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.',
            ])->onlyInput('email');
        }

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'status' => 'active',
        ], $credentials['remember'] ?? false)) {
            RateLimiter::hit($key);
            $account = \App\Models\User::where('email', Str::lower($credentials['email']))->first();
            $message = 'These credentials do not match an active account.';

            if ($account && Hash::check($credentials['password'], $account->password)) {
                $message = match ($account->status) {
                    'pending' => 'Your registration is awaiting administrator approval.',
                    'rejected' => 'Your registration was not approved. Contact an administrator if you need more information.',
                    'suspended' => 'This account is suspended. Contact an administrator for assistance.',
                    default => $message,
                };
            }

            return back()->withErrors([
                'email' => $message,
            ])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function showForgotPassword(): Response
    {
        return Inertia::render('ForgotPassword');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($credentials);

        return back()->with($status === Password::RESET_LINK_SENT ? 'status' : 'error', __($status));
    }

    public function showResetPassword(Request $request, string $token): Response
    {
        return Inertia::render('ResetPassword', [
            'email' => $request->string('email')->toString(),
            'token' => $token,
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset($credentials, function ($user, $password) {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
        });

        return $status === Password::PASSWORD_RESET
            ? redirect('/login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]])->withInput(['email']);
    }
}
