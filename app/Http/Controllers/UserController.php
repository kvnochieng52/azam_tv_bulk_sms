<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    // public function index()
    // {
    //     $users = User::with('roles')->get();
    //     $roles = Role::all();

    //     return Inertia::render('Users/Index', [
    //         'users' => $users,
    //         'roles' => $roles
    //     ]);
    // }

    public function index(Request $request)
    {
        $search = $request->query('search', '');
        $roleFilter = $request->query('role', '');
        $statusFilter = $request->query('status', '');

        $users = User::with('roles')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('telephone', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter, function ($query, $roleFilter) {
                $query->whereHas('roles', function ($q) use ($roleFilter) {
                    $q->where('name', $roleFilter);
                });
            })
            ->when($statusFilter !== '', function ($query) use ($statusFilter) {
                $query->where('is_active', $statusFilter === 'active');
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('updated_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::all();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['search', 'role', 'status'])
        ]);
    }

    public function create()
    {
        $roles = Role::all();
        return Inertia::render('Users/Create', [
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'full_names' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'telephone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,id',
            'active' => 'required|boolean',
        ]);

        $user = User::create([
            'name' => $request->full_names,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'password' => bcrypt($request->password),
            'is_active' => $request->active,
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id
        ]);

        $role = Role::findById($request->role);
        $user->assignRole($role);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        return Inertia::render('Users/Show', [
            'user' => $user->load('roles')
        ]);
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        return Inertia::render('Users/Edit', [
            'user' => $user->load('roles'),
            'roles' => $roles
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'full_names' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'telephone' => 'required|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|exists:roles,id',
            'active' => 'required|boolean',
        ]);

        $user->update([
            'name' => $request->full_names,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'is_active' => $request->active,
            'updated_by' => auth()->user()->id
        ]);

        if ($request->password) {
            $user->update(['password' => bcrypt($request->password)]);
        }

        $role = Role::findById($request->role);
        $user->syncRoles([$role]);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
