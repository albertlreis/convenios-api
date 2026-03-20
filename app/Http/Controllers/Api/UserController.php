<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(0, min((int) $request->query('per_page', 15), 200));

        $query = User::query()->orderBy('name');

        if ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($perPage === 0) {
            $items = $query->get();
            $total = $items->count();

            return response()->json([
                'sucesso' => true,
                'data' => [
                    'results' => UserResource::collection($items)->resolve(),
                    'pagination' => [
                        'page' => 1,
                        'perPage' => 0,
                        'total' => $total,
                        'lastPage' => 1,
                    ],
                ],
            ]);
        }

        return UserResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function show(int $user): UserResource
    {
        return UserResource::make(
            User::query()->withTrashed()->findOrFail($user)
        );
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $payload = $request->validated();

        if (empty($payload['password'])) {
            unset($payload['password']);
        }

        $user->fill($payload);
        $user->save();

        return $this->show($user->id);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(status: 204);
    }

    public function restore(int $user): UserResource
    {
        $user = User::query()->withTrashed()->findOrFail($user);

        if ($user->trashed()) {
            $user->restore();
        }

        return $this->show($user->id);
    }

    public function updateStatus(Request $request, User $user): UserResource
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->forceFill([
            'is_active' => $validated['is_active'],
        ])->save();

        return $this->show($user->id);
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): UserResource
    {
        $validated = $request->validated();

        $user->forceFill([
            'password' => $validated['password'],
            'force_password_change' => $validated['force_password_change'] ?? true,
        ])->save();

        return $this->show($user->id);
    }
}
