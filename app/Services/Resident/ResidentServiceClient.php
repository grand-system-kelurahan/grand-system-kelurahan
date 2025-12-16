<?php

namespace App\Services\Resident;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class ResidentServiceClient
{
    /**
     * Fetch single resident by ID
     */
    public function findById(int $id): ?array
    {
        $request = Request::create("/api/residents/{$id}", 'GET');
        $response = Route::dispatch($request);

        $data = json_decode($response->getContent(), true);
        $resident = data_get($data, 'data.resident');

        if (!$resident) {
            return null;
        }

        return [
            'id' => data_get($data, 'data.resident.id'),
            'name' => data_get($data, 'data.resident.name'),
            'gender' => data_get($data, 'data.resident.gender'),
            'national_number_id' => data_get($data, 'data.resident.national_number_id'),
            'family_card_id' => data_get($data, 'data.resident.family_member.family_card_id'),
            'region_id' => data_get($data, 'data.resident.region_id'),
            'region_name' => data_get($data, 'data.resident.region.name'),
        ];
    }

    /**
     * Fetch multiple residents by IDs (without pagination)
     */
    public function findByIds(array $ids): array
    {
        $request = Request::create(
            '/api/residents',
            'GET',
            [
                'ids' => implode(',', $ids),
                'with_pagination' => false,
            ]
        );

        $response = Route::dispatch($request);
        $data = json_decode($response->getContent(), true);

        $residents = data_get($data, 'data.residents', []);

        return collect($residents)->map(function ($resident) {
            return [
                'id' => data_get($resident, 'id'),
                'name' => data_get($resident, 'name'),
                'gender' => data_get($resident, 'gender'),
                'national_number_id' => data_get($resident, 'national_number_id'),
                'family_card_id' => data_get($resident, 'family_member.family_card_id'),
                'region_id' => data_get($resident, 'region_id'),
                'region_name' => data_get($resident, 'region.name'),
            ];
        })->values()->toArray();
    }
}
