<?php
namespace Andmarruda\Lpb\Delivery;

use Andmarruda\Lpb\Contracts\MediaRepositoryInterface;
use Andmarruda\Lpb\Media\MediaStorage;
use Throwable;

/**
 * Resolves references in widget configs for delivery payloads (ADR 10).
 *
 * {"$media": id} becomes {id, url, mime_type, meta}, or null when the media
 * record is gone. {"$var": "token"} is left for renderers, which emit
 * var(--lpb-token).
 */
class ReferenceResolver
{
    /** @var array<string, array|null> */
    private array $media = [];

    public function __construct(private MediaRepositoryInterface $repository, private MediaStorage $storage)
    {
    }

    /**
     * @param array $blocks Nested blocks
     * @return array
     */
    public function resolve(array $blocks): array
    {
        return array_map(function (array $block) {
            $block['widgets'] = array_map(function (array $instance) {
                $instance['config'] = $this->value($instance['config'] ?? []);
                return $instance;
            }, $block['widgets'] ?? []);
            $block['children'] = $this->resolve($block['children'] ?? []);
            return $block;
        }, $blocks);
    }

    private function value(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_keys($value) === ['$media'] && (is_int($value['$media']) || is_string($value['$media']))) {
            return $this->media($value['$media']);
        }
        return array_map(fn ($item) => $this->value($item), $value);
    }

    private function media(int|string $id): ?array
    {
        $key = (string) $id;
        if (array_key_exists($key, $this->media)) {
            return $this->media[$key];
        }

        try {
            $record = $this->repository->find($id);
        } catch (Throwable) {
            // e.g. an id that is not valid for the driver
            $record = null;
        }

        return $this->media[$key] = $record === null ? null : [
            'id' => $record['id'],
            'url' => $this->storage->url($record),
            'mime_type' => $record['mime_type'],
            'meta' => $record['meta'] ?? [],
        ];
    }

}
