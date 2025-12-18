<?php
namespace App\Services\ReportWrapper;

use Illuminate\Support\Facades\Http;

class ReportWrapperService
{
    private function callApi($url)
    {
        try {
            if (! $url) {
                throw new \Exception('API URL is not configured');
            }

            $token = env('API_WRAPPER_TOKEN');
            if (! $token) {
                throw new \Exception('API authentication token is not configured');
            }

            return Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])
                ->get($url)
                ->json();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getResidentReport()
    {
        $url = env('API_RESIDENT_URL', 'http://localhost:8000') . "/api/residents/statistics";
        return $this->callApi($url);
    }

    public function getAssetReport()
    {
        $url = env('API_ASSET_URL', 'http://localhost:8000') . "/api/assets/report";
        return $this->callApi($url);
    }

    public function getAssetLoanReport()
    {
        $url = env('API_ASSET_URL', 'http://localhost:8000') . "/api/asset-loans/report";
        return $this->callApi($url);
    }

    public function getLetterReport()
    {
        $url = env('API_LETTER_URL', 'http://localhost:8000') . "/api/report";
        return $this->callApi($url);
    }

    public function getAttendanceReport()
    {
        $url = env('API_ATTENDANCE_URL', 'http://localhost:8000') . "/api/report";
        return $this->callApi($url);
    }

    public function getFamilyCardsReport()
    {
        $url = env('API_RESIDENT_URL', 'http://localhost:8000') . "/api/family-cards/statistics";
        return $this->callApi($url);
    }

    public function generateReport()
    {
        return [
            'resident'     => $this->preprocessResidentDataForAdmin($this->getResidentReport()),
            'asset'        => $this->preprocessAssetDataForAdmin($this->getAssetReport()),
            'asset_loan'   => $this->preprocessAssetLoanDataForAdmin($this->getAssetLoanReport()),
            'letter'       => $this->getLetterReport(),
            'attendance'   => $this->getAttendanceReport(),
            'family_cards' => $this->preprocessFamilyCardsDataForAdmin($this->getFamilyCardsReport()),
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

        return [
            'total_residents'     => $residentData['total_residents'] ?? 0,
            'gender_distribution' => $residentData['gender_distribution'] ?? [],
            'age_groups'          => $residentData['age_groups'] ?? [],
            'summary_only'        => true,
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

        return [
            'total_family_cards'    => $familyCardsData['total_family_cards'] ?? 0,
            'total_family_members'  => $familyCardsData['total_family_members'] ?? 0,
            'regional_distribution' => $familyCardsData['regional_distribution'] ?? [],
            'summary_only'          => true,
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

        return [
            'total_assets'         => $assetData['total_assets'] ?? 0,
            'asset_categories'     => $assetData['asset_categories'] ?? [],
            'asset_status_summary' => $assetData['asset_status_summary'] ?? [],
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

        return [
            'total_active_loans'    => $assetLoanData['total_active_loans'] ?? 0,
            'total_completed_loans' => $assetLoanData['total_completed_loans'] ?? 0,
            'loan_statistics'       => $assetLoanData['loan_statistics'] ?? [],
            'summary_only'          => true,
        ];
    }

    /**
     * Preprocess resident data for admin consumption
     * Organizes resident statistics with all details for admin use
     */
    private function preprocessResidentDataForAdmin($residentData)
    {
        if (isset($residentData['error'])) {
            return $residentData;
        }

        return [
            'total_residents'       => $residentData['total_residents'] ?? 0,
            'gender_distribution'   => $residentData['gender_distribution'] ?? [],
            'age_groups'            => $residentData['age_groups'] ?? [],
            'education_levels'      => $residentData['education_levels'] ?? [],
            'employment_status'     => $residentData['employment_status'] ?? [],
            'marital_status'        => $residentData['marital_status'] ?? [],
            'religion_distribution' => $residentData['religion_distribution'] ?? [],
            'detailed_breakdown'    => $residentData['detailed_breakdown'] ?? [],
            'admin_data'            => true,
        ];
    }

    /**
     * Preprocess family cards data for admin consumption
     * Organizes family cards with all details including addresses for admin use
     */
    private function preprocessFamilyCardsDataForAdmin($familyCardsData)
    {
        if (isset($familyCardsData['error'])) {
            return $familyCardsData;
        }

        return [
            'total_family_cards'    => $familyCardsData['total_family_cards'] ?? 0,
            'total_family_members'  => $familyCardsData['total_family_members'] ?? 0,
            'regional_distribution' => $familyCardsData['regional_distribution'] ?? [],
            'family_details'        => $familyCardsData['family_details'] ?? [],
            'address_information'   => $familyCardsData['address_information'] ?? [],
            'regional_data'         => $familyCardsData['regional_data'] ?? [],
            'member_demographics'   => $familyCardsData['member_demographics'] ?? [],
            'admin_data'            => true,
        ];
    }

    /**
     * Preprocess asset data for admin consumption
     * Organizes asset data with full details for admin use
     */
    private function preprocessAssetDataForAdmin($assetData)
    {
        if (isset($assetData['error'])) {
            return $assetData;
        }

        return [
            'total_assets'         => $assetData['total_assets'] ?? 0,
            'asset_categories'     => $assetData['asset_categories'] ?? [],
            'asset_status_summary' => $assetData['asset_status_summary'] ?? [],
            'asset_details'        => $assetData['asset_details'] ?? [],
            'asset_locations'      => $assetData['asset_locations'] ?? [],
            'asset_conditions'     => $assetData['asset_conditions'] ?? [],
            'asset_values'         => $assetData['asset_values'] ?? [],
            'full_categorization'  => $assetData['full_categorization'] ?? [],
            'admin_data'           => true,
        ];
    }

    /**
     * Preprocess asset loan data for admin consumption
     * Organizes asset loan data with borrower information for admin use
     */
    private function preprocessAssetLoanDataForAdmin($assetLoanData)
    {
        if (isset($assetLoanData['error'])) {
            return $assetLoanData;
        }

        return [
            'total_active_loans'    => $assetLoanData['total_active_loans'] ?? 0,
            'total_completed_loans' => $assetLoanData['total_completed_loans'] ?? 0,
            'loan_statistics'       => $assetLoanData['loan_statistics'] ?? [],
            'borrower_information'  => $assetLoanData['borrower_information'] ?? [],
            'loan_details'          => $assetLoanData['loan_details'] ?? [],
            'loan_history'          => $assetLoanData['loan_history'] ?? [],
            'overdue_loans'         => $assetLoanData['overdue_loans'] ?? [],
            'loan_trends'           => $assetLoanData['loan_trends'] ?? [],
            'admin_data'            => true,
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
