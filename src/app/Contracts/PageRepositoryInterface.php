<?php
namespace Andmarruda\Lpb\Contracts;

interface PageRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?array;
    public function getPublished(): array;
    public function withWidgets(string|int $id): ?array;
    public function withMetatags(string|int $id): ?array;
    public function getByStatus(string $status): array;
}
