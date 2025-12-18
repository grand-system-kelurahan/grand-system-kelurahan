<?php
namespace App\Services\ReportWrapper;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class ReportWrapperService
{
    private function callApi($path)
    {
        try {
            if (! $path) {
                throw new \Exception('API path is not configured');
            }

            $request = Request::create($path, 'GET');

            // Add debug information to understand the error
            $response = Route::dispatch($request);
            $data     = json_decode($response->getContent(), true);

            if ($response->getStatusCode() !== 200) {
                // Include more detailed error information for debugging
                return [
                    'error'            => 'API request failed with status: ' . $response->getStatusCode(),
                    'path'             => $path,
                    'response_content' => $response->getContent(),
                    'data'             => $data,
                ];
            }

            // Extract data using data_get() to match ResidentServiceClient pattern
            // Handle both direct data and nested data structures
            return data_get($data, 'data', $data);
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }

    public function getResidentReport()
    {
        $path = "/api/residents/statistics";
        return $this->callApi($path);
    }

    public function getAssetReport()
    {
        $path = "/api/assets/report";
        return $this->callApi($path);
    }

    public function getAssetLoanReport()
    {
        $path = "/api/asset-loans/report";

        // Add logging to debug the issue
        \Log::info('ReportWrapperService: Calling asset loan report', [
            'path'   => $path,
            'caller' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[1]['function'] ?? 'unknown',
        ]);

        $result = $this->callApi($path);

        \Log::info('ReportWrapperService: Asset loan report result', [
            'has_error' => isset($result['error']),
            'error'     => $result['error'] ?? null,
            'path'      => $path,
        ]);

        return $result;
    }

    public function getLetterReport()
    {
        $path = "/api/letter-applications";
        return $this->callApi($path);
    }

    public function getAttendanceReport()
    {
        // Since there's no specific attendance endpoint, we'll return a placeholder
        // This could be implemented later when attendance tracking is added
        return [
            'message' => 'Attendance report not yet implemented',
            'data'    => [
                'total_attendance' => 0,
                'attendance_rate'  => 0,
                'monthly_trends'   => [],
            ],
        ];
    }

    public function getFamilyCardsReport()
    {
        $path = "/api/family-cards/statistics";
        return $this->callApi($path);
    }

    public function generateReport()
    {
        return [
            'resident'     => $this->getResidentReport(),
            'asset'        => $this->getAssetReport(),
            'asset_loan'   => $this->getAssetLoanReport(),
            // 'letter'       => $this->getLetterReport(),
            // 'attendance'   => $this->getAttendanceReport(),
            'family_cards' => $this->getFamilyCardsReport(),
        ];
    }

    /**
     * Preprocess resident data for public consumption
     * Filters out personal information and keeps only summary statistics
     */
    private function preprocessResidentDataForPublic($residentData)
    {
        if (isset($residentData['error'])) {
            return $residentData;
        }

        // Extract summary data
        $summary = data_get($residentData, 'summary', []);

        // Extract gender distribution
        $genderDistribution = data_get($residentData, 'gender_distribution', []);

        // Extract age analysis data
        $ageAnalysis     = data_get($residentData, 'age_analysis', []);
        $ageDistribution = data_get($ageAnalysis, 'distribution', []);
        $ageGroups       = data_get($ageAnalysis, 'age_groups', []);

        // Extract religion statistics (filter out invalid entries)
        $religionStats         = data_get($residentData, 'religion_stats', []);
        $filteredReligionStats = array_filter($religionStats, function ($key) {
            return ! in_array($key, ['awda']);
        }, ARRAY_FILTER_USE_KEY);

        // Extract marital statistics (filter out invalid entries)
        $maritalStats         = data_get($residentData, 'marital_stats', []);
        $filteredMaritalStats = array_filter($maritalStats, function ($key) {
            return ! in_array($key, ['wadd']);
        }, ARRAY_FILTER_USE_KEY);

        // Extract education statistics (filter out invalid entries)
        $educationStats         = data_get($residentData, 'education_stats', []);
        $filteredEducationStats = array_filter($educationStats, function ($key) {
            return ! in_array($key, ['awd']);
        }, ARRAY_FILTER_USE_KEY);

        // Extract occupation statistics (filter out invalid entries)
        $occupationStats         = data_get($residentData, 'occupation_stats', []);
        $filteredOccupationStats = array_filter($occupationStats, function ($key) {
            return ! in_array($key, ['awd']);
        }, ARRAY_FILTER_USE_KEY);

        // Extract geographical distribution
        $geographicalDistribution = data_get($residentData, 'geographical_distribution', []);

        return [
            'summary'                   => [
                'total_residents' => data_get($summary, 'total_residents', 0),
                'average_age'     => data_get($summary, 'average_age', 0),
                'min_age'         => data_get($summary, 'min_age', 0),
                'max_age'         => data_get($summary, 'max_age', 0),
                'total_rt'        => data_get($summary, 'total_rt', 0),
                'total_rw'        => data_get($summary, 'total_rw', 0),
            ],
            'gender_distribution'       => $genderDistribution,
            'age_analysis'              => [
                'distribution' => $ageDistribution,
                'age_groups'   => $ageGroups,
                'average_age'  => data_get($ageAnalysis, 'average_age', 0),
                'min_age'      => data_get($ageAnalysis, 'min_age', 0),
                'max_age'      => data_get($ageAnalysis, 'max_age', 0),
            ],
            'religion_stats'            => $filteredReligionStats,
            'marital_stats'             => $filteredMaritalStats,
            'education_stats'           => $filteredEducationStats,
            'occupation_stats'          => $filteredOccupationStats,
            'geographical_distribution' => [
                'total_rt'        => data_get($geographicalDistribution, 'total_rt', 0),
                'total_rw'        => data_get($geographicalDistribution, 'total_rw', 0),
                'rt_distribution' => data_get($geographicalDistribution, 'rt_distribution', []),
                'rw_distribution' => data_get($geographicalDistribution, 'rw_distribution', []),
            ],
            'summary_only'              => true,
        ];
    }

    /**
     * Preprocess family cards data for public consumption
     * Filters out personal addresses and names, keeps only summary and regional data
     */
    private function preprocessFamilyCardsDataForPublic($familyCardsData)
    {
        if (isset($familyCardsData['error'])) {
            return $familyCardsData;
        }

        // Extract summary data
        $totalFamilyCards        = data_get($familyCardsData, 'total_family_cards', 0);
        $totalFamilyMembers      = data_get($familyCardsData, 'total_family_members', 0);
        $averageMembersPerFamily = data_get($familyCardsData, 'average_members_per_family', 0);

        // Extract cards by region (regional distribution)
        $cardsByRegion = data_get($familyCardsData, 'cards_by_region', []);

        // Extract yearly distribution
        $yearlyDistribution = data_get($familyCardsData, 'yearly_distribution', []);

        return [
            'total_family_cards'         => $totalFamilyCards,
            'total_family_members'       => $totalFamilyMembers,
            'average_members_per_family' => $averageMembersPerFamily,
            'regional_distribution'      => $cardsByRegion,
            'yearly_distribution'        => $yearlyDistribution,
            'summary_only'               => true,
        ];
    }

    /**
     * Preprocess asset data for public consumption
     * Filters out sensitive information and keeps only summary data
     */
    private function preprocessAssetDataForPublic($assetData)
    {
        if (isset($assetData['error'])) {
            return $assetData;
        }

        // Extract data from the asset report structure
        $summary     = data_get($assetData, 'summary', []);
        $groupByType = data_get($assetData, 'group_by_type', []);

        return [
            'total_assets'         => data_get($summary, 'total_assets', 0),
            'asset_categories'     => $groupByType,
            'asset_status_summary' => [
                'total_stock'     => data_get($summary, 'total_stock', 0),
                'available_stock' => data_get($summary, 'available_stock', 0),
                'borrowed_stock'  => data_get($summary, 'borrowed_stock', 0),
            ],
            'summary_only'         => true,
        ];
    }

    /**
     * Preprocess asset loan data for public consumption
     * Filters out personal borrower information and keeps only summary statistics
     */
    private function preprocessAssetLoanDataForPublic($assetLoanData)
    {
        if (isset($assetLoanData['error'])) {
            return $assetLoanData;
        }

        // Extract data from the asset loan report structure
        $summary         = data_get($assetLoanData, 'summary', []);
        $percentage      = data_get($assetLoanData, 'percentage', []);
        $topAssets       = data_get($assetLoanData, 'top_assets', []);
        $activeQuantity  = data_get($assetLoanData, 'active_quantity', 0);
        $groupByType     = data_get($assetLoanData, 'group_by_type', []);
        $averageDuration = data_get($assetLoanData, 'average_duration_days', 0);
        $monthly         = data_get($assetLoanData, 'monthly', []);
        $daily           = data_get($assetLoanData, 'daily', []);
        $groupByAsset    = data_get($assetLoanData, 'group_by_asset', []);

        return [
            'total_active_loans'    => data_get($summary, 'borrowed', 0),
            'total_completed_loans' => data_get($summary, 'returned', 0),
            'loan_statistics'       => [
                'total_loans' => data_get($summary, 'total_loans', 0),
                'requested'   => data_get($summary, 'requested', 0),
                'borrowed'    => data_get($summary, 'borrowed', 0),
                'returned'    => data_get($summary, 'returned', 0),
                'rejected'    => data_get($summary, 'rejected', 0),
                'percentages' => $percentage,
            ],
            'top_assets'            => $topAssets,
            'group_by_asset'        => $groupByAsset,
            'active_quantity'       => $activeQuantity,
            'group_by_type'         => $groupByType,
            'average_duration_days' => $averageDuration,
            'monthly_trends'        => $monthly,
            'daily_trends'          => $daily,
            'summary_only'          => true,
        ];
    }

    public function generatePublicReport()
    {
        return [
            'resident'     => $this->preprocessResidentDataForPublic($this->getResidentReport()),
            'asset'        => $this->preprocessAssetDataForPublic($this->getAssetReport()),
            'asset_loan'   => $this->preprocessAssetLoanDataForPublic($this->getAssetLoanReport()),
            'family_cards' => $this->preprocessFamilyCardsDataForPublic($this->getFamilyCardsReport()),
        ];
    }
}
