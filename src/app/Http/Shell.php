<?php
namespace Andmarruda\Lpb\Http;

use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Illuminate\Contracts\View\Factory as Views;

/**
 * Blade shell helpers (ADR 12): the view that wraps a payload and the
 * embedded payload script read by the frontend packages.
 */
class Shell
{
    public const DEFAULT_VIEW = 'lpb::shell';

    public function __construct(private TemplateRepositoryInterface $templates, private Views $views)
    {
    }

    /**
     * The template's view_path, or the default shell when the page has no
     * template or the view does not exist.
     *
     * @param array $payload
     * @return string
     */
    public function viewFor(array $payload): string
    {
        $templateId = $payload['template']['id'] ?? null;
        $view = $templateId ? ($this->templates->find($templateId)['view_path'] ?? null) : null;

        return is_string($view) && $view !== '' && $this->views->exists($view) ? $view : self::DEFAULT_VIEW;
    }

    /**
     * <script type="application/json" id="lpb-page"> with the payload, safe
     * to embed in HTML.
     *
     * @param array $payload
     * @return string
     */
    public static function payloadScript(array $payload): string
    {
        $json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return '<script type="application/json" id="lpb-page">' . $json . '</script>';
    }
}
