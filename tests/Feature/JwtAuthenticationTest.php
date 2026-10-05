<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JwtAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_jwt_that_authenticates_a_protected_route(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'email' => 'admin@test.local',
            'password' => Hash::make('password123'),
        ]);

        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()
            ->assertJsonStructure(['message', 'status', 'data' => ['access_token', 'token_type', 'expires_in', 'user' => ['id', 'name', 'email', 'role', 'class_id']]])
            ->assertJsonPath('data.user.role', 'admin')
            ->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }
}
