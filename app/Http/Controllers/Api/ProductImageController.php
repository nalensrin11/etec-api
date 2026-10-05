<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductImageRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    private function assertOwned(Product $product, ProductImage $image): void
    {
        abort_unless($image->product_id === $product->id, 404);
    }

    public function store(StoreProductImageRequest $request, Product $product)
    {
        $remaining = 10 - $product->images()->count();
        abort_if(count($request->file('images')) > $remaining, 422, 'A product may have at most 10 images.');
        DB::transaction(function () use ($request, $product) {
            $first = ! $product->images()->exists();
            $start = ((int) $product->images()->max('sort_order')) + 1;
            foreach ($request->file('images') as $i => $file) {
                $product->images()->create(['image_path' => $file->store('products', 'public'), 'is_primary' => $first && $i === 0, 'sort_order' => $start + $i]);
            }
        });

        return new ProductResource($product->fresh()->load(['category', 'studentClass', 'creator', 'images', 'primaryImage']));
    }

    public function destroy(Request $request, Product $product, ProductImage $image)
    {
        $this->authorize('update', $product);
        $this->assertOwned($product, $image);
        DB::transaction(function () use ($product, $image) {
            $wasPrimary = $image->is_primary;
            Storage::disk('public')->delete($image->image_path);
            $image->delete();
            if ($wasPrimary && ($replacement = $product->images()->orderBy('sort_order')->first())) {
                $replacement->update(['is_primary' => true]);
            }
        });

        return $this->success(null, 'Product image deleted successfully.');
    }

    public function primary(Request $request, Product $product, ProductImage $image)
    {
        $this->authorize('update', $product);
        $this->assertOwned($product, $image);
        DB::transaction(function () use ($product, $image) {
            $product->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });

        return new ProductResource($product->fresh()->load(['category', 'studentClass', 'creator', 'images', 'primaryImage']));
    }
}
