<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFamilyCardRequest;
use App\Http\Requests\UpdateFamilyCardRequest;
use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\Region;
use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Rickgoemans\LaravelApiResponseHelpers\ApiResponse;
use App\Services\Region\RegionServiceClient;

class FamilyCardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, RegionServiceClient $regionServiceClient): JsonResponse
    {
        try {
            $query = FamilyCard::with(['familyMembers.resident']); // Hapus 'region'

            // Filtering
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where(function ($q) use ($search, $regionServiceClient) {
                    $q->where('head_of_family_name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        // Hapus filter berdasarkan region name karena perlu API call
                        ->orWhereHas('familyMembers.resident', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            }

            if ($request->has('region_id')) {
                $query->where('region_id', $request->region_id);
            }

            if ($request->has('publication_date_from')) {
                $query->whereDate('publication_date', '>=', $request->publication_date_from);
            }

            if ($request->has('publication_date_to')) {
                $query->whereDate('publication_date', '<=', $request->publication_date_to);
            }

            // Sorting
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_dir', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $familyCards = $query->paginate($perPage);

            // Enrich family cards with region data
            $familyCards = $this->enrichFamilyCardsWithRegions($familyCards, $regionServiceClient);

            // Hitung total anggota per keluarga
            $familyCards->getCollection()->transform(function ($familyCard) {
                $familyCard->total_members = $familyCard->familyMembers()->count();
                return $familyCard;
            });

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'family_cards' => $familyCards->items(),
                    'meta' => [
                        'current_page' => $familyCards->currentPage(),
                        'last_page' => $familyCards->lastPage(),
                        'per_page' => $familyCards->perPage(),
                        'total' => $familyCards->total(),
                        'from' => $familyCards->firstItem(),
                        'to' => $familyCards->lastItem(),
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
     * Enrich family cards with region data from API
     */
    private function enrichFamilyCardsWithRegions($familyCards, RegionServiceClient $regionServiceClient, bool $isPaginated = true)
    {
        if ($isPaginated) {
            $collection = $familyCards->getCollection();
        } else {
            $collection = $familyCards;
        }

        // Extract unique region IDs from family cards
        $regionIds = $collection
            ->pluck('region_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Get regions data from API
        $regions = [];
        if (!empty($regionIds)) {
            $regions = $regionServiceClient->findByIds($regionIds);
        }

        // Convert regions to associative array with region_id as key
        $regionsMap = collect($regions)->keyBy('id')->toArray();

        // Enrich each family card with region data
        $collection->transform(function ($familyCard) use ($regionsMap) {
            $familyCard->region = $regionsMap[$familyCard->region_id] ?? null;
            return $familyCard;
        });

        if ($isPaginated) {
            $familyCards->setCollection($collection);
            return $familyCards;
        }

        return $collection;
    }

    /**
     * Transform family card data for API response
     */
    private function transformFamilyCard($familyCard): array
    {
        $familyMembers = $familyCard->familyMembers ?? collect();

        return [
            'id' => $familyCard->id,
            'head_of_family_name' => $familyCard->head_of_family_name,
            'address' => $familyCard->address,
            'publication_date' => $familyCard->publication_date->format('Y-m-d'),
            'region_id' => $familyCard->region_id,
            'region' => $familyCard->region ? [
                'id' => $familyCard->region['id'] ?? null,
                'name' => $familyCard->region['name'] ?? null,
                'encoded_geometry' => $familyCard->region['encoded_geometry'] ?? null,
            ] : null,
            'total_members' => $familyCard->total_members ?? $familyMembers->count(),
            'family_members' => $familyMembers->map(function ($member) {
                return [
                    'id' => $member->id,
                    'resident_id' => $member->resident_id,
                    'relationship' => $member->relationship,
                    'resident' => $member->resident ? [
                        'id' => $member->resident->id,
                        'name' => $member->resident->name,
                        'national_number_id' => $member->resident->national_number_id,
                        'gender' => $member->resident->gender,
                        'date_of_birth' => $member->resident->date_of_birth->format('Y-m-d'),
                        'age' => $member->resident->age ?? now()->diffInYears($member->resident->date_of_birth),
                    ] : null
                ];
            })->values()->toArray(),
            'created_at' => $familyCard->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $familyCard->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFamilyCardRequest $request): JsonResponse
    {
        DB::beginTransaction();

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

            if (isset($data['publication_date'])) {
                $data['publication_date'] = date('Y-m-d', strtotime($data['publication_date']));
            }

            $familyCard = FamilyCard::create($data);

            DB::commit();

            return ApiResponse::success(
                'Data created successfully',
                [
                    'family_card' => $familyCard->load('region')
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();

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
            $familyCard = FamilyCard::with(['familyMembers.resident'])->find($id);

            if (!$familyCard) {
                return ApiResponse::error(
                    'Family card not found',
                    null,
                    404
                );
            }

            // Get region data from API
            $regionData = null;
            if ($familyCard->region_id) {
                $regionData = $regionServiceClient->findById($familyCard->region_id);
            }

            // Get statistics
            $totalMembers = $familyCard->familyMembers()->count();
            $membersByGender = $this->getMembersByGender($familyCard);
            $membersByAge = $this->getMembersByAge($familyCard);

            // Transform family members with resident region data
            $familyMembers = $familyCard->familyMembers->map(function ($member) use ($regionServiceClient) {
                $memberData = [
                    'id' => $member->id,
                    'resident_id' => $member->resident_id,
                    'relationship' => $member->relationship,
                    'resident' => null
                ];

                if ($member->resident) {
                    $residentRegionData = null;
                    if ($member->resident->region_id) {
                        $residentRegionData = $regionServiceClient->findById($member->resident->region_id);
                    }

                    $memberData['resident'] = [
                        'id' => $member->resident->id,
                        'name' => $member->resident->name,
                        'national_number_id' => $member->resident->national_number_id,
                        'gender' => $member->resident->gender,
                        'date_of_birth' => $member->resident->date_of_birth,
                        'age' => $member->resident->age ?? now()->diffInYears($member->resident->date_of_birth),
                        'region_id' => $member->resident->region_id,
                        'region' => $residentRegionData ? [
                            'id' => $residentRegionData['id'] ?? null,
                            'name' => $residentRegionData['name'] ?? null,
                        ] : null
                    ];
                }

                return $memberData;
            });

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'family_card' => [
                        'id' => $familyCard->id,
                        'head_of_family_name' => $familyCard->head_of_family_name,
                        'address' => $familyCard->address,
                        'publication_date' => $familyCard->publication_date,
                        'region_id' => $familyCard->region_id,
                        'region' => $regionData ? [
                            'id' => $regionData['id'] ?? null,
                            'name' => $regionData['name'] ?? null,
                            'encoded_geometry' => $regionData['encoded_geometry'] ?? null,
                        ] : null,
                        'created_at' => $familyCard->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $familyCard->updated_at->format('Y-m-d H:i:s'),
                    ],
                    'family_members' => $familyMembers,
                    'statistics' => [
                        'total_members' => $totalMembers,
                        'members_by_gender' => $membersByGender,
                        'members_by_age' => $membersByAge,
                        'members_by_relationship' => $familyCard->familyMembers->groupBy('relationship')->map->count(),
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
     * Update the specified resource in storage.
     */
    public function update(UpdateFamilyCardRequest $request, FamilyCard $familyCard): JsonResponse
    {
        DB::beginTransaction();

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

            if (isset($data['publication_date'])) {
                $data['publication_date'] = date('Y-m-d', strtotime($data['publication_date']));
            }

            $familyCard->update($data);

            DB::commit();

            return ApiResponse::success(
                'Data updated successfully',
                [
                    'family_card' => $familyCard->fresh()->load('region')
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();

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
    public function destroy(FamilyCard $familyCard): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Cek apakah ada anggota keluarga
            if ($familyCard->familyMembers()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a family card that still has members',
                    'data' => [
                        'members_count' => $familyCard->familyMembers()->count()
                    ]
                ], 422);
            }

            $familyCard->delete();

            DB::commit();

            return ApiResponse::success(
                'Data deleted successfully',
                null
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(
                'Failed to delete data',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get family card statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $totalFamilyCards = FamilyCard::count();
            $totalMembers = FamilyMember::count();
            $averageMembers = $totalFamilyCards > 0 ? round($totalMembers / $totalFamilyCards, 2) : 0;

            $cardsByRegion = FamilyCard::select('region_id', DB::raw('COUNT(*) as total'))
                ->with('region')
                ->groupBy('region_id')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->region->name ?? 'Unknown' => $item->total];
                });

            $latestCards = FamilyCard::with('region')
                ->orderBy('publication_date', 'desc')
                ->limit(5)
                ->get();

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'total_family_cards' => $totalFamilyCards,
                    'total_family_members' => $totalMembers,
                    'average_members_per_family' => $averageMembers,
                    'cards_by_region' => $cardsByRegion,
                    'latest_cards' => $latestCards,
                    'yearly_distribution' => $this->getYearlyDistribution()
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to fetch statistics',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Add member to family card
     */
    public function addMember(Request $request, FamilyCard $familyCard): JsonResponse
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'resident_id' => 'required|exists:residents,id',
                'relationship' => 'required|string|max:50'
            ]);

            // Cek apakah resident sudah menjadi anggota keluarga lain
            $existingMember = FamilyMember::where('resident_id', $request->resident_id)->first();
            if ($existingMember) {
                return ApiResponse::error(
                    'This resident is already a member of another family card',
                    [
                        'existing_family_card_id' => $existingMember->family_card_id
                    ],
                    422
                );
            }


            $familyMember = $familyCard->familyMembers()->create([
                'resident_id' => $request->resident_id,
                'relationship' => $request->relationship
            ]);

            DB::commit();

            return ApiResponse::success(
                'Data created successfully',
                [
                    'family_member' => $familyMember->load('resident')
                ],
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(
                'Failed to add family member',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get members by gender
     */
    private function getMembersByGender(FamilyCard $familyCard): array
    {
        $members = $familyCard->familyMembers()->with('resident')->get();

        return [
            'male' => $members->where('resident.gender', 'Laki-laki')->count(),
            'female' => $members->where('resident.gender', 'Perempuan')->count(),
        ];
    }

    /**
     * Get members by age group
     */
    private function getMembersByAge(FamilyCard $familyCard): array
    {
        $members = $familyCard->familyMembers()->with('resident')->get();

        return [
            '0-17' => $members->filter(fn($m) => $m->resident->age <= 17)->count(),
            '18-30' => $members->filter(fn($m) => $m->resident->age >= 18 && $m->resident->age <= 30)->count(),
            '31-45' => $members->filter(fn($m) => $m->resident->age >= 31 && $m->resident->age <= 45)->count(),
            '46-60' => $members->filter(fn($m) => $m->resident->age >= 46 && $m->resident->age <= 60)->count(),
            '60+' => $members->filter(fn($m) => $m->resident->age > 60)->count(),
        ];
    }

    /**
     * Get yearly distribution
     */
    private function getYearlyDistribution(): array
    {
        return FamilyCard::selectRaw('YEAR(publication_date) as year, COUNT(*) as total')
            ->whereNotNull('publication_date')
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->year => $item->total];
            })
            ->toArray();
    }

    /**
     * Search available residents for adding to family
     */
    public function searchAvailableResidents(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search' => 'required|string|min:2'
            ]);

            $search = $request->search;

            // Cari warga yang belum menjadi anggota keluarga manapun
            $availableResidents = Resident::whereDoesntHave('familyMember')
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('national_number_id', 'like', "%{$search}%");
                })
                ->with('region')
                ->limit(20)
                ->get();

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'available_residents' => $availableResidents,
                    'total_available' => $availableResidents->count()
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to search available data',
                $e->getMessage(),
                500
            );
        }
    }
}
