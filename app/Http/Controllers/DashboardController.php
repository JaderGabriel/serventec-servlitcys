<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\AdminHomeMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, AdminHomeMetrics $metrics): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && ! $user->canViewAdminDashboard()) {
            return redirect()->route('dashboard.analytics');
        }

        $data = $metrics->gather();

        return view('dashboard', [
            'user' => $user,
            'stats' => $data['stats'],
            'ops' => $data['ops'],
            'systemFlow' => $data['system_flow'],
            'mapMarkers' => $data['map_markers'],
        ]);
    }
}
