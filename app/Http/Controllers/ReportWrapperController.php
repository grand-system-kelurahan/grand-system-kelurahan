<?php
namespace App\Http\Controllers;

use App\Services\ReportWrapper\ReportWrapperService;
use Illuminate\Http\Request;
use Rickgoemans\LaravelApiResponseHelpers\ApiResponse;

class ReportWrapperController extends Controller
{
    protected ReportWrapperService $reportWrapperService;

    public function __construct(ReportWrapperService $reportWrapperService)
    {
        $this->reportWrapperService = $reportWrapperService;
    }

    public function getReport(Request $request)
    {
        try {
            $data = $this->reportWrapperService->generateReport();

            return ApiResponse::success(
                'Successfully retrieved integrated report',
                $data
            );
        } catch (\Throwable $e) {
            return ApiResponse::error(
                'Failed to retrieve report',
                $e->getMessage()
            );
        }
    }

    public function getPublicReport(Request $request)
    {
        try {
            $data = $this->reportWrapperService->generatePublicReport();

            return ApiResponse::success(
                'Successfully retrieved integrated public report',
                $data
            );
        } catch (\Throwable $e) {
            return ApiResponse::error(
                'Failed to retrieve public report',
                $e->getMessage()
            );
        }
    }
}
