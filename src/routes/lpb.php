<?php

use Andmarruda\Lpb\Http\Controllers\DeliveryController;
use Andmarruda\Lpb\Http\Controllers\Editor\MediaController;
use Andmarruda\Lpb\Http\Controllers\Editor\MenuController;
use Andmarruda\Lpb\Http\Controllers\Editor\PageController;
use Andmarruda\Lpb\Http\Controllers\Editor\TemplateController;
use Andmarruda\Lpb\Http\Controllers\Editor\WidgetController;
use Andmarruda\Lpb\Http\Controllers\PageViewController;
use Illuminate\Support\Facades\Route;

/*
| JSON APIs of the page builder (docs/openapi.yaml). Loaded by the service
| provider unless laravel-page-builder.routes.enabled is false.
*/

Route::prefix(config('laravel-page-builder.routes.prefix', 'lpb/api'))->name('lpb.')->group(function () {
    // Delivery (public)
    Route::middleware(config('laravel-page-builder.routes.middleware', ['api']))->group(function () {
        Route::get('pages/{slug}', [DeliveryController::class, 'page'])->name('pages.show');
        Route::get('menus/{location}', [DeliveryController::class, 'menu'])->name('menus.show');
        Route::get('preview/{token}', [DeliveryController::class, 'preview'])->middleware('signed')->name('preview');
    });

    // Editor (protected by middleware and the lpb.edit / lpb.publish Gates)
    Route::prefix('editor')->name('editor.')->middleware(config('laravel-page-builder.editor.middleware', ['web', 'auth']))->group(function () {
        Route::get('widgets', [WidgetController::class, 'index'])->name('widgets.index');

        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::post('pages', [PageController::class, 'store'])->name('pages.store');
        Route::get('pages/{page}', [PageController::class, 'show'])->name('pages.show');
        Route::patch('pages/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
        Route::put('pages/{page}/tree', [PageController::class, 'saveTree'])->name('pages.tree');
        Route::post('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
        Route::post('pages/{page}/unpublish', [PageController::class, 'unpublish'])->name('pages.unpublish');
        Route::post('pages/{page}/archive', [PageController::class, 'archive'])->name('pages.archive');
        Route::post('pages/{page}/unarchive', [PageController::class, 'unarchive'])->name('pages.unarchive');
        Route::get('pages/{page}/revisions', [PageController::class, 'revisions'])->name('pages.revisions');
        Route::post('pages/{page}/rollback/{revision}', [PageController::class, 'rollback'])->name('pages.rollback');
        Route::post('pages/{page}/restore/{revision}', [PageController::class, 'restore'])->name('pages.restore');
        Route::post('pages/{page}/preview-token', [PageController::class, 'previewToken'])->name('pages.preview-token');

        Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::get('templates/{template}', [TemplateController::class, 'show'])->name('templates.show');
        Route::patch('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
        Route::post('templates/{template}/default', [TemplateController::class, 'setDefault'])->name('templates.default');

        Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
        Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
        Route::get('menus/{menu}', [MenuController::class, 'show'])->name('menus.show');
        Route::patch('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
        Route::post('menus/{menu}/items', [MenuController::class, 'storeItem'])->name('menus.items.store');
        Route::put('menus/{menu}/items/order', [MenuController::class, 'reorderItems'])->name('menus.items.order');
        Route::patch('menus/{menu}/items/{item}', [MenuController::class, 'updateItem'])->name('menus.items.update');
        Route::delete('menus/{menu}/items/{item}', [MenuController::class, 'destroyItem'])->name('menus.items.destroy');

        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
        Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    });
});

// Optional page route of the view flow, e.g. LPB_PAGES_ROUTE=/{slug}
if ($pages = config('laravel-page-builder.routes.pages')) {
    Route::get($pages, PageViewController::class)
        ->middleware(config('laravel-page-builder.routes.pages_middleware', ['web']))
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('lpb.page');
}
