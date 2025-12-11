<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreResidentHouseRequest;
use App\Http\Requests\UpdateResidentHouseRequest;
use App\Models\ResidentHouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResidentHouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ResidentHouse::with(['region', 'resident']);

            // Filtering
            if ($request->filled('search')) {
                $query->where('name', 'like', "%{$request->search}%");
            }

            // Sorting
            $sortField     = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_dir', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Pagination
            $perPage = intval($request->get('per_page', 20));
            $houses  = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Data rumah warga berhasil diambil',
                'data'    => [
                    'houses' => $houses->items(),
                    'meta'   => [
                        'current_page' => $houses->currentPage(),
                        'last_page'    => $houses->lastPage(),
                        'per_page'     => $houses->perPage(),
                        'total'        => $houses->total(),
                        'from'         => $houses->firstItem(),
                        'to'           => $houses->lastItem(),
                    ],
                    'links'  => [
                        'first' => $houses->url(1),
                        'last'  => $houses->url($houses->lastPage()),
                        'prev'  => $houses->previousPageUrl(),
                        'next'  => $houses->nextPageUrl(),
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreResidentHouseRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $house = ResidentHouse::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Data rumah warga berhasil dibuat',
                'data'    => [
                    'house' => $house,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat data rumah warga',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        try {
            $house = ResidentHouse::with(['region', 'resident'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail data rumah warga',
                'data'    => [
                    'house' => $house,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data rumah warga',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateResidentHouseRequest $request, $id): JsonResponse
    {
        try {
            $house = ResidentHouse::findOrFail($id);
            $data  = $request->validated();

            $house->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Data rumah warga berhasil diperbarui',
                'data'    => [
                    'house' => $house->fresh(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data rumah warga',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $house = ResidentHouse::findOrFail($id);
            $house->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data rumah warga berhasil dihapus',
                'data'    => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data rumah warga',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
