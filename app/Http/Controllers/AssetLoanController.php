<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetLoan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Rickgoemans\LaravelApiResponseHelpers\ApiResponse;

class AssetLoanController extends Controller
{
    public function index(Request $request)
    {
        $query = AssetLoan::with('asset');

        // search
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->whereHas('asset', function ($q) use ($keyword) {
                $q->where('asset_name', 'like', "%{$keyword}%")
                    ->orWhere('asset_code', 'like', "%{$keyword}%");
            });
        }

        // filter
        if ($request->filled('loan_status')) {
            $query->where('loan_status', $request->loan_status);
        }

        if ($request->filled('asset_type')) {
            $query->whereHas('asset', function ($q) use ($request) {
                $q->where('asset_type', $request->asset_type);
            });
        }

        if ($request->filled('resident_id')) {
            $query->where('resident_id', $request->resident_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('loan_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('loan_date', '<=', $request->to_date);
        }

        // sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSortBy = [
            'id',
            'loan_date',
            'planned_return_date',
            'actual_return_date',
            'loan_status',
            'created_at',
        ];

        if (!in_array($sortBy, $allowedSortBy)) {
            $sortBy = 'created_at';
        }

        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortBy, $sortOrder);

        // pagination
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $paginator->appends($request->query());

        $data = [
            'asset_loans' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ];

        return ApiResponse::success('Asset loans retrieved successfully.', $data);
    }

    public function show(int $id)
    {
        $loan = AssetLoan::with('asset')->find($id);

        if (!$loan) {
            return APIResponse::error('Asset loan not found.', null, 404);
        }

        return APIResponse::success('Asset loan retrieved successfully.', $loan);
    }

    /**
     * Pengajuan peminjaman aset
     * Status: requested
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'resident_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'loan_date' => 'required|date',
            'planned_return_date' => 'required|date|after_or_equal:loan_date',
            'loan_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', $validator->errors());
        }

        $asset = Asset::find($request->asset_id);

        if ($asset->available_stock < $request->quantity) {
            return ApiResponse::error(
                'Not enough available stock',
                ['quantity' => ['Not enough available stock']]
            );
        }

        $loan = AssetLoan::create([
            'asset_id' => $request->asset_id,
            'resident_id' => $request->resident_id,
            'quantity' => $request->quantity,
            'loan_date' => $request->loan_date,
            'planned_return_date' => $request->planned_return_date,
            'loan_status' => AssetLoan::STATUS_REQUESTED,
            'loan_reason' => $request->loan_reason,
        ]);

        return ApiResponse::success('Loan request created successfully.', $loan, 201);
    }

    /**
     * Persetujuan & penyerahan aset
     * Status: borrowed
     */
    public function approve(int $id)
    {
        $loan = AssetLoan::find($id);

        if (!$loan) {
            return APIResponse::error('Loan request not found.', null, 404);
        }

        if ($loan->loan_status !== AssetLoan::STATUS_REQUESTED) {
            return APIResponse::error(
                'Invalid loan status',
                ['loan_status' => ['Only requested loans can be approved']],
            );
        }

        $asset = Asset::findOrFail($loan->asset_id);

        if ($asset->available_stock < $loan->quantity) {
            return APIResponse::error(
                'The requested quantity exceeds the available stock.',
                ['available_stock' => ['Not enough available stock'],],
            );
        }

        $asset->decrement('available_stock', $loan->quantity);

        $loan->update([
            'loan_status' => AssetLoan::STATUS_BORROWED,
        ]);

        return APIResponse::success('Loan request approved successfully.', $loan);
    }

    /**
     * Pengembalian aset
     * Status: returned
     */
    public function returnAsset(int $id)
    {
        $loan = AssetLoan::find($id);

        if (!$loan) {
            return APIResponse::error('Loan request not found.', null, 404);
        }

        if ($loan->loan_status !== AssetLoan::STATUS_BORROWED) {
            return ApiResponse::error(
                'Invalid loan status',
                ['loan_status' => ['Only borrowed loans can be returned']],
            );
        }

        $asset = Asset::findOrFail($loan->asset_id);

        $asset->increment('available_stock', $loan->quantity);

        $loan->update([
            'loan_status' => AssetLoan::STATUS_RETURNED,
            'actual_return_date' => now()->toDateString(),
        ]);

        return ApiResponse::success('Loan request returned successfully.', $loan);
    }

    /**
     * Penolakan pengajuan
     * Status: rejected
     */
    public function reject(Request $request, int $id)
    {
        $loan = AssetLoan::find($id);

        if (!$loan) {
            return APIResponse::error('Loan request not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'rejected_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return APIResponse::error('Validation failed.', $validator->errors());
        }

        if ($loan->loan_status !== AssetLoan::STATUS_REQUESTED) {
            return APIResponse::error(
                'Invalid loan status',
                ['loan_status' => ['Only requested loans can be rejected']],
            );
        }

        $loan->update([
            'loan_status' => AssetLoan::STATUS_REJECTED,
            'rejected_reason' => $request->rejected_reason,
        ]);

        return APIResponse::success('Loan request rejected successfully.', $loan);
    }

    public function report(Request $request)
    {
        $query = AssetLoan::with('asset');

        if ($request->filled('loan_status')) {
            $query->where('loan_status', $request->loan_status);
        }

        if ($request->filled('asset_type')) {
            $query->whereHas('asset', function ($q) use ($request) {
                $q->where('asset_type', $request->asset_type);
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('loan_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('loan_date', '<=', $request->to_date);
        }

        $loans = $query->get();

        $summary = [
            'total_loans' => $loans->count(),
            'requested' => $loans->where('loan_status', 'requested')->count(),
            'borrowed' => $loans->where('loan_status', 'borrowed')->count(),
            'returned' => $loans->where('loan_status', 'returned')->count(),
            'rejected' => $loans->where('loan_status', 'rejected')->count(),
        ];

        $group_by_asset = $loans->groupBy('asset.asset_name')->map(function ($items) {
            return [
                'total_loans' => $items->count(),
                'total_quantity' => $items->sum('quantity'),
            ];
        });

        // total quantity sedang dipinjam
        $active_quantity = $loans
            ->where('loan_status', 'borrowed')
            ->sum('quantity');

        // persentase status
        $total = max($summary['total_loans'], 1);

        $percentage = [
            'requested' => round(($summary['requested'] / $total) * 100, 2),
            'borrowed' => round(($summary['borrowed'] / $total) * 100, 2),
            'returned' => round(($summary['returned'] / $total) * 100, 2),
            'rejected' => round(($summary['rejected'] / $total) * 100, 2),
        ];

        // group by asset type (item / room)
        $group_by_type = $loans
            ->groupBy('asset.asset_type')
            ->map(function ($items) {
                return [
                    'total_loans' => $items->count(),
                    'total_quantity' => $items->sum('quantity'),
                ];
            });

        // rata-rata durasi peminjaman (returned saja)
        $average_duration = $loans
            ->where('loan_status', 'returned')
            ->filter(fn($loan) => $loan->actual_return_date)
            ->map(function ($loan) {
                return \Carbon\Carbon::parse($loan->loan_date)
                    ->diffInDays(\Carbon\Carbon::parse($loan->actual_return_date));
            })
            ->avg();

        $top_assets = $loans
            ->groupBy('asset.asset_name')
            ->map(fn($items) => $items->sum('quantity'))
            ->sortDesc()
            ->take(5);

        $monthly = $loans
            ->groupBy(fn($loan) => $loan->loan_date->format('Y-m'))
            ->map(fn($items) => $items->count());

        $daily = $loans
            ->groupBy(fn($loan) => $loan->loan_date->format('Y-m-d'))
            ->map(fn($items) => $items->count());

        $data = [
            'summary' => $summary,
            'top_assets' => $top_assets,
            'group_by_asset' => $group_by_asset,
            'percentage' => $percentage,
            'active_quantity' => $active_quantity,
            'group_by_type' => $group_by_type,
            'average_duration_days' => round($average_duration, 2),
            'monthly' => $monthly,
            'daily' => $daily,
        ];

        return APIResponse::success('Loan report generated successfully.', $data);
    }
}
