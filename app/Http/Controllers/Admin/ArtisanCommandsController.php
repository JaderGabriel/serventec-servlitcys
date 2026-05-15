<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Console\ArtisanCommandsCatalog;
use Illuminate\View\View;

class ArtisanCommandsController extends Controller
{
    public function index(): View
    {
        return view('admin.artisan-commands.index', [
            'categories' => ArtisanCommandsCatalog::categories(),
            'phpBinary' => PHP_BINARY,
            'projectRoot' => base_path(),
        ]);
    }
}
