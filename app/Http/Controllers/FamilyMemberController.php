<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFamilyMemberRequest;
use App\Http\Requests\UpdateFamilyMemberRequest;
use App\Models\FamilyMember;
use App\Models\FamilyCard;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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

            return response()->json([
                'success' => true,
                'message' => 'Data anggota keluarga berhasil diambil',
                'data' => [
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
    public function store(StoreFamilyMemberRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();

            // Cek apakah resident sudah menjadi anggota keluarga lain
            $existingMember = FamilyMember::where('resident_id', $data['resident_id'])->first();
            if ($existingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warga ini sudah menjadi anggota keluarga lain',
                    'data' => [
                        'existing_family_card_id' => $existingMember->family_card_id
                    ]
                ], 422);
            }

            $familyMember = FamilyMember::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Anggota keluarga berhasil ditambahkan',
                'data' => [
                    'family_member' => $familyMember->load(['familyCard', 'resident'])
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan anggota keluarga',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(FamilyMember $familyMember): JsonResponse
    {
        try {
            $familyMember->load(['familyCard.region', 'resident.region']);

            return response()->json([
                'success' => true,
                'message' => 'Detail anggota keluarga',
                'data' => [
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
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data anggota keluarga',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFamilyMemberRequest $request, FamilyMember $familyMember): JsonResponse
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();

            // Jika mengubah resident_id, cek apakah resident baru sudah menjadi anggota keluarga lain
            if (isset($data['resident_id']) && $data['resident_id'] != $familyMember->resident_id) {
                $existingMember = FamilyMember::where('resident_id', $data['resident_id'])->first();
                if ($existingMember && $existingMember->id != $familyMember->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Warga ini sudah menjadi anggota keluarga lain',
                        'data' => [
                            'existing_family_card_id' => $existingMember->family_card_id
                        ]
                    ], 422);
                }
            }

            $familyMember->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Anggota keluarga berhasil diperbarui',
                'data' => [
                    'family_member' => $familyMember->fresh()->load(['familyCard', 'resident'])
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui anggota keluarga',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FamilyMember $familyMember): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Cek jika ini kepala keluarga
            if ($familyMember->relationship === 'Kepala Keluarga') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus kepala keluarga'
                ], 422);
            }

            $familyCardId = $familyMember->family_card_id;
            $familyMember->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Anggota keluarga berhasil dihapus',
                'data' => [
                    'remaining_members_count' => FamilyMember::where('family_card_id', $familyCardId)->count()
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus anggota keluarga',
                'error' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Anggota keluarga dari ' . $familyCard->head_of_family_name,
                'data' => [
                    'family_card' => $familyCard->only(['id', 'head_of_family_name', 'address']),
                    'family_members' => $familyMembers,
                    'statistics' => $statistics
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil anggota keluarga',
                'error' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Data keluarga warga',
                'data' => [
                    'family_member' => $familyMember,
                    'family_members' => $familyMember->familyCard->familyMembers()->with('resident')->get()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data keluarga',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer family member to another family card
     */
    public function transfer(Request $request, FamilyMember $familyMember): JsonResponse
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'new_family_card_id' => 'required|exists:family_cards,id',
                'new_relationship' => 'required|string|max:50'
            ]);

            // Cek jika ini kepala keluarga
            if ($familyMember->relationship === 'Kepala Keluarga') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat mentransfer kepala keluarga'
                ], 422);
            }

            // Cek apakah resident sudah menjadi anggota keluarga tujuan
            $existingInNewFamily = FamilyMember::where('family_card_id', $request->new_family_card_id)
                ->where('resident_id', $familyMember->resident_id)
                ->exists();

            if ($existingInNewFamily) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warga ini sudah menjadi anggota keluarga tujuan'
                ], 422);
            }

            $oldFamilyCardId = $familyMember->family_card_id;

            $familyMember->update([
                'family_card_id' => $request->new_family_card_id,
                'relationship' => $request->new_relationship
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Anggota keluarga berhasil ditransfer',
                'data' => [
                    'family_member' => $familyMember->fresh()->load(['familyCard', 'resident']),
                    'old_family_members_count' => FamilyMember::where('family_card_id', $oldFamilyCardId)->count(),
                    'new_family_members_count' => FamilyMember::where('family_card_id', $request->new_family_card_id)->count()
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal mentransfer anggota keluarga',
                'error' => $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Statistik hubungan keluarga',
                'data' => [
                    'total_family_members' => $totalMembers,
                    'unique_families' => $uniqueFamilies,
                    'relationship_distribution' => $relationshipStats,
                    'average_members_per_family' => $uniqueFamilies > 0 ? round($totalMembers / $uniqueFamilies, 2) : 0,
                    'most_common_relationship' => $relationshipStats->first()->relationship ?? 'Tidak ada data',
                    'most_common_relationship_count' => $relationshipStats->first()->total ?? 0
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
