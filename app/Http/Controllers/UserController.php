<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // 🧾 List all users with roles (Admin-only)
    public function index()
    {
        return User::with('role')->get();
    }

    // ➕ Create a new user (by admin)
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role_id' => 'required|exists:roles,id',
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return response()->json($user, 201);
    }

    // ✏️ Update user details
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $user->update($data);

        return response()->json(['message' => 'User updated', 'user' => $user]);
    }

    // ✅ Update authenticated user's name & email
    public function updateMe(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }


    // ❌ Delete user
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
