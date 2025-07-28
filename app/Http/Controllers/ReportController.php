<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use App\Models\AuditLog;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    // GET /api/reports/exeats
    public function exeats(Request $request)
    {
        $exeats = ExeatRequest::all();
        // For demo, return as JSON; in real app, stream CSV/Excel
        return response()->json(['exeats' => $exeats]);
    }

    // GET /api/reports/audit-logs
    public function auditLogs(Request $request)
    {
        $logs = AuditLog::all();
        // For demo, return as JSON; in real app, stream CSV/Excel
        return response()->json(['audit_logs' => $logs]);
    }
}
