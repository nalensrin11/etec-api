<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return ['category_id' => ['sometimes', 'integer', 'exists:categories,id'], 'name' => ['sometimes', 'string', 'max:255'], 'sku' => ['sometimes', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)], 'description' => ['nullable', 'string'], 'regular_price' => ['sometimes', 'numeric', 'min:0'], 'sale_price' => ['nullable', 'numeric', 'min:0', 'lte:regular_price'], 'quantity' => ['sometimes', 'integer', 'min:0'], 'status' => ['sometimes', Rule::in(['active', 'inactive'])]];
    }
}
