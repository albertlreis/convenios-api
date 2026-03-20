<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User|null $user */
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Usuário inativo. Acesso bloqueado.',
            ], 403);
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'sucesso' => true,
            'data' => [
                'user' => UserResource::make($user->fresh())->resolve(),
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        return response()->json([
            'sucesso' => true,
            'data' => [
                'user' => UserResource::make($user)->resolve(),
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return response()->json([
            'sucesso' => true,
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'A senha atual informada é inválida.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'force_password_change' => false,
        ])->save();

        return response()->json([
            'sucesso' => true,
            'data' => [
                'user' => UserResource::make($user->fresh())->resolve(),
            ],
        ]);
    }
}
