<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Selectors\LLMSelector;

final readonly class CollaboratorChoice
{
    public function __construct(
        public string  $collaboratorName,
        public ?string $reason = null,
    ) {}
}