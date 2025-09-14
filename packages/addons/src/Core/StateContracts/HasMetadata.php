<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\StateContracts;

use Cognesy\Utils\Metadata;

interface HasMetadata
{
    public function metadata(): Metadata;
    public function withMetadata(string $name, mixed $value): static;
}