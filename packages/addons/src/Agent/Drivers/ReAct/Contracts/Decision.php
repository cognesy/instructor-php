<?php declare(strict_types=1);

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

