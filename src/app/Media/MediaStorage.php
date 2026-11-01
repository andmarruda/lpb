<?php
namespace Andmarruda\Lpb\Media;

use Andmarruda\Lpb\Contracts\MediaContentsRepositoryInterface;
use Andmarruda\Lpb\Contracts\MediaRepositoryInterface;
use Andmarruda\Lpb\Events\MediaChanged;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Exceptions\RecordNotFoundException;
use Andmarruda\Lpb\Support\Ulid;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use finfo;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

/**
 * Stores the files of media records (images, videos, audio).
 *
 * A file goes to a filesystem disk of the application (local, public, s3,
 * an S3-compatible bucket such as Cloudflare R2, ...) or, with the special
 * disk name "database", into the database itself (lpb_media_files) and is
 * served by the package's media route.
 *
 * Replacing a file keeps the media id, so pages that reference it show the
 * new file; the stored path changes, so URLs (and caches) change too.
 */
class MediaStorage
{
    public const DATABASE = 'database';

    public function __construct(
        private MediaRepositoryInterface $media,
        private MediaContentsRepositoryInterface $contents,
        private Filesystems $files,
        private UrlGenerator $urls,
        private Dispatcher $events,
    ) {
    }

    /**
     * Store a file and create its media record.
     *
     * @param UploadedFile|File $file
     * @param array $attributes Optional collection and meta (alt, title, ...)
     * @param string|null $disk A disk of media.disks; defaults to media.disk
     * @return array The media record
     * @throws InvalidValueException On a disallowed disk, type or size
     */
    public function upload(UploadedFile|File $file, array $attributes = [], ?string $disk = null): array
    {
        $disk = $this->disk($disk);
        $mime = $this->assertAcceptable($file);
        $path = $this->newPath($file, $disk);

        $record = [
            'disk' => $disk,
            'path' => $path,
            'filename' => $this->clientName($file),
            'mime_type' => $mime,
            'size' => (int) $file->getSize(),
            'meta' => array_merge($this->dimensions($file, $mime), array_intersect_key((array) ($attributes['meta'] ?? []), array_flip(['alt', 'title', 'caption', 'focal_point']))),
            'collection' => $attributes['collection'] ?? null,
        ];

        if ($disk === self::DATABASE) {
            $media = $this->media->create($record);
            try {
                $this->contents->put($media['id'], $path, (string) file_get_contents($file->getRealPath()));
            } catch (Throwable $e) {
                $this->media->delete($media['id']);
                throw $e;
            }
            return $media;
        }

        $this->writeToDisk($disk, $path, $file);
        try {
            return $this->media->create($record);
        } catch (Throwable $e) {
            $this->files->disk($disk)->delete($path);
            throw $e;
        }
    }

    /**
     * Replace the file of a media record, keeping its id and meta (alt, ...).
     *
     * @param string|int $id
     * @param UploadedFile|File $file
     * @return array The updated media record
     */
    public function replace(string|int $id, UploadedFile|File $file): array
    {
        $media = $this->media->find($id) ?? throw new RecordNotFoundException("Media '{$id}' not found.");
        $mime = $this->assertAcceptable($file);
        $disk = $media['disk'];
        $path = $this->newPath($file, $disk);

        // The stored contents carry their path: if the record update below
        // fails, the old URL stops matching instead of serving the new bytes
        if ($disk === self::DATABASE) {
            $this->contents->put($media['id'], $path, (string) file_get_contents($file->getRealPath()));
        } else {
            $this->writeToDisk($disk, $path, $file);
        }

        $meta = array_merge(array_diff_key((array) ($media['meta'] ?? []), ['width' => true, 'height' => true]), $this->dimensions($file, $mime));
        $updated = $this->media->update($id, [
            'path' => $path,
            'filename' => $this->clientName($file),
            'mime_type' => $mime,
            'size' => (int) $file->getSize(),
            'meta' => $meta,
        ]);

        if ($disk !== self::DATABASE) {
            $this->files->disk($disk)->delete($media['path']);
        }
        $this->events->dispatch(new MediaChanged((string) $media['id']));
        return $updated;
    }

    /**
     * Change the details editors may change (meta such as alt, and the
     * collection); delivered payloads pick them up.
     *
     * @param string|int $id
     * @param array $data meta and/or collection; other keys are ignored
     * @return array|null The updated media record, or null if not found
     */
    public function updateDetails(string|int $id, array $data): ?array
    {
        $media = $this->media->find($id);
        if (!$media) {
            return null;
        }

        $changes = [];
        if (array_key_exists('meta', $data)) {
            if (!is_array($data['meta'])) {
                throw new InvalidValueException('Media meta must be an object.');
            }
            // Dimensions describe the stored file and stay as measured
            $changes['meta'] = array_merge((array) ($media['meta'] ?? []), array_diff_key($data['meta'], ['width' => true, 'height' => true]));
        }
        if (array_key_exists('collection', $data)) {
            $changes['collection'] = $data['collection'];
        }
        if ($changes === []) {
            return $media;
        }

        $updated = $this->media->update($id, $changes);
        $this->events->dispatch(new MediaChanged((string) $media['id']));
        return $updated;
    }

    /**
     * Whether a disk and path may be recorded for a file that is already
     * stored: an allowed disk and a relative path without traversal.
     */
    public function isRecordablePath(mixed $disk, mixed $path): bool
    {
        return is_string($disk) && $disk !== self::DATABASE && in_array($disk, $this->disks(), true)
            && is_string($path) && $path !== '' && !str_starts_with($path, '/')
            && !preg_match('#(^|[/\\\\])\.\.?([/\\\\]|$)|\\\\|\x00#', $path);
    }

    /**
     * Delete a media record and its file.
     */
    public function delete(string|int $id): bool
    {
        $media = $this->media->find($id);
        if (!$media) {
            return false;
        }

        if ($media['disk'] === self::DATABASE) {
            $this->contents->delete($media['id']);
        } else {
            try {
                $this->files->disk($media['disk'])->delete($media['path']);
            } catch (Throwable) {
                // A record of a file on a disk that no longer exists can still be removed
            }
        }

        $deleted = $this->media->delete($id);
        if ($deleted) {
            $this->events->dispatch(new MediaChanged((string) $media['id']));
        }
        return $deleted;
    }

    /**
     * Public URL of a media record's file.
     */
    public function url(array $media): ?string
    {
        if (($media['disk'] ?? null) === self::DATABASE) {
            return $this->urls->route('lpb.media.file', ['media' => $media['id'], 'file' => basename((string) $media['path'])]);
        }

        try {
            return $this->files->disk($media['disk'])->url($media['path']);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Contents of a database-stored file, when $file is its current name.
     *
     * @return array{contents: string, mime_type: string, filename: string}|null
     */
    public function read(string|int $id, string $file): ?array
    {
        try {
            $media = $this->media->find($id);
        } catch (Throwable) {
            // An id the database cannot even compare (e.g. text for a bigint)
            return null;
        }
        if (!$media || $media['disk'] !== self::DATABASE || basename((string) $media['path']) !== $file) {
            return null;
        }

        $contents = $this->contents->get($media['id'], (string) $media['path']);
        return $contents === null ? null : ['contents' => $contents, 'mime_type' => $media['mime_type'], 'filename' => $media['filename']];
    }

    /**
     * The disks editors may upload to.
     *
     * @return string[]
     */
    public function disks(): array
    {
        $default = (string) config('laravel-page-builder.media.disk', 'public');
        return array_values(array_unique([$default, ...(array) config('laravel-page-builder.media.disks', [])]));
    }

    private function disk(?string $disk): string
    {
        $disk ??= (string) config('laravel-page-builder.media.disk', 'public');
        if (!in_array($disk, $this->disks(), true)) {
            throw new InvalidValueException("Media disk '{$disk}' is not allowed; use one of: " . implode(', ', $this->disks()) . '.');
        }
        return $disk;
    }

    /**
     * @return string The detected MIME type
     */
    private function assertAcceptable(UploadedFile|File $file): string
    {
        // Detected from the contents, never from the client's name or type
        $mime = strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()));
        $allowed = array_map('strtolower', (array) config('laravel-page-builder.media.mimes', []));
        if (!in_array($mime, $allowed, true)) {
            throw new InvalidValueException("Files of type '{$mime}' cannot be uploaded.");
        }

        $max = (int) config('laravel-page-builder.media.max_size', 10240) * 1024;
        if ($file->getSize() > $max) {
            throw new InvalidValueException('The file is larger than ' . intdiv($max, 1024) . ' KB.');
        }
        return $mime;
    }

    private function newPath(UploadedFile|File $file, string $disk): string
    {
        $mime = strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()));
        $extension = MimeTypes::getDefault()->getExtensions($mime)[0] ?? 'bin';
        $directory = trim((string) config('laravel-page-builder.media.directory', 'lpb'), '/');
        $name = strtolower(Ulid::generate()) . ".{$extension}";
        return $disk === self::DATABASE ? $name : ($directory === '' ? '' : "{$directory}/") . date('Y/m') . "/{$name}";
    }

    private function writeToDisk(string $disk, string $path, UploadedFile|File $file): void
    {
        $stream = fopen($file->getRealPath(), 'r');
        try {
            if (!$this->files->disk($disk)->put($path, $stream, ['visibility' => 'public'])) {
                throw new InvalidValueException("The file could not be written to the '{$disk}' disk.");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function dimensions(UploadedFile|File $file, string $mime): array
    {
        if (!str_starts_with($mime, 'image/')) {
            return [];
        }
        $size = @getimagesize($file->getRealPath());
        return $size ? ['width' => $size[0], 'height' => $size[1]] : [];
    }

    private function clientName(UploadedFile|File $file): string
    {
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();
        // A display name only: never used to build a path
        return mb_substr(preg_replace('/[^\pL\pN._ -]+/u', '_', $name) ?: 'file', 0, 200);
    }
}
