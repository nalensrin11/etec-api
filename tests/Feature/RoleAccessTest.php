<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\StudentClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_instructor_who_can_create_a_class_and_student(): void
    {
        $this->postJson('/api/register', ['name' => 'Ivy', 'email' => 'ivy@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertCreated();
        $instructor = User::where('email', 'ivy@example.com')->firstOrFail();
        $this->assertSame('instructor', $instructor->role->name);
        $this->actingAs($instructor, 'api');
        $class = $this->postJson('/api/classes', ['name' => 'Web A', 'code' => 'WEB-A'])->assertCreated()->json('data');
        $this->postJson('/api/users', ['name' => 'Student', 'email' => 'student@web.test', 'password' => 'password123', 'status' => 'active'])->assertCreated();
        $student = User::where('email', 'student@web.test')->firstOrFail();
        $this->assertSame($class['id'], $student->class_id);
        $this->assertSame('student', $student->role->name);
    }

    public function test_public_catalog_is_read_only_and_only_contains_active_records(): void
    {
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'status' => 'active']);
        $category = Category::create(['class_id' => $class->id, 'name' => 'Laptop', 'status' => 'active']);
        $owner = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'instructor'])->id, 'class_id' => $class->id]);
        Product::create(['class_id' => $class->id, 'category_id' => $category->id, 'created_by' => $owner->id, 'name' => 'Public', 'sku' => 'PUBLIC', 'regular_price' => 10, 'quantity' => 1, 'status' => 'active']);
        Product::create(['class_id' => $class->id, 'category_id' => $category->id, 'created_by' => $owner->id, 'name' => 'Hidden', 'sku' => 'HIDDEN', 'regular_price' => 10, 'quantity' => 1, 'status' => 'inactive']);
        $otherClass = StudentClass::create(['name' => 'Web B', 'code' => 'WEB-B', 'status' => 'active']);
        $otherCategory = Category::create(['class_id' => $otherClass->id, 'name' => 'Other Laptop', 'status' => 'active']);
        $otherOwner = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'instructor'])->id, 'class_id' => $otherClass->id]);
        Product::create(['class_id' => $otherClass->id, 'category_id' => $otherCategory->id, 'created_by' => $otherOwner->id, 'name' => 'Other Class Product', 'sku' => 'OTHER', 'regular_price' => 10, 'quantity' => 1, 'status' => 'active']);
        $this->getJson('/api/public/categories?class_id='.$class->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/public/products?class_id='.$class->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Public');
    }

    public function test_public_catalog_supports_detail_category_price_ordering_and_promotions(): void
    {
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'status' => 'active']);
        $laptop = Category::create(['class_id' => $class->id, 'name' => 'Laptop', 'status' => 'active']);
        $phone = Category::create(['class_id' => $class->id, 'name' => 'Phone', 'status' => 'active']);
        $owner = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'instructor'])->id, 'class_id' => $class->id]);
        $sale = Product::create(['class_id' => $class->id, 'category_id' => $laptop->id, 'created_by' => $owner->id, 'name' => 'Sale Laptop', 'sku' => 'SALE', 'regular_price' => 100, 'sale_price' => 70, 'quantity' => 1, 'status' => 'active']);
        Product::create(['class_id' => $class->id, 'category_id' => $laptop->id, 'created_by' => $owner->id, 'name' => 'Full Laptop', 'sku' => 'FULL', 'regular_price' => 80, 'quantity' => 1, 'status' => 'active']);
        Product::create(['class_id' => $class->id, 'category_id' => $phone->id, 'created_by' => $owner->id, 'name' => 'Phone', 'sku' => 'PHONE', 'regular_price' => 50, 'quantity' => 1, 'status' => 'active']);

        $this->getJson('/api/public/products/'.$sale->id.'?class_id='.$class->id)->assertOk()->assertJsonPath('data.is_on_sale', true)->assertJsonPath('data.effective_price', 70);
        $this->getJson('/api/public/products?class_id='.$class->id.'&category_id='.$laptop->id.'&sort=low_price')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'Sale Laptop');
        $this->getJson('/api/public/products?class_id='.$class->id.'&promotion=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Sale Laptop');
        $this->getJson('/api/public/products/promotions?class_id='.$class->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sale->id);
    }

    public function test_student_can_use_their_product_workspace_but_cannot_manage_categories(): void
    {
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'status' => 'active']);
        $student = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'student'])->id, 'class_id' => $class->id]);
        $this->actingAs($student, 'api');
        $this->getJson('/api/products')->assertOk();
        $this->postJson('/api/categories', ['name' => 'Phone', 'status' => 'active'])->assertForbidden();
    }
}
