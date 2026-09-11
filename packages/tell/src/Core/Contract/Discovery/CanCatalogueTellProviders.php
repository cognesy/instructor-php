<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Discovery;

use Cognesy\Polyglot\Inference\Models\ModelCatalog;

interface CanCatalogueTellProviders
{
    public function catalog(string $project): ModelCatalog;

    /** @return array{connections: list<array<string,mixed>>, errors: list<array<string,string>>} */
    public function connections(string $project): array;

    /** @return list<array<string,mixed>> */
    public function models(string $project, ?string $selector = null): array;

    /** @return array<string,mixed> */
    public function resolve(string $project, string $connection, string $model = ''): array;
}
