<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResidentRequest;
use App\Http\Requests\UpdateResidentRequest;
use App\Models\Region;
use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Rickgoemans\LaravelApiResponseHelpers\ApiResponse;

class ResidentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Resident::with(['region', 'familyMember']);


            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('national_number_id', 'like', "%{$search}%");
                });
            }

            if ($request->has('rt')) {
                $query->where('rt', $request->rt);
            }

            if ($request->has('rw')) {
                $query->where('rw', $request->rw);
            }

            if ($request->has('region_id')) {
                $query->where('region_id', $request->region_id);
            }

            if ($request->has('gender')) {
                $query->where('gender', $request->gender);
            }


            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_dir', 'desc');
            $query->orderBy($sortField, $sortDirection);


            $perPage = $request->get('per_page', 20);
            $residents = $query->paginate($perPage);

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'residents' => $residents->items(),
                    'meta' => [
                        'current_page' => $residents->currentPage(),
                        'last_page' => $residents->lastPage(),
                        'per_page' => $residents->perPage(),
                        'total' => $residents->total(),
                        'from' => $residents->firstItem(),
                        'to' => $residents->lastItem(),
                    ],
                    'links' => [
                        'first' => $residents->url(1),
                        'last' => $residents->url($residents->lastPage()),
                        'prev' => $residents->previousPageUrl(),
                        'next' => $residents->nextPageUrl(),
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
    public function store(StoreResidentRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $regionId = $data['region_id'];
            $region = Region::find($regionId);

            if (!$region) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region with id ' . $regionId . ' not found',
                ], 404);
            }

            if (isset($data['date_of_birth'])) {
                $data['date_of_birth'] = date('Y-m-d', strtotime($data['date_of_birth']));
            }

            $resident = Resident::create($data);

            return ApiResponse::success(
                'Data created successfully',
                [
                    'resident' => $resident->load('region')
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
    public function show(Resident $resident): JsonResponse
    {
        try {
            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'resident' => $resident->load('region')
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
     * Update the specified resource in storage.
     */
    public function update(UpdateResidentRequest $request, Resident $resident): JsonResponse
    {
        try {
            $data = $request->validated();

            $regionId = $data['region_id'];
            $region = Region::find($regionId);

            if (!$region) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region with id ' . $regionId . ' not found',
                ], 404);
            }

            if (isset($data['date_of_birth'])) {
                $data['date_of_birth'] = date('Y-m-d', strtotime($data['date_of_birth']));
            }

            $resident->update($data);

            return ApiResponse::success(
                'Data updated successfully',
                [
                    'resident' => $resident->fresh()->load('region')
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
    public function destroy(Resident $resident): JsonResponse
    {
        try {
            $resident->delete();
            return ApiResponse::success(
                'Data deleted successfully',
                null
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
     * Get resident statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $total = Resident::count();
            $male = Resident::where('gender', 'Laki-laki')->count();
            $female = Resident::where('gender', 'Perempuan')->count();

            $religionStats = Resident::selectRaw('religion, COUNT(*) as total')
                ->groupBy('religion')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->religion => $item->total];
                });

            $maritalStats = Resident::selectRaw('marital_status, COUNT(*) as total')
                ->groupBy('marital_status')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->marital_status => $item->total];
                });

            $educationStats = Resident::selectRaw('education, COUNT(*) as total')
                ->groupBy('education')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->education => $item->total];
                });

            $ageDistribution = [
                '0-17' => Resident::whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 17')->count(),
                '18-30' => Resident::whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30')->count(),
                '31-45' => Resident::whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 45')->count(),
                '46-60' => Resident::whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 46 AND 60')->count(),
                '60+' => Resident::whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) > 60')->count(),
            ];

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'total' => $total,
                    'gender_distribution' => [
                        'male' => $male,
                        'female' => $female,
                        'male_percentage' => $total > 0 ? round(($male / $total) * 100, 2) : 0,
                        'female_percentage' => $total > 0 ? round(($female / $total) * 100, 2) : 0,
                    ],
                    'religion_stats' => $religionStats,
                    'marital_stats' => $maritalStats,
                    'education_stats' => $educationStats,
                    'age_distribution' => $ageDistribution,
                    'rt_rw_summary' => [
                        'total_rt' => Resident::distinct('rt')->count('rt'),
                        'total_rw' => Resident::distinct('rw')->count('rw'),
                    ]
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to get statistics',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get residents by RT/RW
     */
    public function getByArea(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'rt' => 'sometimes|string',
                'rw' => 'sometimes|string',
                'region_id' => 'sometimes|exists:regions,id'
            ]);

            $query = Resident::with('region');

            if ($request->has('rt')) {
                $query->where('rt', $request->rt);
            }

            if ($request->has('rw')) {
                $query->where('rw', $request->rw);
            }

            if ($request->has('region_id')) {
                $query->where('region_id', $request->region_id);
            }

            $residents = $query->get();

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'residents' => $residents,
                    'summary' => [
                        'total' => $residents->count(),
                        'rt' => $request->rt ?? 'All',
                        'rw' => $request->rw ?? 'All',
                        'region' => $residents->first()->region ?? null
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
     * Search residents by name or NIK
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'keyword' => 'required|string|min:3'
            ]);

            $keyword = $request->keyword;

            $residents = Resident::with('region')
                ->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('national_number_id', 'like', "%{$keyword}%")
                        ->orWhere('father_name', 'like', "%{$keyword}%")
                        ->orWhere('mother_name', 'like', "%{$keyword}%");
                })
                ->limit(20)
                ->get();

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'residents' => $residents,
                    'total_found' => $residents->count()
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
