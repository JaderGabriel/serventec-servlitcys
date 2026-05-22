<?php

namespace App\Http\Controllers;

use App\Services\Rx\RxOverviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RxDashboardController extends Controller
{
    public function __construct(
        private RxOverviewService $overview,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->is_active, 403);

        $rx = $this->overview->build($user);

        return view('dashboard.rx', [
            'rx' => $rx,
        ]);
    }
}
