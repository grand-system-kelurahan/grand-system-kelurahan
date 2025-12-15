<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Rickgoemans\LaravelApiResponseHelpers\ApiResponse;

class AssetController extends Controller
{
    /**
     * GET /api/assets
     * List semua aset
     */
    public function index(Request $request)
    {
        $query = Asset::query();
        $withPagination = $request->get('with_pagination', 'true') === 'true';

        // search by keyword
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('asset_name', 'like', "%{$keyword}%")
                    ->orWhere('asset_code', 'like', "%{$keyword}%")
                    ->orWhere('location', 'like', "%{$keyword}%");
            });
        }

        // filter
        if ($request->filled('asset_type')) {
            $query->where('asset_type', $request->asset_type);
        }

        if ($request->filled('asset_status')) {
            $query->where('asset_status', $request->asset_status);
        }

        if ($request->filled('available_only') && $request->available_only === 'true') {
            $query->where('available_stock', '>', 0);
        }

        // sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSortBy = [
            'id',
            'asset_code',
            'asset_name',
            'asset_type',
            'total_stock',
            'available_stock',
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
                'assets' => $paginator->items(),
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

        return ApiResponse::success('Assets retrieved successfully.', $data);
    }

    /**
     * GET /api/assets/{id}
     * Detail aset
     */
    public function show(int $id)
    {
        $asset = Asset::find($id);

        if (!$asset) {
            return ApiResponse::error('Asset not found', null, 404);
        }

        return ApiResponse::success('Asset retrieved successfully.', $asset);
    }

    /**
     * POST /api/assets
     * Tambah aset baru
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_code' => 'required|string|max:30|unique:assets,asset_code',
            'asset_name' => 'required|string|max:100|unique:assets,asset_name',
            'description' => 'nullable|string',
            'asset_type' => 'required|in:item,room',
            'total_stock' => 'required|integer|min:1',
            'location' => 'nullable|string|max:100',
            'asset_status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', $validator->errors());
        }

        $validated = $validator->validated();

        $asset = Asset::create([
            ...$validated,
            'available_stock' => $validated['total_stock'],
            'asset_status' => Asset::STATUS_ACTIVE,
        ]);

        return ApiResponse::success('Asset created successfully.', $asset, 201);
    }

    /**
     * PUT /api/assets/{id}
     * Update aset
     */
    public function update(Request $request, int $id)
    {
        $asset = Asset::find($id);

        if (!$asset) {
            return APIResponse::error('Asset not found', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'asset_name' => 'sometimes|string|max:100|unique:assets,asset_name,' . $asset->id,
            'description' => 'sometimes|nullable|string',
            'asset_type' => 'sometimes|in:item,room',
            'total_stock' => 'sometimes|integer|min:' . $asset->available_stock,
            'location' => 'sometimes|nullable|string|max:100',
            'asset_status' => 'sometimes|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return APIResponse::error('Validation failed.', $validator->errors());
        }

        $validated = $validator->validated();

        if (isset($validated['total_stock'])) {
            $asset->total_stock = $validated['total_stock'];
        }

        $asset->fill($validated);
        $asset->save();

        return APIResponse::success('Asset updated successfully.', $asset);
    }


    /**
     * DELETE /api/assets/{id}
     * Nonaktifkan aset (soft delete versi bisnis)
     */
    public function destroy(int $id)
    {
        $asset = Asset::find($id);

        if (!$asset) {
            return APIResponse::error('Asset not found', null, 404);
        }

        $asset->delete();

        return APIResponse::success('Asset deleted successfully.');
    }

    public function report(Request $request)
    {
        $query = Asset::query();

        if ($request->filled('asset_type')) {
            $query->where('asset_type', $request->asset_type);
        }

        if ($request->filled('asset_status')) {
            $query->where('asset_status', $request->asset_status);
        }

        $assets = $query->get();

        $summary = [
            'total_assets' => $assets->count(),
            'total_stock' => $assets->sum('total_stock'),
            'available_stock' => $assets->sum('available_stock'),
            'borrowed_stock' => $assets->sum('total_stock') - $assets->sum('available_stock'),
        ];

        $group_by_type = $assets->groupBy('asset_type')->map(function ($items) {
            return [
                'total_assets' => $items->count(),
                'total_stock' => $items->sum('total_stock'),
                'available_stock' => $items->sum('available_stock'),
            ];
        });

        $data = [
            'summary' => $summary,
            'group_by_type' => $group_by_type,
        ];

        return ApiResponse::success('Assets retrieved successfully.', $data);
    }
}
