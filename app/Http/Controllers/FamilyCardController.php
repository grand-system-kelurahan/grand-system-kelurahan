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

class FamilyCardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FamilyCard::with(['region', 'familyMembers.resident']);

            // Filtering
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('head_of_family_name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('region', function ($q) use ($search) {
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
    public function show(FamilyCard $familyCard): JsonResponse
    {
        try {
            $familyCard->load(['region', 'familyMembers.resident.region']);

            $totalMembers = $familyCard->familyMembers()->count();
            $membersByGender = $this->getMembersByGender($familyCard);
            $membersByAge = $this->getMembersByAge($familyCard);

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'family_card' => $familyCard,
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
