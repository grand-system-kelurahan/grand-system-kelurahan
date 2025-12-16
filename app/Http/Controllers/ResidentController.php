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
use App\Services\Region\RegionServiceClient;
use Illuminate\Support\Facades\Validator;

class ResidentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, RegionServiceClient $regionServiceClient): JsonResponse
    {
        try {
            $query = Resident::query();

            if ($request->filled('ids')) {
                $ids = $request->ids;

                if (is_string($ids)) {
                    $ids = array_filter(explode(',', $ids));
                }

                if (is_array($ids) && count($ids) > 0) {
                    $query->whereIn('id', $ids);
                }
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('national_number_id', 'like', "%{$search}%");
                });
            }

            if ($request->filled('rt')) {
                $query->where('rt', $request->rt);
            }

            if ($request->filled('rw')) {
                $query->where('rw', $request->rw);
            }

            if ($request->filled('region_id')) {
                $query->where('region_id', $request->region_id);
            }

            if ($request->filled('gender')) {
                $query->where('gender', $request->gender);
            }

            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_dir', 'desc');
            $query->orderBy($sortField, $sortDirection);

            $withPagination = filter_var(
                $request->get('with_pagination', true),
                FILTER_VALIDATE_BOOLEAN
            );

            if (!$withPagination) {
                $residents = $query->get();


                $residents = $this->enrichResidentsWithRegions($residents, $regionServiceClient);

                return ApiResponse::success(
                    'Data fetched successfully',
                    [
                        'residents' => $residents,
                    ]
                );
            }

            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);

            $residents = $query->paginate($perPage, ['*'], 'page', $page);

            $residents = $this->enrichResidentsWithRegions($residents, $regionServiceClient, true);

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
     * Enrich residents with region data from API
     */
    private function enrichResidentsWithRegions($residents, RegionServiceClient $regionServiceClient, bool $isPaginated = false)
    {
        if ($isPaginated) {
            $residentsCollection = $residents->getCollection();
        } else {
            $residentsCollection = $residents;
        }

        $regionIds = $residentsCollection
            ->pluck('region_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $regions = [];
        if (!empty($regionIds)) {
            $regions = $regionServiceClient->findByIds($regionIds);
            $regions = collect($regions)->keyBy('id')->toArray();
        }


        $residentsCollection->transform(function ($resident) use ($regions) {
            $resident->region = $regions[$resident->region_id] ?? null;
            if (!isset($resident->familyMember)) {
                $resident->load('familyMember');
            }

            return $resident;
        });

        if ($isPaginated) {
            $residents->setCollection($residentsCollection);
            return $residents;
        }

        return $residentsCollection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'national_number_id'    => 'required|string',
                'name'                  => 'required|string',
                'gender'                => 'required|string',
                'place_of_birth'        => 'required|string',
                'date_of_birth'         => 'required|date',
                'religion'              => 'required|string',
                'rt'                    => 'required|string',
                'rw'                    => 'required|string',
                'education'             => 'required|string',
                'occupation'            => 'required|string',
                'marital_status'        => 'required|string',
                'citizenship'           => 'required|string',
                'blood_type'            => 'required|string',
                'disabilities'          => 'required|string',
                'father_name'           => 'required|string',
                'mother_name'           => 'required|string',
                'region_id'             => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed.', $validator->errors());
            }

            $validated = $validator->validated();

            $regionId = $validated['region_id'];
            $region = Region::find($regionId);

            if (!$region) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region with id ' . $regionId . ' not found',
                ], 404);
            }

            if (isset($validated['date_of_birth'])) {
                $validated['date_of_birth'] = date('Y-m-d', strtotime($validated['date_of_birth']));
            }

            $resident = Resident::create($validated);

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
    public function show(int $id, RegionServiceClient $regionServiceClient): JsonResponse
    {
        try {
            // Find resident by ID
            $resident = Resident::with('familyMember')->find($id);

            if (!$resident) {
                return ApiResponse::error(
                    'Resident not found',
                    null,
                    404
                );
            }

            // Get region data from API
            $regionData = null;
            if ($resident->region_id) {
                $regionData = $regionServiceClient->findById($resident->region_id);
            }

            // Transform resident data
            $transformedResident = $this->transformResident($resident, $regionData);

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'resident' => $transformedResident
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
     * Transform resident data with region
     */
    private function transformResident(Resident $resident, ?array $regionData = null): array
    {
        return [
            'id' => $resident->id,
            'national_number_id' => $resident->national_number_id,
            'name' => $resident->name,
            'gender' => $resident->gender,
            'place_of_birth' => $resident->place_of_birth,
            'date_of_birth' => $resident->date_of_birth,
            'religion' => $resident->religion,
            'rt' => $resident->rt,
            'rw' => $resident->rw,
            'education' => $resident->education,
            'occupation' => $resident->occupation,
            'marital_status' => $resident->marital_status,
            'citizenship' => $resident->citizenship,
            'blood_type' => $resident->blood_type,
            'disabilities' => $resident->disabilities,
            'father_name' => $resident->father_name,
            'mother_name' => $resident->mother_name,
            'region_id' => $resident->region_id,
            'region' => $regionData ? [
                'id' => $regionData['id'] ?? null,
                'name' => $regionData['name'] ?? null,
                'encoded_geometry' => $regionData['encoded_geometry'] ?? null,
            ] : null,
            'family_member' => $resident->familyMember ? [
                'id' => $resident->familyMember->id,
                'family_card_id' => $resident->familyMember->family_card_id,
                'relationship' => $resident->familyMember->relationship,
            ] : null,
            'created_at' => $resident->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $resident->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Resident $resident): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'national_number_id'    => 'sometimes|string',
                'name'                  => 'sometimes|string',
                'gender'                => 'sometimes|string',
                'place_of_birth'        => 'sometimes|string',
                'date_of_birth'         => 'sometimes|date',
                'religion'              => 'sometimes|string',
                'rt'                    => 'sometimes|string',
                'rw'                    => 'sometimes|string',
                'education'             => 'sometimes|string',
                'occupation'            => 'sometimes|string',
                'marital_status'        => 'sometimes|string',
                'citizenship'           => 'sometimes|string',
                'blood_type'            => 'sometimes|string',
                'disabilities'          => 'sometimes|string',
                'father_name'           => 'sometimes|string',
                'mother_name'           => 'sometimes|string',
                'region_id'             => 'sometimes|numeric',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed.', $validator->errors());
            }

            $validated = $validator->validated();

            $regionId = $validated['region_id'];
            $region = Region::find($regionId);

            if (!$region) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region with id ' . $regionId . ' not found',
                ], 404);
            }

            if (isset($validated['date_of_birth'])) {
                $validated['date_of_birth'] = date('Y-m-d', strtotime($validated['date_of_birth']));
            }

            $resident->update($validated);

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
