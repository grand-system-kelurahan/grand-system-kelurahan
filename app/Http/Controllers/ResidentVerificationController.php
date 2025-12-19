<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use App\Models\ResidentVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ResidentVerificationController extends Controller
{
    // List verifikasi pending
    public function index(Request $request)
    {
        try {
            $query = ResidentVerification::with(['resident', 'verifier'])
                ->latest();

            $withPagination = $request->get('with_pagination', 'true') === 'true';

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Search by resident name or NIK
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('resident', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('national_number_id', 'like', "%{$search}%");
                });
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Filter by verified_by
            if ($request->has('verified_by')) {
                $query->where('verified_by', $request->verified_by);
            }

            // Sorting dengan validasi
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_dir', 'desc');

            $allowedSortFields = [
                'id',
                'status',
                'created_at',
                'updated_at',
                'verified_at'
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

                $data = [
                    'verifications' => $paginator->items(),
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


                $data = $query->get();
            }

            return response()->json([
                'success' => true,
                'message' => 'Data verifikasi berhasil diambil',
                'data' => $data,
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data verifikasi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Detail verifikasi
    public function show($id)
    {
        $verification = ResidentVerification::with(['resident', 'verifier'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $verification,
        ]);
    }

    // Create new verification (bisa otomatis atau manual)
    public function store(Request $request)
    {
        try {
            // Validasi input utama
            $validated = $request->validate([
                'resident_id' => 'required|integer|exists:residents,id',
                'status' => 'required|in:pending,verified,rejected',
                'notes' => 'nullable|string|max:500',
                'verified_data' => 'required|string',
                'verified_by' => 'required|numeric|exists:users,id',
                'verified_at' => 'nullable|date',
            ]);

            // Cek apakah sudah ada verifikasi pending
            $existing = ResidentVerification::where('resident_id', $validated['resident_id'])
                ->where('status', 'pending')
                ->exists();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Penduduk sudah memiliki verifikasi yang sedang diproses',
                ], 422);
            }

            // Decode verified_data untuk validasi lebih lanjut
            $verifiedData = json_decode($validated['verified_data'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format verified_data tidak valid',
                ], 422);
            }

            // Validasi data resident dalam verified_data
            $residentRules = [
                'national_number_id' => 'required|string|max:16',
                'name' => 'required|string|max:100',
                'gender' => 'required|in:male,female',
                'place_of_birth' => 'required|string|max:50',
                'date_of_birth' => 'required|date',
                'religion' => 'required|string|max:20',
                'rt' => 'required|string|max:3',
                'rw' => 'required|string|max:3',
                'education' => 'required|string|max:50',
                'occupation' => 'required|string|max:50',
                'marital_status' => 'required|string|max:20',
                'citizenship' => 'required|string|max:3',
                'blood_type' => 'required|string|max:3',
                'disabilities' => 'required|string|max:50',
                'father_name' => 'required|string|max:100',
                'mother_name' => 'required|string|max:100',
                'region_id' => 'nullable|integer|exists:regions,id',
                'created_at' => 'nullable|date',
                'updated_at' => 'nullable|date',
            ];

            $validator = Validator::make($verifiedData, $residentRules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data resident dalam verified_data tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Buat verifikasi
            $verification = ResidentVerification::create([
                'resident_id' => $validated['resident_id'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'verified_data' => $validated['verified_data'],
                'verified_at' => $validated['status'] !== 'pending'
                    ? ($validated['verified_at'] ?? now())
                    : null,
                'verified_by' => $validated['verified_by'] ?? null
            ]);

            // Log aktivitas

            return response()->json([
                'success' => true,
                'message' => 'Verifikasi berhasil dibuat',
                'data' => $verification->load(['resident', 'verifier']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat verifikasi',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    // Approve verification
    public function approve(Request $request, $id)
    {
        $verification = ResidentVerification::with('resident')->findOrFail($id);

        if ($verification->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Verification is not in pending status',
            ], 422);
        }

        DB::transaction(function () use ($verification, $request) {
            $verification->update([
                'status' => 'verified',
                'verified_by' => $request->user()->id,
                'verified_data' => $verification->resident->toArray(), // Simpan data saat diverifikasi
                'verified_at' => now(),
                'notes' => $request->notes ?? $verification->notes,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Resident verified successfully',
            'data' => $verification->fresh(),
        ]);
    }

    // Reject verification
    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $verification = ResidentVerification::findOrFail($id);

        if ($verification->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Verification is not in pending status',
            ], 422);
        }

        $verification->update([
            'status' => 'rejected',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'notes' => "Ditolak: " . $request->rejection_reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Verification rejected',
            'data' => $verification,
        ]);
    }

    // Statistics
    public function statistics()
    {
        $stats = ResidentVerification::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = "verified" THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected
        ')->first();

        $recent = ResidentVerification::with('resident')
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'statistics' => $stats,
                'recent_verifications' => $recent,
            ],
        ]);
    }

    // Bulk create verifications (misal untuk import data)
    public function bulkCreate(Request $request)
    {
        $request->validate([
            'resident_ids' => 'required|array',
            'resident_ids.*' => 'exists:residents,id',
        ]);

        $created = [];

        foreach ($request->resident_ids as $residentId) {
            // Skip jika sudah ada verifikasi pending
            $existing = ResidentVerification::where('resident_id', $residentId)
                ->where('status', 'pending')
                ->exists();

            if (!$existing) {
                $verification = ResidentVerification::create([
                    'resident_id' => $residentId,
                    'status' => 'pending',
                ]);
                $created[] = $verification;
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' verifications created',
            'data' => $created,
        ]);
    }
}
