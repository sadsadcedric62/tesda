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
        $lowStock = DB::table('property_inventory')->where('quantity', '<', $lowStockThreshold)->count();
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
$issuedForms = DB::table('issued_summary as s')
    ->leftJoin('issued_log as l', function($join) {
        $join->on('s.student_name', '=', 'l.student_name')
             ->on('s.form_type', '=', 'l.form_type');
    })
    ->select(
        's.id',
        's.form_type',
        DB::raw('GROUP_CONCAT(DISTINCT l.reference_no) as reference_no'),
        's.created_at',
        's.student_name',
        's.item_count',
        's.status'
    )
    ->groupBy(
        's.id',
        's.form_type',
        's.created_at',
        's.student_name',
        's.item_count',
        's.status'
    )
    ->orderBy('s.created_at', 'desc')
    ->get();

// ---------- Form Summary Counts ----------
$totalForms = $issuedForms->count();
$icsForms = $issuedForms->where('form_type', 'ICS')->count();
$parForms = $issuedForms->where('form_type', 'PAR')->count();
$activeForms = $issuedForms->where('status', 'Active')->count();
$archivedForms = $issuedForms->where('status', 'Archived')->count();

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
    'totalForms',
    'icsForms',
    'parForms',
    'activeForms',
    'archivedForms'
));
    }
}
