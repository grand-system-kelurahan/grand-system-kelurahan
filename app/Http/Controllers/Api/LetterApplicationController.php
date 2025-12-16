<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LetterApplication;
use App\Models\LetterType;
use Illuminate\Http\Request;

class LetterApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $applications = LetterApplication::with(['resident', 'letterType'])->get();

        return response()->json($applications);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'resident_id' => 'required|exists:residents,id',
            'letter_type_id' => 'required|exists:letter_types,id',
            'description' => 'nullable|string',
        ]);

        $data['submission_date'] = now()->toDateString();
        $data['status'] = 'new';

        $application = LetterApplication::create($data);

        return response()->json($application, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $application = LetterApplication::with(['resident', 'letterType'])->findOrFail($id);

        return response()->json($application);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $application = LetterApplication::findOrFail($id);

        if ($application->status === 'approved') {
            return response()->json([
                'message' => 'Data yang sudah disetujui tidak dapat diubah.'
            ], 422);
        }

        $data = $request->validate([
            'description' => 'nullable|string',
        ]);

        $application->update($data);

        return response()->json($application);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $application = LetterApplication::findOrFail($id);
        $application->delete();

        return response()->json(null, 204);
    }

    public function approve(Request $request, string $id)
    {
        $application = LetterApplication::findOrFail($id);

        // cegah approve ulang
        if ($application->status === "approved") {
            return response()->json([
                'message' => 'Data yang sudah disetujui tidak dapat disetujui ulang.'
            ], 422);
        }

        // validasi data approver
        $data = $request->validate([
            'approved_by_employee_id' => 'required|string|max:64',
            'approved_by_employee_name' => 'required|string|max:100',
        ]);

        $application->update([
            'status' => 'approved',
            'approval_date' => now()->toDateString(),
            'letter_number' => $this->generateLetterNumber($application),
            'approved_by_employee_id' => $data['approved_by_employee_id'],
            'approved_by_employee_name' => $data['approved_by_employee_name']
        ]);

        return response()->json($application);
    }

    public function reject(Request $request, $id)
    {
        $application = LetterApplication::findOrFail($id);

        // validasi data approver
        $data = $request->validate([
            'approved_by_employee_id' => 'required|string|max:64',
            'approved_by_employee_name' => 'required|string|max:100',
        ]);

        $application->update([
            'status'      => 'rejected',
            'description' => $request->input('description', $application->description),
            'approved_by_employee_id' => $data['approved_by_employee_id'],
            'approved_by_employee_name' => $data['approved_by_employee_name']
        ]);

        return response()->json($application);
    }

    protected function generateLetterNumber(LetterApplication $application): string
    {
        $run = str_pad($application->id, 3, '0', STR_PAD_LEFT);
        $code = $application->letterType->letter_code;
        $month = now()->format('m');
        $year = now()->format('Y');

        return "{$run}/{$code}/{$month}/{$year}";
    }
}
