<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\StudentClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomPortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_login_creates_a_web_session(): void
    {
        $user = User::factory()->create([
            'email' => 'instructor@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'instructor'])->id,
            'password' => 'password',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('portal.dashboard'));
        $this->get('/dashboard')->assertOk()->assertSee($user->name);
    }

    public function test_authenticated_root_redirects_to_the_dashboard(): void
    {
        $user = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'instructor'])->id]);

        $this->actingAs($user, 'web')->get('/')->assertRedirect(route('portal.dashboard'));
    }

    public function test_admin_can_clear_project_data_while_preserving_roles_and_admin_instructor_accounts(): void
    {
        $admin = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'admin'])->id]);
        $instructor = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'instructor'])->id]);
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'join_code' => 'WEB-A-ABCDE', 'status' => 'active', 'created_by' => $instructor->id, 'instructor_id' => $instructor->id]);
        $student = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'student'])->id, 'class_id' => $class->id]);
        $category = Category::create(['class_id' => $class->id, 'name' => 'Phones', 'status' => 'active']);
        Product::create(['class_id' => $class->id, 'category_id' => $category->id, 'created_by' => $student->id, 'name' => 'Phone', 'sku' => 'PHONE-1', 'regular_price' => 100, 'quantity' => 1, 'status' => 'active']);

        $this->actingAs($admin, 'web')->post('/admin/clear-data', ['confirmation' => 'CLEAR'])->assertRedirect(route('portal.admin'));

        $this->assertDatabaseCount('classes', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('users', ['id' => $instructor->id]);
        $this->assertDatabaseHas('roles', ['name' => 'student']);
    }

    public function test_instructor_class_has_a_unique_join_code_and_students_can_register_with_it(): void
    {
        $instructor = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'instructor'])->id]);
        $this->actingAs($instructor, 'api');
        $class = $this->postJson('/api/classes', ['name' => 'Web Development A', 'code' => 'WEB-A'])->assertCreated()->json('data');

        $this->assertMatchesRegularExpression('/^WEB-A-[A-Z0-9]{5}$/', $class['join_code']);
        $this->postJson('/api/student/register', ['name' => 'Sokha', 'email' => 'sokha@example.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'join_code' => $class['join_code']])->assertCreated()->assertJsonPath('data.user.class_id', $class['id']);
    }

    public function test_class_documentation_and_class_detail_reject_another_instructor(): void
    {
        $owner = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'instructor'])->id]);
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'join_code' => 'WEB-A-ABCDE', 'status' => 'active', 'created_by' => $owner->id, 'instructor_id' => $owner->id]);
        $other = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'instructor'])->id]);
        $this->actingAs($other, 'api');

        $this->getJson('/api/classes/'.$class->id)->assertForbidden();
        $this->getJson('/api/classes/'.$class->id.'/documentation')->assertForbidden();
    }

    public function test_update_endpoints_use_put_and_do_not_accept_patch(): void
    {
        $instructor = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'instructor'])->id]);
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'join_code' => 'WEB-A-ABCDE', 'status' => 'active', 'created_by' => $instructor->id, 'instructor_id' => $instructor->id]);
        $instructor->update(['class_id' => $class->id]);
        $category = Category::create(['class_id' => $class->id, 'name' => 'Phones', 'status' => 'active']);
        $this->actingAs($instructor, 'api');

        $this->putJson('/api/categories/'.$category->id, ['name' => 'Computers'])->assertOk()->assertJsonPath('data.name', 'Computers');
        $this->patchJson('/api/categories/'.$category->id, ['name' => 'Tablets'])->assertStatus(405);
    }

    public function test_portal_documentation_renders_endpoint_details(): void
    {
        $instructor = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'instructor'])->id]);
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'join_code' => 'WEB-A-ABCDE', 'status' => 'active', 'created_by' => $instructor->id, 'instructor_id' => $instructor->id]);

        $this->actingAs($instructor, 'web')
            ->get('/classes/'.$class->id.'/api')
            ->assertOk()
            ->assertSee('Request headers')
            ->assertSee('Success response')
            ->assertSee('Error response');
    }

    public function test_portal_documentation_can_be_downloaded_as_a_pdf(): void
    {
        $instructor = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'instructor'])->id]);
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'join_code' => 'WEB-A-ABCDE', 'status' => 'active', 'created_by' => $instructor->id, 'instructor_id' => $instructor->id]);

        $this->actingAs($instructor, 'web')
            ->get('/classes/'.$class->id.'/api/download')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=api-documentation-web-a.pdf');
    }
}
