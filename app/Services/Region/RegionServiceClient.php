<?php

namespace App\Services\Region;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


class RegionServiceClient
{
    /**
     * Fetch single region by ID
     */
    public function findById(int $id)
    {
        $request = Request::create("/api/regions/{$id}", 'GET');
        $response = Route::dispatch($request);

        $data = json_decode($response->getContent(), true);
        $region = data_get($data, 'data.region');

        if (!$region) {
            return null;
        }

        return [
            'id' => data_get($data, 'data.region.id'),
            'name' => data_get($data, 'data.region.name'),
            'encoded_geometry' => data_get($data, 'data.region.encoded_geometry'),
        ];
    }

    /**
     * Fetch multiple regions by IDs (without pagination)
     */
    public function findByIds(array $ids): array
    {
        $request = Request::create(
            '/api/regions',
            'GET',
            [
                'ids' => implode(',', $ids),
                'with_pagination' => false,
            ]
        );

        $response = Route::dispatch($request);
        $data = json_decode($response->getContent(), true);

        $regions = data_get($data, 'data.regions', []);

        return collect($regions)->map(function ($region) {

            return [
                'id' => data_get($region, 'id'),
                'name' => data_get($region, 'name'),
                'encoded_geometry' => data_get($region, 'encoded_geometry'),
            ];
        })->values()->toArray();
    }
}
