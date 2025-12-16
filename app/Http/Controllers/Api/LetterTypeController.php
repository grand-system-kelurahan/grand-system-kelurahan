<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LetterType;
use Illuminate\Http\Request;
use Rickgoemans\LaravelApiResponseHelpers\ApiResponse;

class LetterTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = LetterType::query();


            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%");
            }


            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_dir', 'desc');
            $query->orderBy($sortField, $sortDirection);


            $perPage = $request->get('per_page', 20);
            $letterTypes = $query->paginate($perPage);

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'letterType' => $letterTypes->items(),
                    'meta' => [
                        'current_page' => $letterTypes->currentPage(),
                        'last_page' => $letterTypes->lastPage(),
                        'per_page' => $letterTypes->perPage(),
                        'total' => $letterTypes->total(),
                        'from' => $letterTypes->firstItem(),
                        'to' => $letterTypes->lastItem(),
                    ],
                    'links' => [
                        'first' => $letterTypes->url(1),
                        'last' => $letterTypes->url($letterTypes->lastPage()),
                        'prev' => $letterTypes->previousPageUrl(),
                        'next' => $letterTypes->nextPageUrl(),
                    ]
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to fetch data',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'letter_code' => 'required|string|max:20|unique:letter_types,letter_code',
            'letter_name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $letterType = LetterType::create($data);

        return ApiResponse::success(
            'Data created successfully',
            [
                'letterType' => $letterType
            ],
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $letterType = LetterType::findOrFail($id);
        return ApiResponse::success(
            'Data fetched successfully',
            [
                'letterType' => $letterType
            ],
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $letterType = LetterType::findOrFail($id);

        $data = $request->validate([
            'letter_code' => 'sometimes|string|max:20|unique:letter_types,letter_code,' . $letterType->id,
            'letter_name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
        ]);

        $letterType->update($data);

        return ApiResponse::success(
            'Data updated successfully',
            [
                'letterType' => $letterType
            ],
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $letterType = LetterType::findOrFail($id);
        $letterType->delete();

        return ApiResponse::success(
            'Data deleted successfully',
            [
                'letterType' => $letterType
            ],
        );
    }
}
