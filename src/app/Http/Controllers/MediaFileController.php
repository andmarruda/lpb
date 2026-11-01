<?php
namespace Andmarruda\Lpb\Http\Controllers;

use Andmarruda\Lpb\Media\MediaStorage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves media stored on the "database" disk. The URL names the current
 * stored file, so it changes on replace and can be cached forever.
 */
class MediaFileController extends Controller
{
    public function __invoke(MediaStorage $storage, string $media, string $file): Response
    {
        $found = $storage->read($media, $file);
        if ($found === null) {
            return $this->notFound('Media not found.');
        }

        return new Response($found['contents'], 200, [
            'Content-Type' => $found['mime_type'],
            'Content-Length' => (string) strlen($found['contents']),
            'Content-Disposition' => 'inline; filename="' . addcslashes($found['filename'], '"\\') . '"',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            // Even an allowed type cannot run scripts from this origin
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
