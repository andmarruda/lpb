<?php
namespace Andmarruda\Lpb;

use Andmarruda\Lpb\Delivery\PageDeliveryService;
use Andmarruda\Lpb\Http\Shell;
use Andmarruda\Lpb\Widgets\WidgetRegistry;
use Illuminate\Contracts\Routing\ResponseFactory;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Entry point of the package, also available as the Lpb facade.
 */
class Lpb
{
    public function __construct(
        private WidgetRegistry $widgets,
        private PageDeliveryService $delivery,
        private Shell $shell,
        private ResponseFactory $responses,
    ) {
    }

    /**
     * The widget registry: Lpb::widgets()->register(Hero::class).
     *
     * @return WidgetRegistry
     */
    public function widgets(): WidgetRegistry
    {
        return $this->widgets;
    }

    /**
     * View flow (ADR 12): render the template's shell view with the
     * published payload as $lpb; the frontend mounts the page from the
     * embedded payload.
     *
     * @param string $slug
     * @return Response
     * @throws NotFoundHttpException When the page is not published
     */
    public function view(string $slug): Response
    {
        $payload = $this->published($slug);
        return $this->responses->view($this->shell->viewFor($payload), ['lpb' => $payload]);
    }

    /**
     * Inertia flow (ADR 12): the template's shell view becomes the root view
     * and the payload is the page component's "page" prop. Requires
     * inertiajs/inertia-laravel.
     *
     * @param string $slug
     * @return Response
     * @throws NotFoundHttpException When the page is not published
     */
    public function inertia(string $slug): Response
    {
        if (!class_exists(\Inertia\Inertia::class)) {
            throw new RuntimeException('Lpb::inertia() requires inertiajs/inertia-laravel.');
        }

        $payload = $this->published($slug);
        \Inertia\Inertia::setRootView($this->shell->viewFor($payload));

        return \Inertia\Inertia::render(config('laravel-page-builder.inertia.component', 'Lpb/Page'), ['page' => $payload])
            ->withViewData(['lpb' => $payload])
            ->toResponse(request());
    }

    private function published(string $slug): array
    {
        return $this->delivery->published($slug) ?? throw new NotFoundHttpException("Page '{$slug}' is not published.");
    }
}
