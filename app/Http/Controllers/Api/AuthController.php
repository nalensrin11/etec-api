<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\StudentClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user = User::create([...$data, 'role_id' => Role::firstOrCreate(['name' => 'instructor'])->id, 'status' => 'active']);

        return $this->success(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]], 'Instructor account registered. Create your class after logging in.', 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || $user->status !== 'active') {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }

        return $this->respondWithToken(auth('api')->login($user), $user);
    }

    public function registerStudent(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'join_code' => ['required', 'string', 'exists:classes,join_code'],
        ]);
        $class = StudentClass::where('join_code', $data['join_code'])->where('status', 'active')->firstOrFail();
        $user = User::create([
            ...$data,
            'role_id' => Role::firstOrCreate(['name' => 'student'])->id,
            'class_id' => $class->id,
            'status' => 'active',
        ]);

        return $this->success(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'class_id' => $class->id]], 'Student account registered and joined to class.', 201);
    }

    public function joinClass(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isStudent(), 403);
        $data = $request->validate(['join_code' => ['required', 'string', 'exists:classes,join_code']]);
        $class = StudentClass::where('join_code', $data['join_code'])->where('status', 'active')->firstOrFail();
        $user->update(['class_id' => $class->id]);

        return $this->success($class, 'Joined class successfully.');
    }

    public function logout(Request $request)
    {
        auth('api')->logout();

        return $this->success(null, 'Logged out successfully.');
    }

    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh(), auth('api')->user());
    }

    private function respondWithToken(string $token, User $user)
    {
        $user->loadMissing('role:id,name');

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name,
                'class_id' => $user->class_id,
            ],
        ], 'Authentication successful.');
    }
}
