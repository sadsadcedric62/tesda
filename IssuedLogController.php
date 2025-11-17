<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class IssuedLogController extends Controller
{
    /**
     * Return student matches for suggestions
     */
    public function searchStudents(Request $request)
    {
        $q = $request->get('query', '');
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $students = DB::table('student')
            ->select('id', 'student_name', 'student_number', 'batch')
            ->where('student_name', 'LIKE', "%{$q}%")
            ->limit(10)
            ->get();

        return response()->json($students);
    }

    /**
     * Return available serial numbers for tools
     */
    public function availableSerials(Request $request)
    {
        try {
            $query = DB::table('tools')->where('status', 'Available');

            if ($request->has('property_no') && $request->property_no != '') {
                $query->where('property_no', $request->property_no);
            }

            $tools = $query->get(['serial_no', 'property_no', 'tool_name']);

            return response()->json($tools);

        } catch (\Exception $e) {
            \Log::error('Available serials error: '.$e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    /**
     * Quick check if reference number already exists
     */
    public function checkReference($reference)
    {
        // Check in issued_summary table only
        $exists = DB::table('issued_summary')
            ->where('reference_no', $reference)
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Store the issuance and update tools
     */
    public function store(Request $request)
{
    $data = $request->validate([
        'student_name' => 'required|string',
        'selected_serials' => 'required|array|min:1',
        'selected_serials.*' => 'required|string',
        'form_type' => ['required', Rule::in(['ICS','PAR'])],
        'issued_date' => 'required|date',
        'return_date' => 'nullable|date|after_or_equal:issued_date',
        'reference_no' => 'required|string|unique:issued_summary,reference_no',
    ]);

    $issuedDate = Carbon::parse($data['issued_date'])->toDateString();
    $returnDate = $data['return_date'] ? Carbon::parse($data['return_date'])->toDateString() : null;

    DB::beginTransaction();
    try {
        $propertyNos = [];

        foreach ($data['selected_serials'] as $serial) {
            $tool = DB::table('tools')->where('serial_no', $serial)->first();

            if (!$tool) {
                throw new \Exception("Serial number {$serial} not found.");
            }

            $propertyNo = $tool->property_no;
            $propertyNos[] = $propertyNo;

            // Insert into issued_log
            DB::table('issued_log')->insert([
                'student_name' => $data['student_name'],
                'serial_no' => $serial,
                'property_no' => $propertyNo,
                'form_type' => $data['form_type'],
                'issued_date' => $issuedDate,
                'return_date' => $returnDate,
                'reference_no' => $data['reference_no'],
            ]);

            // Update tool status and usage_count safely
            DB::table('tools')->where('serial_no', $serial)
                ->update([
                    'status' => 'Issued',
                    'usage_count' => DB::raw('COALESCE(usage_count, 0) + 1'),
                    'updated_at' => now()
                ]);
        }

        // Insert into issued_summary
        DB::table('issued_summary')->insert([
            'form_type' => $data['form_type'],
            'student_name' => $data['student_name'],
            'item_count' => count($data['selected_serials']),
            'status' => 'Active',
            'reference_no' => $data['reference_no'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Form saved successfully',
            'data' => $data,
            'property_nos' => $propertyNos
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('IssuedLog store error: '.$e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Server error: '.$e->getMessage()
        ], 500);
    }
}
}
