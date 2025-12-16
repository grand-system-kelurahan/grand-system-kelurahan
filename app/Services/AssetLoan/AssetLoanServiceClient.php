<?php

namespace App\Services\AssetLoan;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class AssetLoanServiceClient
{
    public function getAssetReport(array $params = [])
    {
        $request = Request::create(
            '/api/assets/report',
            'GET',
            array_filter([
                'asset_type' => $params['asset_type'] ?? null,
                'asset_status' => $params['asset_status'] ?? null,
            ])
        );
        $response = Route::dispatch($request);

        $data = json_decode($response->getContent(), true);
        return $data['data'];
    }

    public function getAssetLoanReport(array $params = []): array
    {
        $request = Request::create(
            '/api/asset-loans/report',
            'GET',
            array_filter([
                'loan_status' => $params['loan_status'] ?? null,
                'asset_type'  => $params['asset_type'] ?? null,
                'from_date'   => $params['from_date'] ?? null,
                'to_date'     => $params['to_date'] ?? null,
            ])
        );

        $response = Route::dispatch($request);
        $data = json_decode($response->getContent(), true);

        return $data['data'] ?? [];
    }
}
