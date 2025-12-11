<?php
namespace App\Http\Controllers;

use App\Http\Requests\StorePointOfInterestRequest;
use App\Http\Requests\UpdatePointOfInterestRequest;
use App\Models\PointOfInterest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointOfInterestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PointOfInterest::with('region');

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
            $pois    = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Data points of interest berhasil diambil',
                'data'    => [
                    'points_of_interest' => $pois->items(),
                    'meta'               => [
                        'current_page' => $pois->currentPage(),
                        'last_page'    => $pois->lastPage(),
                        'per_page'     => $pois->perPage(),
                        'total'        => $pois->total(),
                        'from'         => $pois->firstItem(),
                        'to'           => $pois->lastItem(),
                    ],
                    'links'              => [
                        'first' => $pois->url(1),
                        'last'  => $pois->url($pois->lastPage()),
                        'prev'  => $pois->previousPageUrl(),
                        'next'  => $pois->nextPageUrl(),
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
    public function store(StorePointOfInterestRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $poi = PointOfInterest::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Data point of interest berhasil dibuat',
                'data'    => [
                    'point_of_interest' => $poi,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat data point of interest',
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
            $poi = PointOfInterest::with('region')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail data point of interest',
                'data'    => [
                    'point_of_interest' => $poi,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data point of interest',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePointOfInterestRequest $request, $id): JsonResponse
    {
        try {
            $poi  = PointOfInterest::findOrFail($id);
            $data = $request->validated();

            $poi->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Data point of interest berhasil diperbarui',
                'data'    => [
                    'point_of_interest' => $poi->fresh(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data point of interest',
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
            $poi = PointOfInterest::findOrFail($id);
            $poi->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data point of interest berhasil dihapus',
                'data'    => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data point of interest',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
