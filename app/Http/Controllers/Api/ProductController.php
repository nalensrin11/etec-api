<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function publicIndex(Request $request)
    {
        $data = $request->validate(['class_id' => ['required', 'integer', 'exists:classes,id'], 'search' => 'nullable|string', 'category_id' => 'nullable|integer', 'promotion' => 'nullable|boolean', 'min_price' => 'nullable|numeric', 'max_price' => 'nullable|numeric', 'sort' => 'nullable|in:name,regular_price,price,low_price,high_price,created_at', 'direction' => 'nullable|in:asc,desc', 'per_page' => 'nullable|integer|min:1|max:100']);
        $query = Product::query()->with(['category', 'studentClass', 'creator', 'images', 'primaryImage'])->where('status', 'active')->where('class_id', $data['class_id']);
        foreach (['category_id'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (! empty($data['search'])) {
            $query->where('name', 'like', '%'.$data['search'].'%');
        }
        if (isset($data['min_price'])) {
            $query->whereRaw('COALESCE(sale_price, regular_price) >= ?', [$data['min_price']]);
        }
        if (isset($data['max_price'])) {
            $query->whereRaw('COALESCE(sale_price, regular_price) <= ?', [$data['max_price']]);
        }
        if (! empty($data['promotion'])) {
            $query->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'regular_price');
        }
        $sort = $data['sort'] ?? 'created_at';
        $direction = $data['direction'] ?? 'desc';
        if ($sort === 'low_price') {
            $sort = 'price';
            $direction = 'asc';
        } elseif ($sort === 'high_price') {
            $sort = 'price';
            $direction = 'desc';
        }
        if ($sort === 'price') {
            $query->orderByRaw("COALESCE(sale_price, regular_price) {$direction}");
        } else {
            $query->orderBy($sort, $direction);
        }

        return new ProductCollection($query->paginate($data['per_page'] ?? 15)->withQueryString());
    }

    public function publicShow(Request $request, Product $product)
    {
        $data = $request->validate(['class_id' => ['required', 'integer', 'exists:classes,id']]);
        abort_unless($product->status === 'active' && $product->class_id === (int) $data['class_id'], 404);

        return new ProductResource($this->loaded($product));
    }

    public function publicPromotions(Request $request)
    {
        $request->merge(['promotion' => true]);

        return $this->publicIndex($request);
    }

    private function loaded(Product $product): Product
    {
        return $product->load(['category', 'studentClass', 'creator', 'images', 'primaryImage']);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isInstructor() || ($request->user()->isStudent() && $request->user()->class_id), 403);
        $data = $request->validate(['search' => 'nullable|string', 'category_id' => 'nullable|integer', 'class_id' => 'nullable|integer', 'status' => 'nullable|in:active,inactive', 'min_price' => 'nullable|numeric', 'max_price' => 'nullable|numeric', 'sort' => 'nullable|in:name,sku,regular_price,quantity,created_at', 'direction' => 'nullable|in:asc,desc', 'per_page' => 'nullable|integer|min:1|max:100']);
        $query = Product::query()->with(['category', 'studentClass', 'creator', 'images', 'primaryImage']);
        if (! $request->user()->isAdmin()) {
            $query->where('class_id', $request->user()->class_id);
        } elseif (isset($data['class_id'])) {
            $query->where('class_id', $data['class_id']);
        }
        foreach (['category_id', 'status'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (! empty($data['search'])) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$data['search'].'%')->orWhere('sku', 'like', '%'.$data['search'].'%'));
        }
        if (isset($data['min_price'])) {
            $query->whereRaw('COALESCE(sale_price, regular_price) >= ?', [$data['min_price']]);
        }
        if (isset($data['max_price'])) {
            $query->whereRaw('COALESCE(sale_price, regular_price) <= ?', [$data['max_price']]);
        }

        return new ProductCollection($query->orderBy($data['sort'] ?? 'created_at', $data['direction'] ?? 'desc')->paginate($data['per_page'] ?? 15)->withQueryString());
    }

    public function store(StoreProductRequest $request)
    {
        $product = DB::transaction(function () use ($request) {
            $input = $request->validated();
            unset($input['images']);
            $input['class_id'] = $request->user()->isAdmin() ? $input['class_id'] : $request->user()->class_id;
            $input['created_by'] = $request->user()->id;
            $category = Category::findOrFail($input['category_id']);
            if ($category->class_id !== (int) $input['class_id']) {
                throw ValidationException::withMessages(['category_id' => ['The selected category does not belong to this class.']]);
            }
            $product = Product::create($input);
            $images = $request->file('images', $request->file('image', []));
            foreach ($images as $i => $file) {
                $product->images()->create(['image_path' => $file->store('products', 'public'), 'is_primary' => $i === 0, 'sort_order' => $i + 1]);
            }

            return $product;
        });

        return (new ProductResource($this->loaded($product)))->response()->setStatusCode(201);
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        return new ProductResource($this->loaded($product));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return new ProductResource($this->loaded($product));
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorize('delete', $product);
        DB::transaction(function () use ($product) {
            $product->images()->get()->each(fn ($image) => Storage::disk('public')->delete($image->image_path));
            $product->delete();
        });

        return $this->success(null, 'Product deleted successfully.');
    }
}
