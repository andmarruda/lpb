<?php
namespace Andmarruda\Lpb\Http\Controllers\Editor;

use Andmarruda\Lpb\Http\Controllers\Controller;
use Andmarruda\Lpb\Widgets\WidgetRegistry;
use Illuminate\Http\JsonResponse;

/**
 * Registered widget definitions with their JSON Schemas, for the editor.
 */
class WidgetController extends Controller
{
    public function index(WidgetRegistry $widgets): JsonResponse
    {
        $this->authorizeEdit();
        return new JsonResponse(['data' => $widgets->describe()]);
    }
}
