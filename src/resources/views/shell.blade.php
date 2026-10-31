{{--
    Default shell view (lpb::shell): the HTML around a page payload. The
    frontend framework mounts the page into #lpb-app from the embedded
    payload. Publish it with: php artisan vendor:publish --tag=lpb-views
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-lpb::head :page="$lpb" />
    @if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
        @vite(config('laravel-page-builder.shell.assets', ['resources/js/app.js']))
    @endif
</head>
<body>
    @if (isset($page['component']))
        {{-- Used as the Inertia root view --}}
        @inertia
    @else
        <div id="lpb-app"></div>
        @lpbPayload
    @endif
</body>
</html>
