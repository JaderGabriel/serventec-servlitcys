<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Admin\DocumentationFileReader;
use App\Services\Admin\DocumentationMarkdownRenderer;
use App\Support\Admin\DocumentationCatalog;
use App\Support\Admin\DocumentationSearchIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DocumentationController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route($this->documentationRoutePrefix().'.show', [
            'doc' => DocumentationCatalog::defaultPath(),
        ]);
    }

    public function search(Request $request, DocumentationSearchIndex $searchIndex): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || ! $user->canViewDocumentation(), 403);

        $query = trim((string) $request->query('q', ''));
        $limit = min(30, max(1, (int) $request->query('limit', 20)));

        $results = $searchIndex->search(
            $user,
            $query,
            $limit,
            $this->documentationRoutePrefix(),
        );

        return response()->json([
            'query' => $query,
            'results' => $results,
        ]);
    }

    public function show(
        Request $request,
        DocumentationFileReader $reader,
        DocumentationMarkdownRenderer $renderer,
    ): View {
        $user = $request->user();
        abort_if($user === null || ! $user->canViewDocumentation(), 403);

        $path = (string) $request->query('doc', DocumentationCatalog::defaultPath());
        $resolved = DocumentationCatalog::resolveReadablePath($path);
        if ($resolved === null || ! DocumentationCatalog::canUserReadPath($user, $resolved)) {
            abort(404);
        }

        try {
            $file = $reader->read($resolved);
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        $item = DocumentationCatalog::findItemByPath($resolved);
        $routePrefix = $this->documentationRoutePrefix();
        $product = config('documentation.product', []);

        $markdown = $file['markdown'];
        $rendered = $renderer->render($markdown, $resolved, $routePrefix);

        return view('documentation.show', [
            'sections' => DocumentationCatalog::sectionsForUser($user),
            'currentPath' => $resolved,
            'currentLabel' => $item['label'] ?? $file['label'],
            'currentSection' => $item['section_title'] ?? null,
            'currentSectionKey' => $item['section_key'] ?? null,
            'currentSectionIcon' => $item['section_icon'] ?? 'document-text',
            'currentSectionTone' => $item['section_tone'] ?? 'slate',
            'htmlContent' => $rendered['html'],
            'documentHeadings' => $rendered['headings'],
            'loadMermaid' => $renderer->markdownUsesMermaid($markdown),
            'modifiedAt' => $file['modified_at'],
            'githubBlobUrl' => DocumentationCatalog::githubBlobUrl($resolved),
            'defaultDoc' => DocumentationCatalog::defaultPath(),
            'documentationRoutePrefix' => $routePrefix,
            'productVersion' => (string) ($product['version'] ?? ''),
            'productReleaseTag' => (string) ($product['release_tag'] ?? ''),
            'productCommit' => (string) ($product['commit_short'] ?? ''),
            'productCommitNumber' => (int) ($product['commit_number'] ?? 0),
            'productRevisionDate' => (string) ($product['revision_date'] ?? ''),
            'productInProduction' => (bool) ($product['in_production'] ?? false),
            'productProductionLabel' => (string) ($product['production_label'] ?? __('Em produção')),
            'isAdminReader' => $user->isAdmin(),
        ]);
    }

    protected function documentationRoutePrefix(): string
    {
        return request()->routeIs('admin.documentation.*')
            ? 'admin.documentation'
            : 'documentation';
    }
}
