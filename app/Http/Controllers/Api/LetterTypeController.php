<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LetterType;
use Illuminate\Http\Request;

class LetterTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(LetterType::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'letter_code' => 'required|string|max:20|unique:letter_types,letter_code',
            'letter_name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $letterType = LetterType::create($data);

        return response()->json($letterType, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $letterType = LetterType::findOrFail($id);
        return response()->json($letterType);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $letterType = LetterType::findOrFail($id);

        $data = $request->validate([
            'letter_code' => 'sometimes|string|max:20|unique:letter_types,letter_code,' . $letterType->id,
            'letter_name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
        ]);

        $letterType->update($data);

        return response()->json($letterType);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $letterType = LetterType::findOrFail($id);
        $letterType->delete();

        return response()->json(null, 204);
    }
}
