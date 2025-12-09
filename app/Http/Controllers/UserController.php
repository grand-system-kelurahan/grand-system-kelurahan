<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Update the authenticated user's username, email, and name.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'email'    => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'name'     => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $user->username = $validated['username'];
        $user->email    = $validated['email'];
        $user->name     = $validated['name'];
        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'user'    => $user,
        ]);
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password'          => ['required', 'string', 'min:8'],
            'new_password'              => ['required', 'string', 'min:8', 'confirmed'],
            'new_password_confirmation' => ['required', 'string', 'min:8'],
        ]);

        if (! password_verify($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = bcrypt($validated['new_password']);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.',
            'user'    => $user,
        ]);
    }

}
