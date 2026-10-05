<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function publicIndex(Request $request)
    {
        $data = $request->validate(['class_id' => ['required', 'integer', 'exists:classes,id']]);

        return $this->paginated(Category::query()->where('class_id', $data['class_id'])->where('status', 'active')->orderBy('name')->paginate(), 'Categories retrieved successfully.');
    }

    private function manage(Request $request, ?Category $category = null): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($user->isInstructor() && $user->class_id && (! $category || $category->class_id === $user->class_id)), 403);
    }

    public function index(Request $request)
    {
        $this->manage($request);
        $data = $request->validate(['class_id' => ['nullable', 'integer', 'exists:classes,id']]);
        $query = Category::query()->orderBy('name');
        if ($request->user()->isInstructor()) {
            $query->where('class_id', $request->user()->class_id);
        } elseif (isset($data['class_id'])) {
            $query->where('class_id', $data['class_id']);
        }

        return $this->paginated($query->paginate(), 'Categories retrieved successfully.');
    }

    public function store(Request $request)
    {
        $this->manage($request);
        $data = $request->validate(['class_id' => [Rule::requiredIf($request->user()->isAdmin()), 'nullable', 'integer', 'exists:classes,id'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        $data['class_id'] = $request->user()->isAdmin() ? $data['class_id'] : $request->user()->class_id;

        return $this->success(Category::create($data), 'Category created successfully.', 201);
    }

    public function update(Request $request, Category $category)
    {
        $this->manage($request, $category);
        $category->update($request->validate(['name' => ['sometimes', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'status' => ['sometimes', Rule::in(['active', 'inactive'])]]));

        return $this->success($category, 'Category updated successfully.');
    }

    public function destroy(Request $request, Category $category)
    {
        $this->manage($request, $category);
        abort_if($category->products()->exists(), 422, 'A category with products cannot be deleted.');
        $category->delete();

        return $this->success(null, 'Category deleted successfully.');
    }
}
