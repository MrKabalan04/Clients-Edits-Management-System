<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Update the authenticated administrator's password.
     */
    public function updatePassword(UpdateAdminPasswordRequest $request): JsonResponse
    {
        $request->user()->forceFill([
            'password' => Hash::make($request->validated('new_password')),
        ])->save();

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Authenticate an administrator using the SPA session.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json([
                'success' => false,
                'message' => 'A stateful browser session is required.',
            ], 419);
        }

        $credentials = $request->validated();

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators are allowed to authenticate.',
            ], 403);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Invalidate the authenticated SPA session.
     */
    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()?->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
