<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Rickgoemans\LaravelApiResponseHelpers\ApiResponse;
use App\Services\Region\RegionServiceClient;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ResidentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, RegionServiceClient $regionServiceClient): JsonResponse
    {
        try {
            $query = Resident::query();
            $withPagination = $request->get('with_pagination', 'true') === 'true';

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

            // Sorting dengan validasi
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_dir', 'desc');

            $allowedSortFields = [
                'id',
                'name',
                'national_number_id',
                'gender',
                'rt',
                'rw',
                'region_id',
                'created_at',
                'updated_at'
            ];

            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'created_at';
            }

            if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }

            $query->orderBy($sortField, $sortDirection);

            // Pagination logic
            if ($withPagination) {
                $perPage = (int) $request->get('per_page', 20);
                $page = (int) $request->get('page', 1);

                $paginator = $query->paginate($perPage, ['*'], 'page', $page);
                $paginator->appends($request->query());

                $residents = $this->enrichResidentsWithRegions($paginator, $regionServiceClient, true);

                $data = [
                    'residents' => $paginator->items(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ],
                    'links' => [
                        'first' => $paginator->url(1),
                        'last' => $paginator->url($paginator->lastPage()),
                        'prev' => $paginator->previousPageUrl(),
                        'next' => $paginator->nextPageUrl(),
                    ]
                ];
            } else {
                $residents = $query->get();
                $residents = $this->enrichResidentsWithRegions($residents, $regionServiceClient);

                $data = $residents;
            }

            return ApiResponse::success(
                'Data fetched successfully',
                $data
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
            $collection = $residents->getCollection();
        } else {
            $collection = $residents;
        }

        // Group residents by region_id untuk batch processing
        $residentsByRegionId = $collection->groupBy('region_id');

        $allRegions = [];

        foreach ($residentsByRegionId as $regionId => $regionResidents) {
            if (!$regionId) {
                continue;
            }

            // Fetch region data
            $regionData = $regionServiceClient->findById($regionId);

            if ($regionData) {
                $allRegions[$regionId] = $regionData;
            }
        }

        // Attach region data ke setiap resident
        $collection->transform(function ($resident) use ($allRegions) {
            $resident->region = $allRegions[$resident->region_id] ?? null;

            // Load family member
            if (!isset($resident->familyMember)) {
                $resident->load('familyMember');
            }

            return $resident;
        });

        if ($isPaginated) {
            $residents->setCollection($collection);
            return $residents;
        }

        return $collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'national_number_id'    => 'required|string|min:10|max:20|regex:/^[0-9]+$/|unique:residents',
                'name'                  => 'required|string|min:3|max:255|regex:/^[\pL\s.]+$/u',
                'gender'                => 'required|string|in:male,female',
                'place_of_birth'        => 'required|string|min:3|max:255|regex:/^[\pL\s.,]+$/u',
                'date_of_birth'         => 'required|date|before:today',
                'religion'              => 'required|string|in:Islam,Kristen,Katolik,Hindu,Budha,Konghucu,Kepercayaan Terhadap Tuhan YME / Lainnya',
                'rt'                    => 'required|string|size:3|regex:/^[0-9]{3}$/',
                'rw'                    => 'required|string|size:3|regex:/^[0-9]{3}$/',
                'education'             => 'required|string|in:Tidak / Belum Sekolah,Belum Tamat SD / Sederajat,Tamat SD / Sederajat,SLTP / Sederajat,SLTA / Sederajat,Diploma I / II,Akademi / Diploma III / S. Muda,Diploma IV / Strata I,Strata II,Strata III,Tidak Tau',
                'occupation'            => 'required|string|min:3|max:255|regex:/^[\pL\s.,-]+$/u',
                'marital_status'        => 'required|string|in:Belum Kawin,Kawin,Cerai Hidup,Cerai Mati,Tidak Tau',
                'citizenship'           => 'required|string|in:WNI,WNA',
                'blood_type'            => 'required|string|in:A,B,AB,O,A+,A-,B+,B-,AB+,AB-,O+,O-,Tidak Tau',
                'disabilities'          => 'required|string',
                'father_name'           => 'required|string|min:3|max:255|regex:/^[\pL\s.]+$/u',
                'mother_name'           => 'required|string|min:3|max:255|regex:/^[\pL\s.]+$/u',
                'region_id'             => 'required|integer|min:1|exists:regions,id',
            ], [
                'national_number_id.regex' => 'The national number ID must contain only numbers without spaces.',
                'name.regex' => 'The name field can only contain letters, spaces, and periods.',
                'place_of_birth.regex' => 'The place of birth field can only contain letters, spaces, commas, and periods.',
                'occupation.regex' => 'The occupation field can only contain letters, spaces, commas, hyphens, and periods.',
                'father_name.regex' => 'The father name field can only contain letters, spaces, and periods.',
                'mother_name.regex' => 'The mother name field can only contain letters, spaces, and periods.',
                'rt.regex' => 'The RT field must contain exactly 3 digits.',
                'rw.regex' => 'The RW field must contain exactly 3 digits.',
                'date_of_birth.before' => 'The date of birth must be a date before today.',
                'region_id.exists' => 'The selected region is invalid.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed.', $validator->errors(), 422);
            }

            $validated = $validator->validated();

            // Format date of birth
            if (isset($validated['date_of_birth'])) {
                $validated['date_of_birth'] = date('Y-m-d', strtotime($validated['date_of_birth']));
            }

            // Trim and clean up names
            $validated['name'] = trim(preg_replace('/\s+/', ' ', $validated['name']));
            $validated['father_name'] = trim(preg_replace('/\s+/', ' ', $validated['father_name']));
            $validated['mother_name'] = trim(preg_replace('/\s+/', ' ', $validated['mother_name']));
            $validated['place_of_birth'] = trim(preg_replace('/\s+/', ' ', $validated['place_of_birth']));
            $validated['occupation'] = trim(preg_replace('/\s+/', ' ', $validated['occupation']));

            // Capitalize appropriate fields
            $validated['name'] = ucwords(strtolower($validated['name']));
            $validated['father_name'] = ucwords(strtolower($validated['father_name']));
            $validated['mother_name'] = ucwords(strtolower($validated['mother_name']));
            $validated['place_of_birth'] = ucwords(strtolower($validated['place_of_birth']));

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
    public function show($id, RegionServiceClient $regionServiceClient): JsonResponse
    {
        try {
            // Validasi bahwa id harus angka
            if (!is_numeric($id) || !ctype_digit((string)$id)) {
                return ApiResponse::error(
                    'Invalid ID format',
                    ['id' => 'The ID must be a positive integer.'],
                    422
                );
            }

            // Konversi ke integer
            $id = (int)$id;

            // Validasi bahwa id harus positif
            if ($id <= 0) {
                return ApiResponse::error(
                    'Invalid ID',
                    ['id' => 'The ID must be a positive integer.'],
                    422
                );
            }

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
    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Validasi ID
            $validation = $this->validateAndConvertId($id);
            if (!$validation['success']) {
                return $validation['error'];
            }

            $id = $validation['id'];

            // Cari resident
            $resident = Resident::find($id);

            if (!$resident) {
                return ApiResponse::error(
                    'Resident not found',
                    null,
                    404
                );
            }

            $validator = Validator::make($request->all(), [
                'national_number_id'    => [
                    'sometimes',
                    'string',
                    'min:10',
                    'max:20',
                    'regex:/^[0-9]+$/',
                    Rule::unique('residents')->ignore($resident->id)
                ],
                'name'                  => 'sometimes|string|min:3|max:255|regex:/^[\pL\s.]+$/u',
                'gender'                => 'sometimes|string|in:male,female',
                'place_of_birth'        => 'sometimes|string|min:3|max:255|regex:/^[\pL\s.,]+$/u',
                'date_of_birth'         => 'sometimes|date|before:today',
                'religion'              => 'sometimes|string|in:Islam,Kristen,Katolik,Hindu,Budha,Konghucu,Kepercayaan Terhadap Tuhan YME / Lainnya',
                'rt'                    => 'sometimes|string|size:3|regex:/^[0-9]{3}$/',
                'rw'                    => 'sometimes|string|size:3|regex:/^[0-9]{3}$/',
                'education'             => 'sometimes|string|in:Tidak / Belum Sekolah,Belum Tamat SD / Sederajat,Tamat SD / Sederajat,SLTP / Sederajat,SLTA / Sederajat,Diploma I / II,Akademi / Diploma III / S. Muda,Diploma IV / Strata I,Strata II,Strata III,Tidak Tau',
                'occupation'            => 'sometimes|string|min:3|max:255|regex:/^[\pL\s.,-]+$/u',
                'marital_status'        => 'sometimes|string|in:Belum Kawin,Kawin,Cerai Hidup,Cerai Mati,Tidak Tau',
                'citizenship'           => 'sometimes|string|in:WNI,WNA',
                'blood_type'            => 'sometimes|string|in:A,B,AB,O,A+,A-,B+,B-,AB+,AB-,O+,O-,Tidak Tau',
                'disabilities'          => 'sometimes|string',
                'father_name'           => 'sometimes|string|min:3|max:255|regex:/^[\pL\s.]+$/u',
                'mother_name'           => 'sometimes|string|min:3|max:255|regex:/^[\pL\s.]+$/u',
                'region_id'             => 'sometimes|integer|min:1|exists:regions,id',
            ], [
                'national_number_id.regex' => 'The national number ID must contain only numbers without spaces.',
                'national_number_id.unique' => 'The national number ID has already been taken.',
                'name.regex' => 'The name field can only contain letters, spaces, and periods.',
                'place_of_birth.regex' => 'The place of birth field can only contain letters, spaces, commas, and periods.',
                'occupation.regex' => 'The occupation field can only contain letters, spaces, commas, hyphens, and periods.',
                'father_name.regex' => 'The father name field can only contain letters, spaces, and periods.',
                'mother_name.regex' => 'The mother name field can only contain letters, spaces, and periods.',
                'rt.regex' => 'The RT field must contain exactly 3 digits.',
                'rw.regex' => 'The RW field must contain exactly 3 digits.',
                'date_of_birth.before' => 'The date of birth must be a date before today.',
                'region_id.exists' => 'The selected region is invalid.',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed.', $validator->errors(), 422);
            }

            $validated = $validator->validated();

            // Validasi region_id jika ada
            if (isset($validated['region_id'])) {
                $regionId = $validated['region_id'];
                $region = Region::find($regionId);

                if (!$region) {
                    return ApiResponse::error(
                        'Region not found',
                        ['region_id' => 'Region with id ' . $regionId . ' not found'],
                        404
                    );
                }
            }

            // Format date of birth jika ada
            if (isset($validated['date_of_birth'])) {
                $validated['date_of_birth'] = date('Y-m-d', strtotime($validated['date_of_birth']));
            }

            // Trim dan format nama-nama jika ada
            $fieldsToClean = ['name', 'place_of_birth', 'occupation', 'father_name', 'mother_name'];
            foreach ($fieldsToClean as $field) {
                if (isset($validated[$field])) {
                    $validated[$field] = trim(preg_replace('/\s+/', ' ', $validated[$field]));
                    if (in_array($field, ['name', 'father_name', 'mother_name', 'place_of_birth'])) {
                        $validated[$field] = ucwords(strtolower($validated[$field]));
                    }
                }
            }

            // Update RT/RW dengan leading zero jika perlu
            if (isset($validated['rt'])) {
                $validated['rt'] = str_pad($validated['rt'], 3, '0', STR_PAD_LEFT);
            }
            if (isset($validated['rw'])) {
                $validated['rw'] = str_pad($validated['rw'], 3, '0', STR_PAD_LEFT);
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
    public function destroy($id): JsonResponse
    {
        try {
            // Validasi ID
            $validation = $this->validateAndConvertId($id);
            if (!$validation['success']) {
                return $validation['error'];
            }

            $id = $validation['id'];

            // Cari resident
            $resident = Resident::find($id);

            if (!$resident) {
                return ApiResponse::error(
                    'Resident not found',
                    null,
                    404
                );
            }

            $resident->delete();

            return ApiResponse::success(
                'Data deleted successfully',
                null,
                200
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
    public function statistics(Request $request): JsonResponse
    {
        try {
            $baseQuery = $this->buildBaseQuery($request);
            $total = $baseQuery->count();

            // Hitung statistik utama dalam fungsi terpisah
            $genderStats = $this->getGenderStatistics($baseQuery, $total);
            $ageStats = $this->getAgeStatistics($baseQuery);
            $religionStats = $this->getReligionStatistics($baseQuery, $total);
            $maritalStats = $this->getMaritalStatistics($baseQuery, $total);
            $educationStats = $this->getEducationStatistics($baseQuery, $total);
            $occupationStats = $this->getOccupationStatistics($baseQuery, $total);
            $geographicalStats = $this->getGeographicalStatistics($baseQuery);

            // Data ringkasan
            $summary = [
                'total_residents' => $total,
                'average_age' => $ageStats['average_age'],
                'min_age' => $ageStats['min_age'],
                'max_age' => $ageStats['max_age'],
                'total_rt' => $geographicalStats['total_rt'],
                'total_rw' => $geographicalStats['total_rw'],
            ];

            return ApiResponse::success(
                'Data statistik berhasil diambil',
                [
                    'summary' => $summary,
                    'gender_distribution' => $genderStats,
                    'age_analysis' => $ageStats,
                    'religion_stats' => $religionStats,
                    'marital_stats' => $maritalStats,
                    'education_stats' => $educationStats,
                    'occupation_stats' => $occupationStats,
                    'geographical_distribution' => $geographicalStats,
                ]
            );
        } catch (\Exception $e) {

            return ApiResponse::error(
                'Gagal mengambil data statistik',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Build base query dengan filter
     */
    private function buildBaseQuery(Request $request)
    {
        $query = Resident::query();

        if ($request->has('region_id')) {
            $query->where('region_id', $request->region_id);
        }

        if ($request->has('rt')) {
            $query->where('rt', $request->rt);
        }

        if ($request->has('rw')) {
            $query->where('rw', $request->rw);
        }

        return $query;
    }

    /**
     * Get gender statistics
     */
    private function getGenderStatistics($query, $total)
    {
        $maleQuery = clone $query;
        $male = $maleQuery->where('gender', 'male')->count();
        $female = $total - $male;

        return [
            'male' => [
                'count' => $male,
                'percentage' => $total > 0 ? round(($male / $total) * 100, 2) : 0
            ],
            'female' => [
                'count' => $female,
                'percentage' => $total > 0 ? round(($female / $total) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Get age statistics
     */
    private function getAgeStatistics($query)
    {
        // Age distribution
        $distribution = [
            '0-17' => $query->clone()->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 17')->count(),
            '18-30' => $query->clone()->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30')->count(),
            '31-45' => $query->clone()->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 45')->count(),
            '46-60' => $query->clone()->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 46 AND 60')->count(),
            '60+' => $query->clone()->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) > 60')->count(),
        ];

        // Age summary
        $summaryQuery = clone $query;
        $ageSummary = $summaryQuery->selectRaw('
        AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age,
        MIN(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as min_age,
        MAX(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as max_age
    ')->first();

        return [
            'distribution' => $distribution,
            'average_age' => round($ageSummary->avg_age ?? 0, 1),
            'min_age' => $ageSummary->min_age ?? 0,
            'max_age' => $ageSummary->max_age ?? 0,
            'age_groups' => [
                'children_teenagers' => $distribution['0-17'],
                'young_adults' => $distribution['18-30'],
                'adults' => $distribution['31-45'],
                'middle_aged' => $distribution['46-60'],
                'seniors' => $distribution['60+'],
            ]
        ];
    }

    /**
     * Validate and convert ID to integer
     */
    private function validateAndConvertId($id): array
    {
        if (!is_numeric($id)) {
            return [
                'success' => false,
                'error' => ApiResponse::error(
                    'Invalid ID format',
                    ['id' => 'The ID must be a numeric value.'],
                    422
                )
            ];
        }

        $id = (int)$id;

        if ($id <= 0) {
            return [
                'success' => false,
                'error' => ApiResponse::error(
                    'Invalid ID',
                    ['id' => 'The ID must be a positive integer.'],
                    422
                )
            ];
        }

        return [
            'success' => true,
            'id' => $id
        ];
    }

    /**
     * Get religion statistics - FIXED: tanpa ORDER BY dengan alias
     */
    private function getReligionStatistics($query, $total)
    {
        // Get data tanpa ORDER BY dulu
        $religionData = $query->selectRaw('religion, COUNT(*) as religion_count')
            ->groupBy('religion')
            ->get();

        // Sort manually di PHP
        $religionData = $religionData->sortByDesc('religion_count');

        $result = [];

        foreach ($religionData as $item) {
            $percentage = $total > 0 ? round(($item->religion_count / $total) * 100, 2) : 0;
            $result[$item->religion] = [
                'count' => $item->religion_count,
                'percentage' => $percentage
            ];
        }

        return $result;
    }

    /**
     * Get marital status statistics - FIXED: tanpa ORDER BY dengan alias
     */
    private function getMaritalStatistics($query, $total)
    {
        $maritalData = $query->selectRaw('marital_status, COUNT(*) as marital_count')
            ->groupBy('marital_status')
            ->get();

        // Sort manually di PHP
        $maritalData = $maritalData->sortByDesc('marital_count');

        $result = [];

        foreach ($maritalData as $item) {
            $percentage = $total > 0 ? round(($item->marital_count / $total) * 100, 2) : 0;
            $result[$item->marital_status] = [
                'count' => $item->marital_count,
                'percentage' => $percentage
            ];
        }

        return $result;
    }

    /**
     * Get education statistics - FIXED: tanpa ORDER BY dengan alias
     */
    private function getEducationStatistics($query, $total)
    {
        $educationData = $query->selectRaw('education, COUNT(*) as education_count')
            ->groupBy('education')
            ->get();

        // Sort manually di PHP
        $educationData = $educationData->sortByDesc('education_count');

        $result = [];

        foreach ($educationData as $item) {
            $percentage = $total > 0 ? round(($item->education_count / $total) * 100, 2) : 0;
            $result[$item->education] = [
                'count' => $item->education_count,
                'percentage' => $percentage
            ];
        }

        return $result;
    }

    /**
     * Get occupation statistics - FIXED: tanpa ORDER BY dengan alias
     */
    private function getOccupationStatistics($query, $total)
    {
        $occupationData = $query->selectRaw('occupation, COUNT(*) as occupation_count')
            ->whereNotNull('occupation')
            ->where('occupation', '!=', '')
            ->groupBy('occupation')
            ->get();

        // Sort dan limit manually di PHP
        $occupationData = $occupationData->sortByDesc('occupation_count')->take(10);

        $result = [];

        foreach ($occupationData as $item) {
            $percentage = $total > 0 ? round(($item->occupation_count / $total) * 100, 2) : 0;
            $result[$item->occupation] = [
                'count' => $item->occupation_count,
                'percentage' => $percentage
            ];
        }

        return $result;
    }

    /**
     * Get geographical statistics - FIXED: query sederhana
     */
    private function getGeographicalStatistics($query)
    {
        // Query terpisah untuk count distinct tanpa conflict
        $rtQuery = clone $query;
        $rwQuery = clone $query;

        $rtCount = $rtQuery->distinct()->count('rt');
        $rwCount = $rwQuery->distinct()->count('rw');

        // RT distribution - tanpa ORDER BY dengan alias
        $rtDataRaw = $query->clone()->selectRaw('rt, COUNT(*) as rt_count')
            ->groupBy('rt')
            ->orderBy('rt') // Order by rt (string), bukan count
            ->get();

        $rtData = $rtDataRaw->mapWithKeys(function ($item) {
            return [$item->rt => $item->rt_count];
        });

        // RW distribution - tanpa ORDER BY dengan alias
        $rwDataRaw = $query->clone()->selectRaw('rw, COUNT(*) as rw_count')
            ->groupBy('rw')
            ->orderBy('rw') // Order by rw (string), bukan count
            ->get();

        $rwData = $rwDataRaw->mapWithKeys(function ($item) {
            return [$item->rw => $item->rw_count];
        });

        return [
            'total_rt' => $rtCount,
            'total_rw' => $rwCount,
            'rt_distribution' => $rtData,
            'rw_distribution' => $rwData,
        ];
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
