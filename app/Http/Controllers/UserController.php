<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Rickgoemans\LaravelApiResponseHelpers\ApiResponse;

class UserController extends Controller
{
    /**
     * GET /api/users
     * List semua user
     */
    public function index(Request $request)
    {
        $query = User::query();
        $withPagination = $request->get('with_pagination', 'true') === 'true';

        // search
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('username', 'like', "%{$keyword}%");
            });
        }

        // filter
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSortBy = [
            'id',
            'name',
            'email',
            'username',
            'role',
            'created_at',
            'updated_at',
        ];

        if (!in_array($sortBy, $allowedSortBy)) {
            $sortBy = 'created_at';
        }

        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortBy, $sortOrder);

        // pagination
        if ($withPagination) {
            $perPage = (int) $request->get('per_page', 10);
            $page = (int) $request->get('page', 1);

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($request->query());

            $data = [
                'users' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                    'next_page_url' => $paginator->nextPageUrl(),
                    'prev_page_url' => $paginator->previousPageUrl(),
                ],
            ];
        } else {
            $data = $query->get();
        }

        return ApiResponse::success('Users retrieved successfully.', $data);
    }

    /**
     * GET /api/users/{id}
     * Detail user
     */
    public function show(int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return ApiResponse::error('User not found.', null, 404);
        }

        return ApiResponse::success('User retrieved successfully.', $user);
    }

    /**
     * POST /api/users
     * Tambah user baru
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,user,pegawai',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', $validator->errors());
        }

        $validated = $validator->validated();

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return ApiResponse::success('User created successfully.', $user, 201);
    }

    /**
     * PUT /api/users/{id}
     * Update user
     */
    public function update(Request $request, int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return ApiResponse::error('User not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'username' => 'sometimes|string|max:50|unique:users,username,' . $user->id,
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:6',
            'role' => 'sometimes|in:admin,user,pegawai',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', $validator->errors());
        }

        $validated = $validator->validated();

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->fill($validated);
        $user->save();

        return ApiResponse::success('User updated successfully.', $user);
    }

    /**
     * DELETE /api/users/{id}
     * Hapus user
     */
    public function destroy(int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return ApiResponse::error('User not found.', null, 404);
        }

        $user->delete();

        return ApiResponse::success('User deleted successfully.');
    }
}
