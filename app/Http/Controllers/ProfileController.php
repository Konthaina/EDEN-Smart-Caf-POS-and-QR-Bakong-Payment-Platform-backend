<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * 👤 Get current user profile with profile + avatar URL
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('profile');

        // If user has no profile, return safe defaults
        if (!$user->profile) {
            return response()->json([
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role->name ?? $user->role,
                'profile'    => [
                    'phone'      => null,
                    'gender'     => null,
                    'birthdate'  => null,
                    'address'    => null,
                    'avatar_url' => asset('images/default-avatar.png'),
                ],
                'created_at' => $user->created_at,
            ]);
        }

        // If user has a profile, build avatar URL
        $avatar_url = $user->profile->avatar
            ? asset('storage/' . $user->profile->avatar)
            : asset('images/default-avatar.png');

        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role->name ?? $user->role,
            'profile'    => [
                'phone'      => $user->profile->phone,
                'gender'     => $user->profile->gender,
                'birthdate'  => $user->profile->birthdate,
                'address'    => $user->profile->address,
                'avatar_url' => $avatar_url,
            ],
            'created_at' => $user->created_at,
        ]);
    }

    /**
     * 📝 Update profile info
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'      => 'nullable|string|max:255',
            'phone'     => 'nullable|string|max:20',
            'gender'    => 'nullable|string|in:male,female,other',
            'birthdate' => 'nullable|date',
            'address'   => 'nullable|string|max:255',
        ]);

        // Update users table
        $user->update([
            'name' => $data['name'] ?? $user->name,
        ]);

        // Update or create profile (profiles table)
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'phone'     => $data['phone']     ?? '',
                'gender'    => $data['gender']    ?? '',
                'birthdate' => $data['birthdate'] ?? null,
                'address'   => $data['address']   ?? '',
            ]
        );

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => $user->load('profile'),
        ]);
    }

    /**
     * 🖼 Upload or change avatar in profiles table
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

        // Delete old avatar if exists
        if ($profile->avatar && Storage::disk('public')->exists($profile->avatar)) {
            Storage::disk('public')->delete($profile->avatar);
        }

        // Upload new file
        $path = $request->file('avatar')->store('avatars', 'public');

        $profile->avatar = $path;
        $profile->save();

        return response()->json([
            'message'    => 'Avatar updated successfully.',
            'avatar'     => $path,
            'avatar_url' => asset('storage/' . $path),
        ]);
    }

    /**
     * 🔑 Update user password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:6|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Password updated successfully']);
    }
}
