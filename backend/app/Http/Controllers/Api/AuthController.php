<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\LoginActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function __construct(private readonly LoginActivityService $activity)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'uuid'              => (string) Str::uuid(),
            'email'             => strtolower($data['email']),
            'password_hash'     => Hash::make($data['password']),
            'first_name'        => $data['first_name'],
            'last_name'         => $data['last_name'],
            'display_name'      => trim($data['first_name'] . ' ' . substr($data['last_name'], 0, 1) . '.'),
            'locale'            => $data['locale']   ?? 'en',
            'currency'          => $data['currency'] ?? 'USD',
            'marketing_opt_in'  => (bool) ($data['marketing_opt_in'] ?? false),
            'privacy_consent_at' => now(),
            'status'            => 'active',
        ]);

        // Assign default 'guest' role
        if ($guestRole = Role::where('slug', 'guest')->first()) {
            $user->roles()->attach($guestRole->id, ['granted_at' => now()]);
        }

        $token = $user->createToken('signup', ['*'], now()->addDays(30))->plainTextToken;

        $this->activity->record($user, $request, 'login_success');

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('email', strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            if ($user) {
                $user->increment('failed_login_count');
                $this->activity->record($user, $request, 'login_failed');
            }
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account is not active. Please contact support.'],
            ]);
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            throw ValidationException::withMessages([
                'email' => ['This account is temporarily locked. Try again later.'],
            ]);
        }

        $user->update([
            'failed_login_count' => 0,
            'last_login_at'      => now(),
            'last_login_ip'      => $request->ip(),
        ]);

        $token = $user->createToken($data['device_label'] ?? 'web', ['*'], now()->addDays(30))
            ->plainTextToken;

        $this->activity->record($user, $request, 'login_success');

        return response()->json([
            'user'  => new UserResource($user->load('roles')),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }
}
