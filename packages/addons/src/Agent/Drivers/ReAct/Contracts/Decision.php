<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Drivers\ReAct\Contracts;

interface Decision
{
    public function thought(): string;
    public function isCall(): bool;
    public function tool(): ?string;
    /**
     * @return array<string,mixed>
     */
    public function args(): array;
}

