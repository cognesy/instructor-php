<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\State\Contracts;

use Cognesy\Utils\Metadata;

interface HasMetadata
{
    public function metadata(): Metadata;
    public function withMetadata(string $name, mixed $value): static;
}