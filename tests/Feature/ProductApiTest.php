<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\StudentClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?StudentClass $class = null): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => $role])->id,
            'class_id' => $class?->id,
        ]);
    }

    private function category(StudentClass $class): Category
    {
        return Category::create(['class_id' => $class->id, 'name' => 'Laptop', 'status' => 'active']);
    }

    public function test_instructor_product_creation_uses_own_class_and_creates_primary_image(): void
    {
        Storage::fake('public');
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'status' => 'active']);
        $other = StudentClass::create(['name' => 'Web B', 'code' => 'WEB-B', 'status' => 'active']);
        $this->actingAs($this->user('instructor', $class), 'api');

        $response = $this->post('/api/products', ['class_id' => $other->id, 'created_by' => 999, 'category_id' => $this->category($class)->id, 'name' => 'MacBook', 'sku' => 'MAC-1', 'regular_price' => 1000, 'sale_price' => 900, 'quantity' => 2, 'status' => 'active', 'images' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.png')]]);

        $response->assertCreated()->assertJsonPath('status', 201)->assertJsonPath('data.class.id', $class->id)->assertJsonCount(2, 'data.images');
        $product = Product::firstOrFail();
        $this->assertSame($class->id, $product->class_id);
        $this->assertSame(2, $product->images()->count());
        $this->assertTrue($product->images()->where('is_primary', true)->firstOrFail()->is_primary);
        $product->images->each(fn ($image) => Storage::disk('public')->assertExists($image->image_path));
    }

    public function test_product_creation_accepts_image_array_alias_in_the_same_request(): void
    {
        Storage::fake('public');
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'status' => 'active']);
        $instructor = $this->user('instructor', $class);
        $this->actingAs($instructor, 'api');

        $this->post('/api/products', [
            'category_id' => $this->category($class)->id,
            'name' => 'Camera',
            'sku' => 'CAM-1',
            'regular_price' => 100,
            'quantity' => 1,
            'status' => 'active',
            'image' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.png')],
        ])->assertCreated()->assertJsonCount(2, 'data.images');

        $this->assertSame(2, Product::firstOrFail()->images()->count());
    }

    public function test_product_image_urls_use_the_public_storage_path(): void
    {
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'status' => 'active']);
        $instructor = $this->user('instructor', $class);
        $product = Product::create(['class_id' => $class->id, 'category_id' => $this->category($class)->id, 'created_by' => $instructor->id, 'name' => 'Phone', 'sku' => 'PHONE-1', 'regular_price' => 100, 'quantity' => 1, 'status' => 'active']);
        $product->images()->create(['image_path' => 'products/phone.png', 'is_primary' => true, 'sort_order' => 1]);

        $this->actingAs($instructor, 'api')
            ->getJson('/api/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.primary_image.url', 'http://localhost/storage/products/phone.png');
    }

    public function test_sale_price_must_not_exceed_regular_price(): void
    {
        $class = StudentClass::create(['name' => 'Web A', 'code' => 'WEB-A', 'status' => 'active']);
        $this->actingAs($this->user('instructor', $class), 'api');
        $this->postJson('/api/products', ['category_id' => $this->category($class)->id, 'name' => 'Phone', 'sku' => 'P-1', 'regular_price' => 100, 'sale_price' => 101, 'quantity' => 1, 'status' => 'active'])->assertUnprocessable()->assertJsonPath('message', 'The given data was invalid.')->assertJsonPath('status', 422)->assertJsonStructure(['errors' => ['sale_price']]);
    }

    public function test_instructor_only_lists_own_class_products_while_admin_lists_everything(): void
    {
        $a = StudentClass::create(['name' => 'A', 'code' => 'A', 'status' => 'active']);
        $b = StudentClass::create(['name' => 'B', 'code' => 'B', 'status' => 'active']);
        $category = $this->category($a);
        $owner = $this->user('instructor', $a);
        $foreign = $this->user('instructor', $b);
        Product::create(['class_id' => $a->id, 'category_id' => $category->id, 'created_by' => $owner->id, 'name' => 'Visible', 'sku' => 'A-1', 'regular_price' => 10, 'quantity' => 1, 'status' => 'active']);
        Product::create(['class_id' => $b->id, 'category_id' => $category->id, 'created_by' => $foreign->id, 'name' => 'Hidden', 'sku' => 'B-1', 'regular_price' => 20, 'quantity' => 1, 'status' => 'active']);
        $this->actingAs($owner, 'api');
        $this->getJson('/api/products?class_id='.$b->id)->assertJsonPath('status', 200)->assertJsonStructure(['meta'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Visible');
        $this->actingAs($this->user('admin'), 'api');
        $this->getJson('/api/products')->assertJsonCount(2, 'data');
    }

    public function test_deleting_primary_image_selects_replacement_and_cross_product_image_is_not_found(): void
    {
        Storage::fake('public');
        $class = StudentClass::create(['name' => 'A', 'code' => 'A', 'status' => 'active']);
        $user = $this->user('instructor', $class);
        $product = Product::create(['class_id' => $class->id, 'category_id' => $this->category($class)->id, 'created_by' => $user->id, 'name' => 'Item', 'sku' => 'I-1', 'regular_price' => 1, 'quantity' => 1, 'status' => 'active']);
        $first = $product->images()->create(['image_path' => 'products/first.jpg', 'is_primary' => true, 'sort_order' => 1]);
        $second = $product->images()->create(['image_path' => 'products/second.jpg', 'is_primary' => false, 'sort_order' => 2]);
        $other = Product::create(['class_id' => $class->id, 'category_id' => $product->category_id, 'created_by' => $user->id, 'name' => 'Other', 'sku' => 'O-1', 'regular_price' => 1, 'quantity' => 1, 'status' => 'active']);
        $foreignImage = $other->images()->create(['image_path' => 'products/other.jpg', 'is_primary' => true, 'sort_order' => 1]);
        $this->actingAs($user, 'api');
        $this->delete('/api/products/'.$product->id.'/images/'.$foreignImage->id)->assertNotFound();
        $this->delete('/api/products/'.$product->id.'/images/'.$first->id)->assertOk()->assertJsonPath('status', 200)->assertJsonPath('data', null);
        $this->assertTrue($second->fresh()->is_primary);
    }
}
