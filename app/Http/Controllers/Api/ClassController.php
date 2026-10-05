<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentClass;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isInstructor(), 403);
        $query = StudentClass::query()->with('creator:id,name');
        if ($request->user()->isInstructor()) {
            $query->where(fn ($query) => $query->where('instructor_id', $request->user()->id)->orWhere('created_by', $request->user()->id));
        }

        return $this->paginated($query->paginate(), 'Classes retrieved successfully.');
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isInstructor(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:255', 'unique:classes,code'], 'status' => ['sometimes', Rule::in(['active', 'inactive'])]]);
        $class = StudentClass::create([
            ...$data,
            'created_by' => $request->user()->id,
            'instructor_id' => $request->user()->id,
            'join_code' => $this->joinCode($data['code']),
        ]);
        if ($request->user()->isInstructor() && ! $request->user()->class_id) {
            $request->user()->update(['class_id' => $class->id]);
        }

        return $this->success($class, 'Class created successfully.', 201);
    }

    public function update(Request $request, StudentClass $class)
    {
        abort_unless($request->user()->isAdmin() || ($request->user()->isInstructor() && ($class->instructor_id === $request->user()->id || $class->created_by === $request->user()->id)), 403);
        $class->update($request->validate(['name' => ['sometimes', 'string', 'max:255'], 'code' => ['sometimes', 'string', 'max:255', Rule::unique('classes', 'code')->ignore($class)], 'status' => ['sometimes', Rule::in(['active', 'inactive'])]]));

        return $this->success($class, 'Class updated successfully.');
    }

    public function show(Request $request, StudentClass $class)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($user->isInstructor() && ($class->instructor_id === $user->id || $class->created_by === $user->id)) || ($user->isStudent() && $user->class_id === $class->id), 403);

        return $this->success($class->loadCount(['users', 'products'])->load('instructor:id,name,email'), 'Class retrieved successfully.');
    }

    private function joinCode(string $code): string
    {
        do {
            $joinCode = Str::upper(Str::slug($code)).'-'.Str::upper(Str::random(5));
        } while (StudentClass::where('join_code', $joinCode)->exists());

        return $joinCode;
    }
}
