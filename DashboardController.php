<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // ---------- Dashboard Stats ----------
        $totalTools = DB::table('tools')->count();
        $availableItems = DB::table('tools')->where('status', 'Available')->count();
        $issuedItems = DB::table('tools')->where('status', 'Borrowed')->count();
        $forRepair = DB::table('tools')->whereIn('status', ['For Repair', 'Damaged'])->count();

        $lowStockThreshold = 5;
        $lowStock = DB::table('property_inventory')
            ->where('quantity', '<', $lowStockThreshold)
            ->count();

        $missingItems = DB::table('tools')->where('status', 'Lost')->count();

        $inventory = DB::table('tools')
            ->select(
                'tool_name',
                'source_of_fund',
                'classification',
                DB::raw('DATE(date_acquired) as date_acquired')
            )
            ->get();

        // ---------- Form Records Data ----------
        $issuedForms = DB::table('issued_summary')
            ->select('id', 'form_type', 'reference_no', 'created_at', 'student_name', 'item_count', 'status')
            ->orderBy('created_at', 'desc')
            ->get();

        // ---------- Form Summary Counts ----------
        $formSummaryCounts = DB::table('issued_summary')
            ->select(
                DB::raw('COUNT(*) as total_forms'),
                DB::raw("SUM(form_type = 'ICS') as ics_forms"),
                DB::raw("SUM(form_type = 'PAR') as par_forms"),
                DB::raw("SUM(status = 'Active') as active_forms"),
                DB::raw("SUM(status = 'Archived') as archived_forms")
            )
            ->first();

        // ---------- USAGE TREND GRAPH DATA ----------
        $usageData = DB::table('tools')
            ->select('tool_name', DB::raw('SUM(usage_count) as total_usage'))
            ->groupBy('tool_name')
            ->orderBy('total_usage', 'DESC')
            ->get();

        // ---------- Return view ----------
        return view('dashboard', compact(
            'totalTools',
            'availableItems',
            'issuedItems',
            'forRepair',
            'lowStock',
            'missingItems',
            'inventory',
            'issuedForms',
            'formSummaryCounts',
            'usageData'      // <-- ADD THIS TO MAKE GRAPH WORK!
        ));
    }
}
