<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Rickgoemans\LaravelApiResponseHelpers\ApiResponse;

class RegionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Region::withCount('residents');


            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%");
            }


            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_dir', 'desc');
            $query->orderBy($sortField, $sortDirection);


            $perPage = $request->get('per_page', 20);
            $regions = $query->paginate($perPage);

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'regions' => $regions->items(),
                    'meta' => [
                        'current_page' => $regions->currentPage(),
                        'last_page' => $regions->lastPage(),
                        'per_page' => $regions->perPage(),
                        'total' => $regions->total(),
                        'from' => $regions->firstItem(),
                        'to' => $regions->lastItem(),
                    ],
                    'links' => [
                        'first' => $regions->url(1),
                        'last' => $regions->url($regions->lastPage()),
                        'prev' => $regions->previousPageUrl(),
                        'next' => $regions->nextPageUrl(),
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
    public function store(StoreRegionRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $region = Region::create($data);

            return ApiResponse::success(
                'Data created successfully',
                [
                    'region' => $region
                ],
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to create data',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Region $region): JsonResponse
    {
        try {
            $region->loadCount('residents');
            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'region' => $region,
                    'residents_count' => $region->residents_count,
                    'statistics' => $this->getRegionStatistics($region)
                ],
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
     * Update the specified resource in storage.
     */
    public function update(UpdateRegionRequest $request, Region $region): JsonResponse
    {
        try {
            $data = $request->validated();

            $region->update($data);
            return ApiResponse::success(
                'Data updated successfully',
                [
                    'region' => $region->fresh()
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to update data',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Region $region): JsonResponse
    {
        try {

            if ($region->residents()->count() > 0) {
                return ApiResponse::success(
                    'Cannot delete region that has residents data',
                    [
                        'residents_count' => $region->residents()->count()
                    ],
                    422
                );
            }

            $region->delete();

            return ApiResponse::success(
                'Data deleted successfully',
                null,
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to delete data',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get statistics for a specific region
     */
    private function getRegionStatistics(Region $region): array
    {
        $residents = $region->residents;

        return [
            'total_residents' => $residents->count(),
            'gender_distribution' => [
                'male' => $residents->where('gender', 'Laki-laki')->count(),
                'female' => $residents->where('gender', 'Perempuan')->count(),
            ],
            'age_distribution' => [
                '0-17' => $residents->filter(fn($r) => $r->age <= 17)->count(),
                '18-30' => $residents->filter(fn($r) => $r->age >= 18 && $r->age <= 30)->count(),
                '31-45' => $residents->filter(fn($r) => $r->age >= 31 && $r->age <= 45)->count(),
                '46-60' => $residents->filter(fn($r) => $r->age >= 46 && $r->age <= 60)->count(),
                '60+' => $residents->filter(fn($r) => $r->age > 60)->count(),
            ],
            'religion_stats' => $residents->groupBy('religion')->map->count(),
            'marital_stats' => $residents->groupBy('marital_status')->map->count(),
            'education_stats' => $residents->groupBy('education')->map->count(),
        ];
    }

    /**
     * Get all residents in a specific region
     */
    public function getResidents(Region $region, Request $request): JsonResponse
    {
        try {
            $query = $region->residents();


            if ($request->has('gender')) {
                $query->where('gender', $request->gender);
            }

            if ($request->has('rt')) {
                $query->where('rt', $request->rt);
            }

            if ($request->has('rw')) {
                $query->where('rw', $request->rw);
            }


            $sortField = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_dir', 'asc');
            $query->orderBy($sortField, $sortDirection);


            $perPage = $request->get('per_page', 20);
            $residents = $query->paginate($perPage);

            return ApiResponse::success(
                'Resident in region ' . $region->name,
                [
                    'region' => $region->only(['id', 'name']),
                    'residents' => $residents->items(),
                    'meta' => [
                        'current_page' => $residents->currentPage(),
                        'last_page' => $residents->lastPage(),
                        'per_page' => $residents->perPage(),
                        'total' => $residents->total(),
                    ]
                ],
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
     * Search regions by name
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'keyword' => 'required|string|min:2'
            ]);

            $keyword = $request->keyword;

            $regions = Region::where('name', 'like', "%{$keyword}%")
                ->withCount('residents')
                ->limit(15)
                ->get();

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'regions' => $regions,
                    'total_found' => $regions->count()
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
     * Get regions with geometry data
     */
    public function withGeometry(Request $request): JsonResponse
    {
        try {
            $regions = Region::whereNotNull('encoded_geometry')
                ->select('id', 'name', 'encoded_geometry')
                ->get();

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'regions' => $regions,
                    'total_with_geometry' => $regions->count()
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
     * Bulk delete regions
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:regions,id'
            ]);


            $regionsWithResidents = Region::whereIn('id', $request->ids)
                ->has('residents')
                ->count();

            if ($regionsWithResidents > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus region yang memiliki data warga',
                    'data' => [
                        'regions_with_residents' => $regionsWithResidents
                    ]
                ], 422);
            }

            $count = Region::whereIn('id', $request->ids)->delete();

            return ApiResponse::success(
                "{$count} data regions deleted successfully",
                [
                    'deleted_count' => $count
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to delete data',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get regions statistics summary
     */
    public function statistics(): JsonResponse
    {
        try {
            $totalRegions = Region::count();
            $regionsWithGeometry = Region::whereNotNull('encoded_geometry')->count();
            $regionsWithResidents = Region::has('residents')->count();

            $topRegions = Region::withCount('residents')
                ->orderBy('residents_count', 'desc')
                ->limit(10)
                ->get(['id', 'name', 'residents_count']);

            return ApiResponse::success(
                "Region statistics",
                [
                    'total_regions' => $totalRegions,
                    'regions_with_geometry' => $regionsWithGeometry,
                    'regions_with_residents' => $regionsWithResidents,
                    'regions_without_residents' => $totalRegions - $regionsWithResidents,
                    'top_populated_regions' => $topRegions,
                    'geometry_coverage_percentage' => $totalRegions > 0
                        ? round(($regionsWithGeometry / $totalRegions) * 100, 2)
                        : 0,
                    'residents_coverage_percentage' => $totalRegions > 0
                        ? round(($regionsWithResidents / $totalRegions) * 100, 2)
                        : 0,
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
}
