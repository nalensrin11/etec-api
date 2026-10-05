<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function canManage(Request $request, ?User $user = null): void
    {
        $actor = $request->user();
        abort_unless($actor->isAdmin() || ($actor->isInstructor() && $actor->class_id && (! $user || ($user->class_id === $actor->class_id && $user->role?->name === 'student'))), 403);
    }

    public function index(Request $request)
    {
        $this->canManage($request);
        $query = User::query()->with(['role:id,name', 'studentClass:id,name,code']);
        if ($request->user()->isInstructor()) {
            $query->where('class_id', $request->user()->class_id)->whereHas('role', fn ($q) => $q->where('name', 'student'));
        }

        return $this->paginated($query->paginate(), 'Users retrieved successfully.');
    }

    public function store(Request $request)
    {
        $this->canManage($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'string', 'min:8'], 'role_id' => ['nullable', 'integer', 'exists:roles,id'], 'class_id' => ['nullable', 'integer', 'exists:classes,id'], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        if ($request->user()->isInstructor()) {
            $data['role_id'] = Role::firstOrCreate(['name' => 'student'])->id;
            $data['class_id'] = $request->user()->class_id;
        }

        return $this->success(User::create($data), 'User created successfully.', 201);
    }

    public function update(Request $request, User $user)
    {
        $this->canManage($request, $user);
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:255'], 'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user)], 'password' => ['nullable', 'string', 'min:8'], 'role_id' => ['nullable', 'integer', 'exists:roles,id'], 'class_id' => ['nullable', 'integer', 'exists:classes,id'], 'status' => ['sometimes', Rule::in(['active', 'inactive'])]]);
        if ($request->user()->isInstructor()) {
            unset($data['role_id'], $data['class_id']);
        }
        $user->update($data);

        return $this->success($user, 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->canManage($request, $user);
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');
        $user->delete();

        return $this->success(null, 'User deleted successfully.');
    }
}
