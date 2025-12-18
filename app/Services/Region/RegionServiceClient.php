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
        if (empty($ids)) {
            return [];
        }

        try {
            $request = Request::create(
                '/api/regions',
                'GET',
                [
                    'ids' => implode(',', $ids),
                    'with_pagination' => false,
                ]
            );

            $response = Route::dispatch($request);


            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = json_decode($response->getContent(), true);


            // Handle response format
            $regions = [];
            if (isset($data['data']['regions']) && is_array($data['data']['regions'])) {
                $regions = $data['data']['regions'];
            } elseif (isset($data['data']) && is_array($data['data'])) {
                $regions = $data['data']; // Assuming it's already regions array
            }

            // Map ke format yang diharapkan
            $result = [];
            foreach ($regions as $region) {
                if (isset($region['id'])) {
                    $result[$region['id']] = [
                        'id' => $region['id'],
                        'name' => $region['name'] ?? null,
                        'encoded_geometry' => $region['encoded_geometry'] ?? null,
                    ];
                }
            }


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}
