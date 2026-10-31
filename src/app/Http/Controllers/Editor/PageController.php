<?php
namespace Andmarruda\Lpb\Http\Controllers\Editor;

use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Contracts\PublishingServiceInterface;
use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\Http\Controllers\Controller;
use Andmarruda\Lpb\ValueObjects\SEO;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Editor API for pages: fields, the working tree, publishing and revisions.
 */
class PageController extends Controller
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PageTreeRepositoryInterface $trees,
        private PublishingServiceInterface $publishing,
    ) {
    }

    public function index(): JsonResponse
    {
        $this->authorizeEdit();

        return new JsonResponse(['data' => array_map(fn (array $page) => $this->summary($page), $this->pages->all())]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeEdit();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
        ]);

        $page = $this->pages->create($data);
        return new JsonResponse($this->detail((string) $page['id']), 201);
    }

    public function show(string $page): JsonResponse
    {
        $this->authorizeEdit();
        return $this->pages->find($page) ? new JsonResponse($this->detail($page)) : $this->notFound('Page not found.');
    }

    /**
     * Title, slug, SEO, metatags (set by name) and template.
     */
    public function update(Request $request, TemplateRepositoryInterface $templates, string $page): JsonResponse
    {
        $this->authorizeEdit();
        if (!$this->pages->find($page)) {
            return $this->notFound('Page not found.');
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'seo' => ['sometimes', 'nullable', 'array'],
            'metatags' => ['sometimes', 'array'],
            'metatags.*.name' => ['required', 'string', 'max:255'],
            'metatags.*.content' => ['required', 'string'],
            'template_id' => ['sometimes', 'nullable', 'string'],
        ]);

        $fields = array_intersect_key($data, array_flip(['title', 'slug']));
        if ($fields !== []) {
            $this->pages->update($page, $fields);
        }
        if (array_key_exists('seo', $data)) {
            $this->pages->setSeo($page, $data['seo'] ? SEO::fromArray($data['seo']) : new SEO());
        }
        foreach ($data['metatags'] ?? [] as $tag) {
            $this->pages->setMetatag($page, $tag['name'], $tag['content']);
        }
        if (array_key_exists('template_id', $data)) {
            $attached = $data['template_id'] === null
                ? $templates->detachFromPage($page)
                : $templates->attachToPage($data['template_id'], $page);
            if (!$attached) {
                throw ValidationException::withMessages(['template_id' => 'The template does not exist or is inactive.']);
            }
        }

        return new JsonResponse($this->detail($page));
    }

    public function destroy(string $page): JsonResponse
    {
        $this->authorizeEdit();
        return $this->pages->delete($page) ? new JsonResponse(null, 204) : $this->notFound('Page not found.');
    }

    /**
     * Replace the working tree; 409 with current_version on a conflict.
     */
    public function saveTree(Request $request, string $page): JsonResponse
    {
        $this->authorizeEdit();
        $data = $request->validate([
            'tree' => ['required', 'array'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);

        return new JsonResponse($this->trees->saveTree($page, $data['tree'], (int) $data['expected_version']));
    }

    public function publish(Request $request, string $page): JsonResponse
    {
        $this->authorizePublish();
        $revision = $this->publishing->publish($page, $this->actor($request));
        return new JsonResponse($this->detail($page) + ['revision_id' => (string) $revision['id']]);
    }

    public function unpublish(Request $request, string $page): JsonResponse
    {
        $this->authorizePublish();
        $this->publishing->unpublish($page, $this->actor($request));
        return new JsonResponse($this->detail($page));
    }

    public function archive(Request $request, string $page): JsonResponse
    {
        $this->authorizePublish();
        $this->publishing->archive($page, $this->actor($request));
        return new JsonResponse($this->detail($page));
    }

    public function unarchive(string $page): JsonResponse
    {
        $this->authorizePublish();
        $this->publishing->unarchive($page);
        return new JsonResponse($this->detail($page));
    }

    public function revisions(string $page): JsonResponse
    {
        $this->authorizeEdit();
        return new JsonResponse(['data' => $this->publishing->revisions($page)]);
    }

    public function rollback(Request $request, string $page, string $revision): JsonResponse
    {
        $this->authorizePublish();
        $this->publishing->rollback($page, $revision, $this->actor($request));
        return new JsonResponse($this->detail($page));
    }

    public function restore(Request $request, string $page, string $revision): JsonResponse
    {
        $this->authorizeEdit();
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:0']]);

        return new JsonResponse($this->publishing->restoreToDraft($page, $revision, (int) $data['expected_version']));
    }

    /**
     * A signed, expiring URL of the preview payload.
     */
    public function previewToken(Encrypter $encrypter, string $page): JsonResponse
    {
        $this->authorizeEdit();
        if (!$this->pages->find($page)) {
            return $this->notFound('Page not found.');
        }

        $expires = Carbon::now()->addSeconds((int) config('laravel-page-builder.preview.ttl', 3600));
        $url = URL::temporarySignedRoute('lpb.preview', $expires, ['token' => $encrypter->encryptString($page)]);

        return new JsonResponse(['url' => $url, 'expires_at' => $expires->toIso8601String()]);
    }

    private function summary(array $page): array
    {
        return [
            'id' => (string) $page['id'],
            'title' => $page['title'],
            'slug' => $page['slug'],
            'status' => $page['status'],
            'template_id' => $page['template_id'] ?? null,
            'tree_version' => (int) ($page['tree_version'] ?? 0),
            'published_revision_id' => $page['published_revision_id'] ?? null,
            'has_unpublished_changes' => $this->publishing->hasUnpublishedChanges((string) $page['id']),
            'updated_at' => $page['updated_at'] ?? null,
        ];
    }

    /**
     * Page fields, the working tree and its tree_version.
     */
    private function detail(string $pageId): array
    {
        $tree = $this->trees->getWorkingTree($pageId);
        return $this->summary($this->pages->find($pageId)) + ['tree' => $tree];
    }
}
