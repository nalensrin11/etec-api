<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\StudentClass;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PortalController extends Controller
{
    public function loginForm()
    {
        return view('portal.auth', ['mode' => 'login']);
    }

    public function instructorRegisterForm()
    {
        return view('portal.auth', ['mode' => 'instructor']);
    }

    public function studentRegisterForm()
    {
        return view('portal.auth', ['mode' => 'student']);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::guard('web')->attempt([...$credentials, 'status' => 'active'])) {
            return back()->withErrors(['email' => 'The provided credentials are incorrect.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }

    public function registerInstructor(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'min:8', 'confirmed']]);
        $user = User::create([...$data, 'role_id' => Role::firstOrCreate(['name' => 'instructor'])->id, 'status' => 'active']);
        Auth::guard('web')->login($user);

        return redirect()->route('portal.dashboard');
    }

    public function registerStudent(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'min:8', 'confirmed'], 'join_code' => ['required', 'exists:classes,join_code']]);
        $class = StudentClass::where('join_code', $data['join_code'])->where('status', 'active')->firstOrFail();
        $user = User::create([...$data, 'role_id' => Role::firstOrCreate(['name' => 'student'])->id, 'class_id' => $class->id, 'status' => 'active']);
        Auth::guard('web')->login($user);

        return redirect()->route('portal.dashboard');
    }

    public function dashboard(Request $request)
    {
        $user = $request->user('web')->load('role');
        if ($user->isAdmin()) {
            return redirect()->route('portal.admin');
        }
        $classes = $user->isInstructor() ? StudentClass::where(fn ($q) => $q->where('instructor_id', $user->id)->orWhere('created_by', $user->id))->withCount(['users', 'products'])->get() : collect($user->class_id ? [StudentClass::with(['instructor:id,name'])->withCount('products')->findOrFail($user->class_id)] : []);

        return view('portal.dashboard', compact('user', 'classes'));
    }

    public function storeClass(Request $request)
    {
        $user = $request->user('web');
        abort_unless($user->isInstructor(), 403);
        $data = $request->validate(['name' => ['required', 'max:255'], 'code' => ['required', 'max:255', 'unique:classes,code'], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        do {
            $joinCode = Str::upper(Str::slug($data['code'])).'-'.Str::upper(Str::random(5));
        } while (StudentClass::where('join_code', $joinCode)->exists());
        $class = StudentClass::create([...$data, 'join_code' => $joinCode, 'created_by' => $user->id, 'instructor_id' => $user->id]);
        if (! $user->class_id) {
            $user->update(['class_id' => $class->id]);
        }

        return redirect()->route('portal.class.show', $class);
    }

    public function showClass(Request $request, StudentClass $class)
    {
        $this->authorizeClass($request->user('web'), $class);

        return view('portal.class', ['class' => $class->load('instructor:id,name'), 'students' => $class->users()->whereHas('role', fn ($q) => $q->where('name', 'student'))->withCount('createdProducts')->get(), 'products' => $class->products()->with('creator:id,name')->latest()->get()]);
    }

    public function docs(Request $request, StudentClass $class)
    {
        $this->authorizeClass($request->user('web'), $class);

        return view('portal.docs', compact('class'));
    }

    public function downloadDocs(Request $request, StudentClass $class)
    {
        $this->authorizeClass($request->user('web'), $class);

        return Pdf::loadView('portal.docs-pdf', [
            'class' => $class,
            'apiUrl' => url('/api'),
            'isInstructor' => $request->user('web')->isInstructor() || $request->user('web')->isAdmin(),
        ])->setPaper('a4')->download('api-documentation-'.Str::slug($class->code).'.pdf');
    }

    public function join(Request $request)
    {
        $user = $request->user('web');
        abort_unless($user->isStudent(), 403);
        $data = $request->validate(['join_code' => ['required', 'exists:classes,join_code']]);
        $class = StudentClass::where('join_code', $data['join_code'])->where('status', 'active')->firstOrFail();
        $user->update(['class_id' => $class->id]);

        return redirect()->route('portal.class.show', $class);
    }

    public function admin(Request $request)
    {
        $this->authorizeAdmin($request->user('web'));

        return view('portal.admin', [
            'counts' => [
                'roles' => Role::count(),
                'admins' => User::whereHas('role', fn ($query) => $query->where('name', 'admin'))->count(),
                'instructors' => User::whereHas('role', fn ($query) => $query->where('name', 'instructor'))->count(),
                'students' => User::whereHas('role', fn ($query) => $query->where('name', 'student'))->count(),
                'classes' => StudentClass::count(),
                'categories' => Category::count(),
                'products' => Product::count(),
                'images' => ProductImage::count(),
            ],
        ]);
    }

    public function clearProjectData(Request $request)
    {
        $this->authorizeAdmin($request->user('web'));
        $request->validate(['confirmation' => ['required', 'in:CLEAR']]);
        $paths = ProductImage::pluck('image_path')->all();

        DB::transaction(function () {
            Product::query()->delete();
            Category::query()->delete();
            StudentClass::query()->delete();
            User::whereDoesntHave('role', fn ($query) => $query->whereIn('name', ['admin', 'instructor']))->delete();

            foreach (['cache', 'jobs', 'password_reset_tokens'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        });

        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }

        return redirect()->route('portal.admin')->with('status', 'Project data cleared. Roles and admin/instructor accounts were preserved.');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    private function authorizeClass(User $user, StudentClass $class): void
    {
        abort_unless($user->isAdmin() || ($user->isInstructor() && ($class->instructor_id === $user->id || $class->created_by === $user->id)) || ($user->isStudent() && $user->class_id === $class->id), 403);
    }

    private function authorizeAdmin(User $user): void
    {
        abort_unless($user->isAdmin(), 403);
    }
}
