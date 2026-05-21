<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\DocumentationCatalog;
use Illuminate\View\View;

class DocumentationController extends Controller
{
    public function index(): View
    {
        return view('admin.documentation.index', [
            'sections' => DocumentationCatalog::sections(),
            'docsRoot' => base_path(),
        ]);
    }
}
