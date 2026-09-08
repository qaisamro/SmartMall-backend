<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index()
    {
        return response()->json(User::with('roles')->latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['customer', 'mall-owner', 'super-admin'])],
            'mall_name' => 'nullable|string|max:255',
        ]);

        // Create user first
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        // Auto-create a mall if mall_name is provided and role is mall-owner
        if ($request->filled('mall_name') && $request->role === 'mall-owner') {
            $mall = \App\Models\Mall::create([
                'owner_id' => $user->id,
                'name_ar'  => $request->mall_name,
                'name_en'  => $request->mall_name,
                'slug'     => \Illuminate\Support\Str::slug($request->mall_name) . '-' . time(),
                'status'   => 'active',
            ]);
            // Link user to the mall
            $user->update(['mall_id' => $mall->id]);
        }

        return response()->json([
            'message' => 'User created successfully',
            'user'    => $user->load('roles', 'mall')
        ], 201);
    }

    public function show($id)
    {
        $user = User::with('roles', 'mall')->findOrFail($id);
        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => ['sometimes', 'required', Rule::in(['customer', 'mall-owner', 'super-admin', 'admin'])],
        ]);
        if ($request->filled('name')) $user->name = $request->name;
        if ($request->filled('email')) $user->email = $request->email;
        if ($request->filled('password')) $user->password = Hash::make($request->password);
        $user->save();
        if ($request->filled('role')) $user->syncRoles([$request->role]);
        return response()->json($user->load('roles', 'mall'));
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        if ($user->hasRole('super-admin')) {
            return response()->json(['message' => 'Cannot delete a super admin'], 403);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }
}
