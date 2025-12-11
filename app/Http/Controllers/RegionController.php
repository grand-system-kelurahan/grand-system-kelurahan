<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RegionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Region::withCount('residents');

            // Filtering
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%");
            }

            // Sorting
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_dir', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $regions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Data regions berhasil diambil',
                'data' => [
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
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server',
                'error' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Data region berhasil dibuat',
                'data' => [
                    'region' => $region
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat data region',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Region $region): JsonResponse
    {
        try {
            $region->loadCount('residents');

            return response()->json([
                'success' => true,
                'message' => 'Detail data region',
                'data' => [
                    'region' => $region,
                    'residents_count' => $region->residents_count,
                    'statistics' => $this->getRegionStatistics($region)
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data region',
                'error' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Data region berhasil diperbarui',
                'data' => [
                    'region' => $region->fresh()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data region',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Region $region): JsonResponse
    {
        try {
            // Check if region has residents
            if ($region->residents()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus region yang memiliki data warga',
                    'data' => [
                        'residents_count' => $region->residents()->count()
                    ]
                ], 422);
            }

            $region->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data region berhasil dihapus',
                'data' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data region',
                'error' => $e->getMessage()
            ], 500);
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

            // Filtering
            if ($request->has('gender')) {
                $query->where('gender', $request->gender);
            }

            if ($request->has('rt')) {
                $query->where('rt', $request->rt);
            }

            if ($request->has('rw')) {
                $query->where('rw', $request->rw);
            }

            // Sorting
            $sortField = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_dir', 'asc');
            $query->orderBy($sortField, $sortDirection);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $residents = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Data warga di region ' . $region->name,
                'data' => [
                    'region' => $region->only(['id', 'name']),
                    'residents' => $residents->items(),
                    'meta' => [
                        'current_page' => $residents->currentPage(),
                        'last_page' => $residents->lastPage(),
                        'per_page' => $residents->perPage(),
                        'total' => $residents->total(),
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data warga',
                'error' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Hasil pencarian regions',
                'data' => [
                    'regions' => $regions,
                    'total_found' => $regions->count()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan pencarian',
                'error' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Data regions dengan geometry',
                'data' => [
                    'regions' => $regions,
                    'total_with_geometry' => $regions->count()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data geometry',
                'error' => $e->getMessage()
            ], 500);
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

            // Check if any region has residents
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

            return response()->json([
                'success' => true,
                'message' => "{$count} data regions berhasil dihapus",
                'data' => [
                    'deleted_count' => $count
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data',
                'error' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Statistik regions',
                'data' => [
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
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
