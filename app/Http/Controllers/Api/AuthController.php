<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\MinimumAge;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        // No "name" field here on purpose — the Sign Up screen only collects
        // email/password/DOB; first name is collected next, in Profile Setup.
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->numbers()],
            'dob' => ['bail', 'required', 'date', 'before:today', new MinimumAge(13)],
        ]);

        $user = User::create([
            'name' => Str::before($validated['email'], '@'),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'dob' => $validated['dob'],
        ]);

        $token = $user->createToken('studypilot-mobile')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $token = $user->createToken('studypilot-mobile')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Reset link sent.'])
            : response()->json(['message' => __($status)], 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->numbers()],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset.'])
            : response()->json(['message' => __($status)], 422);
    }

    /**
     * Verifies a Google ID token and signs the user in, creating an account on first sign-in.
     *
     * Note: Google's basic profile scope doesn't include date of birth, so social-signup
     * accounts skip the 13+ DOB gate that email/password signup enforces at registration.
     */
    public function googleSignIn(Request $request): JsonResponse
    {
        $clientId = config('services.google.client_id');

        if (! $clientId) {
            return response()->json([
                'message' => 'Google Sign-In is not configured yet. Set GOOGLE_CLIENT_ID in .env to enable it.',
            ], 501);
        }

        $request->validate(['id_token' => ['required', 'string']]);

        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->string('id_token'),
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'id_token' => ['Could not verify this Google sign-in. Please try again.'],
            ]);
        }

        $payload = $response->json();

        if (($payload['aud'] ?? null) !== $clientId || ($payload['email_verified'] ?? 'false') !== 'true') {
            throw ValidationException::withMessages([
                'id_token' => ['This Google account could not be verified.'],
            ]);
        }

        $user = User::where('google_id', $payload['sub'])->first()
            ?? User::where('email', $payload['email'])->first();

        $isNew = ! $user;

        if (! $user) {
            $user = User::create([
                'name' => $payload['given_name'] ?? Str::before($payload['email'], '@'),
                'email' => $payload['email'],
                'password' => Str::random(40),
                'google_id' => $payload['sub'],
            ]);
        } elseif (! $user->google_id) {
            // This email already had a password-based account we never verified ownership of.
            // Google just proved real ownership — stronger than our own signup form — so the
            // old password (and any sessions logged in with it) can no longer be trusted.
            $user->tokens()->delete();
            $user->update([
                'google_id' => $payload['sub'],
                'password' => Str::random(40),
            ]);
        }

        $token = $user->createToken('studypilot-mobile')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token, 'is_new' => $isNew]);
    }

    /**
     * Verifies an Apple identity token and signs the user in, creating an account on first sign-in.
     *
     * Apple only hands back the user's name on their very first-ever authorization with this
     * app, so the frontend passes it along on that first call for us to store — like Google,
     * social-signup accounts skip the 13+ DOB gate since Apple doesn't share birthdate either.
     */
    public function appleSignIn(Request $request): JsonResponse
    {
        $bundleId = config('services.apple.bundle_id');

        if (! $bundleId) {
            return response()->json([
                'message' => 'Apple Sign-In is not configured yet. Set APPLE_BUNDLE_ID in .env to enable it.',
            ], 501);
        }

        $validated = $request->validate([
            'identity_token' => ['required', 'string'],
            'full_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $jwks = Http::get('https://appleid.apple.com/auth/keys')->throw()->json();
            $payload = JWT::decode($validated['identity_token'], JWK::parseKeySet($jwks));
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'identity_token' => ['Could not verify this Apple sign-in. Please try again.'],
            ]);
        }

        if ($payload->iss !== 'https://appleid.apple.com' || $payload->aud !== $bundleId) {
            throw ValidationException::withMessages([
                'identity_token' => ['This Apple sign-in could not be verified.'],
            ]);
        }

        $user = User::where('apple_id', $payload->sub)->first()
            ?? User::where('email', $payload->email ?? null)->first();

        $isNew = ! $user;

        if (! $user) {
            $user = User::create([
                'name' => $validated['full_name'] ?? Str::before($payload->email, '@'),
                'email' => $payload->email,
                'password' => Str::random(40),
                'apple_id' => $payload->sub,
            ]);
        } elseif (! $user->apple_id) {
            // Same reasoning as Google linking: Apple just proved real ownership of this email,
            // stronger than our own signup form, so the old password can no longer be trusted.
            $user->tokens()->delete();
            $user->update([
                'apple_id' => $payload->sub,
                'password' => Str::random(40),
            ]);
        }

        $token = $user->createToken('studypilot-mobile')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token, 'is_new' => $isNew]);
    }
}
