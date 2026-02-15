<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Traits;

use Cognesy\Agents\Tool\Contracts\CanDescribeTool;

trait HasDescriptor
{
    private CanDescribeTool $descriptor;

    final protected function initializeDescriptor(CanDescribeTool $descriptor): void {
        $this->descriptor = $descriptor;
    }

    #[\Override]
    public function descriptor(): CanDescribeTool {
        return $this->descriptor;
    }

    #[\Override]
    public function name(): string {
        return $this->descriptor->name();
    }

    #[\Override]
    public function description(): string {
        return $this->descriptor->description();
    }

    #[\Override]
    public function metadata(): array {
        return $this->descriptor->metadata();
    }

    #[\Override]
    public function instructions(): array {
        return $this->descriptor->instructions();
    }
}
