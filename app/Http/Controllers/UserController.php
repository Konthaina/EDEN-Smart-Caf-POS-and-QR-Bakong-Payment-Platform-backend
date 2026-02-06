<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    // 🧾 List active (not deleted) users with roles
    public function index()
    {
        return User::with('role')->get(); // SoftDeletes scope will exclude trashed by default
    }

    // 🗑️ List only soft-deleted users
    public function trashed()
    {
        return User::onlyTrashed()->with('role')->get();
    }

    // ➕ Create a new user (by admin)
    public function store(Request $request)
    {
        /** @var User $auth */
        $auth = Auth::user();

        $emailInput = $request->input('email');
        if (is_string($emailInput)) {
            $email = strtolower($emailInput);
            $trashed = User::onlyTrashed()->where('email', $email)->first();
            if ($trashed) {
                return response()->json([
                    'message' => 'This email belongs to a deleted account. Please restore it or use another email.'
                ], 422);
            }
        }

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => [
                'required',
                'email',
                Rule::unique('users', 'email'),
            ],
            'password' => ['required', Password::min(8)],
            'role_id'  => 'required|exists:roles,id',
        ]);

        // Block creating a user with same or higher privilege (lower role_id = higher privilege)
        if ((int)$data['role_id'] <= (int)$auth->role_id) {
            return response()->json([
                'message' => 'You cannot create a user with the same or higher role than yours.'
            ], 403);
        }

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return response()->json($user->load('role'), 201);
    }

    // ✏️ Update user details (no password here)
    public function update(Request $request, User $user)
    {
        /** @var User $auth */
        $auth = Auth::user();

        $emailInput = $request->input('email');
        if (is_string($emailInput)) {
            $email = strtolower($emailInput);
            $trashed = User::onlyTrashed()->where('email', $email)->first();
            if ($trashed && $trashed->id !== $user->id) {
                return response()->json([
                    'message' => 'This email belongs to a deleted account. Please restore it or use another email.'
                ], 422);
            }
        }

        // Cannot edit user with same role as yourself
        if ((int)$auth->role_id === (int)$user->role_id) {
            return response()->json([
                'message' => 'You cannot edit a user with the same role as yourself.'
            ], 403);
        }

        // Cannot edit higher role (lower role_id = higher privilege)
        if ((int)$user->role_id < (int)$auth->role_id) {
            return response()->json([
                'message' => 'You cannot edit a user with a higher role than yourself.'
            ], 403);
        }

        $data = $request->validate([
            'name'    => 'nullable|string|max:255',
            'email'   => [
                'nullable',
                'email',
                // ignore this user's current email, but enforce uniqueness across all users
                Rule::unique('users', 'email')
                    ->ignore($user->id),
            ],
            'role_id' => 'nullable|exists:roles,id',
        ]);

        if (array_key_exists('role_id', $data) && $data['role_id'] !== null) {
            if ((int)$data['role_id'] <= (int)$auth->role_id) {
                return response()->json([
                    'message' => 'You cannot assign the same or higher role than yours.'
                ], 403);
            }
        }

        $user->update($data);

        return response()->json(['message' => 'User updated', 'user' => $user->load('role')]);
    }

    // ✅ Update authenticated user's name & email (no password here)
    public function updateMe(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $emailInput = $request->input('email');
        if (is_string($emailInput)) {
            $email = strtolower($emailInput);
            $trashed = User::onlyTrashed()->where('email', $email)->first();
            if ($trashed && $trashed->id !== $user->id) {
                return response()->json([
                    'message' => 'This email belongs to a deleted account. Please restore it or use another email.'
                ], 422);
            }
        }

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')
                    ->ignore($user->id),
            ],
        ]);

        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
        ]);

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }

    // 🔒 Update authenticated user's password
    public function updateMyPassword(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required'],
            'new_password'     => ['required', Password::min(8), 'confirmed', 'different:current_password'],
        ]);

        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Password updated successfully']);
    }

    // 🛠 Admin resets another user's password (role checks apply)
    public function resetPassword(Request $request, User $user)
    {
        /** @var User $auth */
        $auth = Auth::user();

        if ((int)$auth->role_id === (int)$user->role_id || (int)$user->role_id < (int)$auth->role_id) {
            return response()->json([
                'message' => 'You cannot reset the password for this user.'
            ], 403);
        }

        $request->validate([
            'new_password' => ['required', Password::min(8), 'confirmed'],
        ]);

        $user->password = Hash::make($request->new_password);
        $user->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Password reset successfully']);
    }

    // ❌ Soft delete user (with checks)
    public function destroy(User $user)
    {
        /** @var User $auth */
        $auth = Auth::user();

        // 1) Cannot delete yourself
        if ($auth->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        // 2) Cannot delete same role
        if ((int)$auth->role_id === (int)$user->role_id) {
            return response()->json(['message' => 'You cannot delete a user with the same role as yourself.'], 403);
        }

        // 3) Cannot delete higher role
        if ((int)$user->role_id < (int)$auth->role_id) {
            return response()->json(['message' => 'You cannot delete a user with a higher role than yourself.'], 403);
        }

        $user->delete(); // ✅ now soft delete
        return response()->json(['message' => 'User deleted (soft)']);
    }

    // ♻️ Restore a soft-deleted user
    public function restore($id)
    {
        /** @var User $auth */
        $auth = Auth::user();
        $user = User::withTrashed()->findOrFail($id);

        // role checks similar to update/destroy
        if ((int)$auth->role_id === (int)$user->role_id) {
            return response()->json(['message' => 'You cannot restore a user with the same role as yourself.'], 403);
        }
        if ((int)$user->role_id < (int)$auth->role_id) {
            return response()->json(['message' => 'You cannot restore a user with a higher role than yourself.'], 403);
        }

        if (is_null($user->deleted_at)) {
            return response()->json(['message' => 'User is not deleted.'], 409);
        }

        $user->restore();

        return response()->json(['message' => 'User restored', 'user' => $user->fresh()->load('role')]);
    }

    // 💣 Permanently delete (force delete) — for already soft-deleted users
    public function forceDestroy($id)
    {
        /** @var User $auth */
        $auth = Auth::user();
        $user = User::withTrashed()->findOrFail($id);

        // Protect: not yourself; same checks as destroy
        if ($auth->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }
        if ((int)$auth->role_id === (int)$user->role_id) {
            return response()->json(['message' => 'You cannot delete a user with the same role as yourself.'], 403);
        }
        if ((int)$user->role_id < (int)$auth->role_id) {
            return response()->json(['message' => 'You cannot delete a user with a higher role than yourself.'], 403);
        }

        if (is_null($user->deleted_at)) {
            return response()->json(['message' => 'Force delete allowed only after soft delete.'], 409);
        }

        // If you need to clean tokens or relations, do here.
        $user->forceDelete();

        return response()->json(['message' => 'User permanently deleted']);
    }
}
