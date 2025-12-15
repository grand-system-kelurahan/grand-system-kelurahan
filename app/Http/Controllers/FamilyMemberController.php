<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFamilyMemberRequest;
use App\Http\Requests\UpdateFamilyMemberRequest;
use App\Models\FamilyMember;
use App\Models\FamilyCard;
use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Rickgoemans\LaravelApiResponseHelpers\ApiResponse;

class FamilyMemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FamilyMember::with(['familyCard.region', 'resident.region']);

            // Filtering
            if ($request->has('family_card_id')) {
                $query->where('family_card_id', $request->family_card_id);
            }

            if ($request->has('resident_id')) {
                $query->where('resident_id', $request->resident_id);
            }

            if ($request->has('relationship')) {
                $query->where('relationship', $request->relationship);
            }

            // Search
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('resident', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('national_number_id', 'like', "%{$search}%");
                    })
                        ->orWhereHas('familyCard', function ($q) use ($search) {
                            $q->where('head_of_family_name', 'like', "%{$search}%");
                        });
                });
            }

            // Sorting
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_dir', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $familyMembers = $query->paginate($perPage);

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'family_members' => $familyMembers->items(),
                    'meta' => [
                        'current_page' => $familyMembers->currentPage(),
                        'last_page' => $familyMembers->lastPage(),
                        'per_page' => $familyMembers->perPage(),
                        'total' => $familyMembers->total(),
                        'from' => $familyMembers->firstItem(),
                        'to' => $familyMembers->lastItem(),
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
    public function store(StoreFamilyMemberRequest $request, FamilyCard $familyCard): JsonResponse
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();


            // Tambahkan family_card_id dari route parameter
            $data['family_card_id'] = $familyCard->id;

            // Cek apakah resident sudah menjadi anggota keluarga lain
            $existingMember = FamilyMember::where('resident_id', $data['resident_id'])->first();
            if ($existingMember) {
                return ApiResponse::success(
                    'This resident is already a member of another family card',
                    [
                        'existing_family_card_id' => $existingMember->family_card_id,
                        'existing_family_head' => $existingMember->familyCard->head_of_family_name
                    ],
                    422
                );
            }

            // Validasi tambahan: cek apakah resident sudah ada di keluarga yang sama
            $alreadyInThisFamily = FamilyMember::where('family_card_id', $familyCard->id)
                ->where('resident_id', $data['resident_id'])
                ->exists();

            if ($alreadyInThisFamily) {
                return ApiResponse::success(
                    'This resident is already a member of this family card',
                    null,
                    422
                );
            }

            $familyMember = FamilyMember::create($data);

            DB::commit();

            return ApiResponse::success(
                'Family member created successfully',
                [
                    'family_member' => $familyMember->load(['familyCard', 'resident']),
                    'family_card_info' => [
                        'id' => $familyCard->id,
                        'head_of_family' => $familyCard->head_of_family_name,
                        'total_members' => $familyCard->familyMembers()->count()
                    ]
                ],
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(
                'Failed to create family member',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(FamilyMember $familyMember): JsonResponse
    {
        try {
            $familyMember->load(['familyCard.region', 'resident.region']);

            return ApiResponse::success(
                'Data fetched successfully',
                [
                    'family_member' => $familyMember,
                    'family_info' => [
                        'total_members_in_family' => $familyMember->familyCard->familyMembers()->count(),
                        'head_of_family' => $familyMember->familyCard->head_of_family_name,
                        'family_address' => $familyMember->familyCard->address
                    ],
                    'resident_info' => [
                        'age' => $familyMember->resident->age,
                        'gender' => $familyMember->resident->gender,
                        'occupation' => $familyMember->resident->occupation
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
     * Update the specified resource in storage.
     */
    public function update(UpdateFamilyMemberRequest $request, FamilyCard $familyCard, FamilyMember $familyMember): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Validasi 1: Pastikan family member milik family card yang benar
            if ($familyMember->family_card_id != $familyCard->id) {
                return ApiResponse::success(
                    'Family member not found in this family card',
                    [
                        'requested_family_card_id' => $familyCard->id,
                        'member_family_card_id' => $familyMember->family_card_id
                    ],
                    404
                );
            }

            $data = $request->validated();

            // Validasi 2: Jika mengubah relationship menjadi "Kepala Keluarga"
            if (isset($data['relationship']) && strtolower($data['relationship']) === 'kepala keluarga') {
                // Cek apakah sudah ada kepala keluarga lain di keluarga ini
                $existingHead = FamilyMember::where('family_card_id', $familyCard->id)
                    ->where('relationship', 'Kepala Keluarga')
                    ->where('id', '!=', $familyMember->id)
                    ->first();

                if ($existingHead) {
                    return ApiResponse::success(
                        'There is already a head of family in this family card',
                        [
                            'current_head_of_family' => [
                                'id' => $existingHead->id,
                                'resident_name' => $existingHead->resident->name ?? 'Tidak diketahui',
                                'resident_id' => $existingHead->resident_id
                            ]
                        ],
                        422
                    );
                }
            }

            // Validasi 3: Jika mengubah resident_id
            if (isset($data['resident_id']) && $data['resident_id'] != $familyMember->resident_id) {
                // Cek apakah resident baru ada
                $newResident = Resident::find($data['resident_id']);
                if (!$newResident) {
                    return ApiResponse::success(
                        'Resident not found',
                        null,
                        404
                    );
                }

                // Cek apakah resident baru sudah menjadi anggota keluarga lain
                $existingMember = FamilyMember::where('resident_id', $data['resident_id'])
                    ->where('id', '!=', $familyMember->id)
                    ->first();

                if ($existingMember) {
                    return ApiResponse::success(
                        'Resident is already a member of another family card',
                        [
                            'existing_family_card' => [
                                'id' => $existingMember->family_card_id,
                                'head_of_family' => $existingMember->familyCard->head_of_family_name ?? 'Tidak diketahui'
                            ]
                        ],
                        422
                    );
                }

                // Cek apakah resident baru sudah ada di keluarga yang sama (jika pindah resident)
                $alreadyInSameFamily = FamilyMember::where('family_card_id', $familyCard->id)
                    ->where('resident_id', $data['resident_id'])
                    ->where('id', '!=', $familyMember->id)
                    ->exists();

                if ($alreadyInSameFamily) {
                    return ApiResponse::success(
                        'Resident is already a member of this family card',
                        null,
                        422
                    );
                }
            }

            // Simpan data lama untuk logging/response
            $oldData = [
                'resident_id' => $familyMember->resident_id,
                'resident_name' => $familyMember->resident->name ?? 'Tidak diketahui',
                'relationship' => $familyMember->relationship,
                'updated_at' => $familyMember->updated_at
            ];

            // Update data
            $familyMember->update($data);

            DB::commit();

            // Load data terbaru dengan relasi
            $updatedMember = $familyMember->fresh()->load(['familyCard.region', 'resident.region']);

            return ApiResponse::success(
                'Family member updated successfully',
                [
                    'family_member' => $updatedMember,
                    'changes' => [
                        'old_data' => $oldData,
                        'new_data' => [
                            'resident_id' => $updatedMember->resident_id,
                            'resident_name' => $updatedMember->resident->name ?? 'Tidak diketahui',
                            'relationship' => $updatedMember->relationship
                        ],
                        'changed_fields' => array_keys($data)
                    ],
                    'family_info' => [
                        'id' => $familyCard->id,
                        'head_of_family' => $familyCard->head_of_family_name,
                        'total_members' => $familyCard->familyMembers()->count(),
                        'head_of_family_exists' => $familyCard->familyMembers()
                            ->where('relationship', 'Kepala Keluarga')
                            ->exists()
                    ]
                ],
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(
                'Failed to update family member',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FamilyCard $familyCard, FamilyMember $familyMember): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Validasi: pastikan familyMember benar-benar milik familyCard ini
            if ($familyMember->family_card_id !== $familyCard->id) {
                return ApiResponse::success(
                    'Family member not found in this family card',
                    [
                        'requested_family_card_id' => $familyCard->id,
                        'member_family_card_id' => $familyMember->family_card_id
                    ],
                    404
                );
            }

            // Simpan informasi sebelum menghapus untuk response
            $deletedMemberInfo = [
                'id' => $familyMember->id,
                'resident_id' => $familyMember->resident_id,
                'resident_name' => $familyMember->resident->name ?? 'Unknown',
                'relationship' => $familyMember->relationship
            ];

            $familyMember->delete();

            DB::commit();

            // Hitung ulang jumlah anggota
            $remainingCount = FamilyMember::where('family_card_id', $familyCard->id)->count();

            return ApiResponse::success(
                'Family member deleted successfully',
                [
                    'deleted_member' => $deletedMemberInfo,
                    'family_card_info' => [
                        'id' => $familyCard->id,
                        'head_of_family' => $familyCard->head_of_family_name,
                        'remaining_members_count' => $remainingCount,
                        'has_members' => $remainingCount > 0
                    ]
                ]
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
     * Get family members by family card
     */
    public function getByFamilyCard(FamilyCard $familyCard, Request $request): JsonResponse
    {
        try {
            $query = $familyCard->familyMembers()->with('resident');

            // Filter by relationship
            if ($request->has('relationship')) {
                $query->where('relationship', $request->relationship);
            }

            // Sorting
            $sortField = $request->get('sort_by', 'relationship');
            $sortDirection = $request->get('sort_dir', 'asc');
            $query->orderBy($sortField, $sortDirection);

            $familyMembers = $query->get();

            $statistics = [
                'total_members' => $familyMembers->count(),
                'head_of_family' => $familyMembers->where('relationship', 'Kepala Keluarga')->first(),
                'by_relationship' => $familyMembers->groupBy('relationship')->map->count(),
                'by_gender' => [
                    'male' => $familyMembers->where('resident.gender', 'Laki-laki')->count(),
                    'female' => $familyMembers->where('resident.gender', 'Perempuan')->count(),
                ],
                'by_age_group' => [
                    '0-17' => $familyMembers->filter(fn($m) => $m->resident->age <= 17)->count(),
                    '18-30' => $familyMembers->filter(fn($m) => $m->resident->age >= 18 && $m->resident->age <= 30)->count(),
                    '31-45' => $familyMembers->filter(fn($m) => $m->resident->age >= 31 && $m->resident->age <= 45)->count(),
                    '46-60' => $familyMembers->filter(fn($m) => $m->resident->age >= 46 && $m->resident->age <= 60)->count(),
                    '60+' => $familyMembers->filter(fn($m) => $m->resident->age > 60)->count(),
                ]
            ];

            return ApiResponse::success(
                'Family members fetched successfully',
                [
                    'family_card' => $familyCard->only(['id', 'head_of_family_name', 'address']),
                    'family_members' => $familyMembers,
                    'statistics' => $statistics
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
     * Get family member by resident
     */
    public function getByResident($residentId): JsonResponse
    {
        try {
            $familyMember = FamilyMember::with(['familyCard.region', 'resident.region'])
                ->where('resident_id', $residentId)
                ->first();

            if (!$familyMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warga tidak ditemukan dalam keluarga manapun',
                    'data' => null
                ], 404);
            }

            return ApiResponse::success(
                'Family card fetched successfully',
                [
                    'family_member' => $familyMember,
                    'family_members' => $familyMember->familyCard->familyMembers()->with('resident')->get()
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
     * Get family relationship statistics
     */
    public function relationshipStatistics(): JsonResponse
    {
        try {
            $totalMembers = FamilyMember::count();

            $relationshipStats = FamilyMember::selectRaw('relationship, COUNT(*) as total')
                ->groupBy('relationship')
                ->orderBy('total', 'desc')
                ->get();

            $uniqueFamilies = FamilyMember::distinct('family_card_id')->count('family_card_id');

            return ApiResponse::success(
                'Family relationship statistics fetched successfully',
                [
                    'total_family_members' => $totalMembers,
                    'unique_families' => $uniqueFamilies,
                    'relationship_distribution' => $relationshipStats,
                    'average_members_per_family' => $uniqueFamilies > 0 ? round($totalMembers / $uniqueFamilies, 2) : 0,
                    'most_common_relationship' => $relationshipStats->first()->relationship ?? 'Tidak ada data',
                    'most_common_relationship_count' => $relationshipStats->first()->total ?? 0
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
