<?php
namespace Andmarruda\Lpb\Http\Controllers;

use Andmarruda\Lpb\Lpb;
use Symfony\Component\HttpFoundation\Response;

/**
 * The optional page route of the view flow (laravel-page-builder.routes.pages).
 */
class PageViewController extends Controller
{
    public function __invoke(Lpb $lpb, string $slug): Response
    {
        return $lpb->view($slug);
    }
}
