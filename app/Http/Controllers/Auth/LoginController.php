<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Rickgoemans\LaravelApiResponseHelpers\ApiResponse;

class LoginController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name'     => 'required|string|max:255|regex:/^[\pL\s.]+$/u',
                'username' => 'required|string|min:3|max:255|regex:/^[a-zA-Z0-9]+$/|unique:users',
                'email'    => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
            ], [
                'name.regex' => 'Name can only contain letters, spaces, and periods.'
            ]);

            $user = User::create([
                'name'     => $request->name,
                'username' => $request->username,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $token = $user->createToken('api-token')->plainTextToken;

            return ApiResponse::success(
                'Registration successful',
                ['user' => $user, 'token' => $token]
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                'The given data was invalid.',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Registration failed',
                $e->getMessage(),
                500
            );
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string|min:3|regex:/^[a-zA-Z0-9]+$/',
                'password' => 'required|string|min:8',
            ], [
                'username.regex' => 'Username can only contain letters and numbers without spaces..'
            ]);

            $user = User::where('username', $request->username)
                ->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return ApiResponse::error(
                    'Invalid credentials',
                    null,
                    401
                );
            }

            $user->tokens()->delete();

            // calculate token expiration time
            $expirationMinutes = config('sanctum.expiration');
            $expiration_time   = $expirationMinutes ? now()->addMinutes($expirationMinutes) : null;

            $token = $user->createToken(
                name: 'api-token',
                abilities: ['*'],
                expiresAt: $expirationMinutes ? now()->addMinutes($expirationMinutes) : null
            )->plainTextToken;

            return ApiResponse::success(
                'Login successful',
                ['user' => $user, 'token' => $token, 'expired' => $expiration_time]
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                'The given data was invalid.',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Login failed',
                $e->getMessage(),
                500
            );
        }
    }

    public function me(Request $request)
    {
        return ApiResponse::success('Successfully retrieved user data', $request->user());
    }

    public function logout(Request $request)
    {
        try {
            if ($request->user()) {
                $request->user()->tokens()->delete();
                return ApiResponse::success('Logout successful', []);
            } else {
                return ApiResponse::error('No user authenticated', null, 401);
            }
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Logout failed',
                $e->getMessage()
            );
        }
    }
}
