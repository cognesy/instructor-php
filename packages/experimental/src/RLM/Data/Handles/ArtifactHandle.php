<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Data\Handles;

final readonly class ArtifactHandle implements Handle
{
    public function __construct(private string $uri) {}

    public function id(): string {
        return $this->uri;
    }
}

