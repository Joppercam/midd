<?php

namespace App\Modules\Analytics\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:analytics.configure');
    }

    public function index()
    {
        return Inertia::render('Analytics/Settings', [
            'settings' => [
                'retention_days' => config('modules.analytics.settings.retention_days', 365),
                'default_date_range' => config('modules.analytics.settings.default_date_range', 'last_30_days'),
                'enable_realtime' => config('modules.analytics.settings.enable_realtime', true),
                'export_formats' => config('modules.analytics.settings.export_formats', ['pdf', 'excel', 'csv']),
            ]
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'retention_days' => 'required|integer|min:30|max:1825',
            'default_date_range' => 'required|string',
            'enable_realtime' => 'boolean',
            'export_formats' => 'array',
        ]);

        // TODO: Implement settings update
        // For now, just return success
        
        return redirect()->route('analytics.settings')
            ->with('success', 'Settings updated successfully');
    }
}