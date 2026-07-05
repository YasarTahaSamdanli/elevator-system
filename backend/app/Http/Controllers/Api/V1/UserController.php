<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = ListQuery::for(User::query()->with('roles'), $request)
            ->filterable([
                'is_active',
                'role' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'roles',
                    fn (Builder $role) => is_array($value)
                        ? $role->whereIn('name', $value)
                        : $role->where('name', $value),
                ),
            ])
            ->searchable(['name', 'email', 'phone'])
            ->sortable(['name', 'email', 'is_active', 'created_at', 'updated_at'])
            ->dateRange('created_at')
            ->paginate();

        return ApiResponse::paginated($users, UserResource::class);
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(
            data: new UserResource($user->load('roles')),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = $data['role'];
        unset($data['role']);

        $user = User::create($data);
        $user->syncRoles([$role]);

        return ApiResponse::success(
            data: new UserResource($user->load('roles')),
            message: 'User created successfully.',
            status: 201,
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('role', $data)) {
            $user->syncRoles([$data['role']]);
            unset($data['role']);
        }

        $user->update($data);

        return ApiResponse::success(
            data: new UserResource($user->fresh()->load('roles')),
            message: 'User updated successfully.',
        );
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return ApiResponse::error(
                message: 'You cannot delete your own account.',
                code: 'CANNOT_DELETE_SELF',
                status: 409,
            );
        }

        // Revoke API tokens so the soft-deleted user is locked out immediately.
        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success(
            message: 'User deleted successfully.',
        );
    }
}
