<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DocumentationFileReader;
use App\Services\Admin\DocumentationMarkdownRenderer;
use App\Support\Admin\DocumentationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DocumentationController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.documentation.show', [
            'doc' => DocumentationCatalog::defaultPath(),
        ]);
    }

    public function show(
        Request $request,
        DocumentationFileReader $reader,
        DocumentationMarkdownRenderer $renderer,
    ): View {
        $path = (string) $request->query('doc', DocumentationCatalog::defaultPath());
        if (! DocumentationCatalog::isAllowedPath($path)) {
            abort(404);
        }

        try {
            $file = $reader->read($path);
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        $item = DocumentationCatalog::findItemByPath($path);

        $product = config('documentation.product', []);

        return view('admin.documentation.show', [
            'sections' => DocumentationCatalog::sections(),
            'currentPath' => $path,
            'currentLabel' => $item['label'] ?? $file['label'],
            'currentSection' => $item['section_title'] ?? null,
            'htmlContent' => $renderer->toHtml($file['markdown'], $path),
            'modifiedAt' => $file['modified_at'],
            'githubBlobUrl' => DocumentationCatalog::githubBlobUrl($path),
            'defaultDoc' => DocumentationCatalog::defaultPath(),
            'productVersion' => (string) ($product['version'] ?? ''),
            'productCommit' => (string) ($product['commit_short'] ?? ''),
            'productCommitNumber' => (int) ($product['commit_number'] ?? 0),
            'productRevisionDate' => (string) ($product['revision_date'] ?? ''),
        ]);
    }
}
