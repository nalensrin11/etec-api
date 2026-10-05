<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Accept the older image[] field name as well as the documented images[] name.
        if (! $this->hasFile('images') && $this->hasFile('image')) {
            $this->files->set('images', $this->file('image'));
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && ($this->user()->isAdmin() || (($this->user()->isInstructor() || $this->user()->isStudent()) && $this->user()->class_id));
    }

    public function rules(): array
    {
        return ['class_id' => [Rule::requiredIf(fn () => $this->user()->isAdmin()), 'nullable', 'integer', 'exists:classes,id'], 'category_id' => ['required', 'integer', 'exists:categories,id'], 'name' => ['required', 'string', 'max:255'], 'sku' => ['required', 'string', 'max:255', 'unique:products,sku'], 'description' => ['nullable', 'string'], 'regular_price' => ['required', 'numeric', 'min:0'], 'sale_price' => ['nullable', 'numeric', 'min:0', 'lte:regular_price'], 'quantity' => ['required', 'integer', 'min:0'], 'status' => ['required', Rule::in(['active', 'inactive'])], 'images' => ['nullable', 'array', 'max:10'], 'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'image' => ['nullable', 'array', 'max:10'], 'image.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120']];
    }
}
