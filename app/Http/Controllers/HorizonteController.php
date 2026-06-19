<?php

namespace App\Http\Controllers;

use App\Services\Horizonte\HorizonteMapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HorizonteController extends Controller
{
    public function __construct(
        private readonly HorizonteMapService $map,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->canViewAdminDashboard(), 403);
        abort_unless((bool) config('horizonte.enabled', true), 404);

        $data = $this->map->build();

        return view('horizonte.index', [
            'horizonte' => $data,
        ]);
    }
}
