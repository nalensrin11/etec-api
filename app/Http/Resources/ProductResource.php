<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function with(Request $request): array
    {
        $created = $this->resource->wasRecentlyCreated;

        return ['message' => $created ? 'Product created successfully.' : 'Product retrieved successfully.', 'status' => $created ? 201 : 200];
    }

    public function toArray(Request $request): array
    {
        $price = $this->sale_price ?? $this->regular_price;
        $image = fn ($item) => $item ? [
            'id' => $item->id,
            'url' => rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.ltrim($item->image_path, '/'),
            'is_primary' => (bool) $item->is_primary,
            'sort_order' => $item->sort_order,
        ] : null;

        return ['id' => $this->id, 'name' => $this->name, 'sku' => $this->sku, 'description' => $this->description, 'regular_price' => (float) $this->regular_price, 'sale_price' => $this->sale_price === null ? null : (float) $this->sale_price, 'effective_price' => (float) $price, 'is_on_sale' => $this->sale_price !== null && $this->sale_price < $this->regular_price, 'quantity' => $this->quantity, 'status' => $this->status, 'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]), 'class' => $this->whenLoaded('studentClass', fn () => ['id' => $this->studentClass->id, 'name' => $this->studentClass->name, 'code' => $this->studentClass->code]), 'created_by' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]), 'images' => $this->whenLoaded('images', fn () => $this->images->map($image)->values()), 'primary_image' => $this->whenLoaded('primaryImage', fn () => $image($this->primaryImage)), 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
