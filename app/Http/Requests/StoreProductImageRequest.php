<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        return ['images' => ['required', 'array', 'min:1', 'max:10'], 'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120']];
    }
}
