<?php

declare(strict_types=1);

namespace Cognesy\Tell\Operational;

final readonly class PlaneOperation
{
    public function __construct(
        public OperationalPlane $plane,
        public string $command,
        public string $responsibility,
        public string $ownedState,
        public string $input,
        public string $output,
        public string $authority,
        public string $degradedBehavior,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array {
        return [
            'plane' => $this->plane->value,
            'command' => $this->command,
            'responsibility' => $this->responsibility,
            'ownedState' => $this->ownedState,
            'input' => $this->input,
            'output' => $this->output,
            'authority' => $this->authority,
            'degradedBehavior' => $this->degradedBehavior,
        ];
    }
}
