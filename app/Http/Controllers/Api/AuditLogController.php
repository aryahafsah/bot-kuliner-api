<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiAuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'method' => 'nullable|string|in:GET,POST,PUT,PATCH,DELETE',
            'path' => 'nullable|string|max:255',
            'status' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = ApiAuditLog::query()->with('user:id,name,email');

        if (isset($validated['method'])) {
            $query->where('method', $validated['method']);
        }

        if (isset($validated['path'])) {
            $query->where('path', 'like', '%'.$validated['path'].'%');
        }

        if (isset($validated['status'])) {
            $query->where('response_status', $validated['status']);
        }

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved successfully',
            'data' => $logs,
        ], 200);
    }
}
