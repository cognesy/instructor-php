<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Contracts;

use Cognesy\Utils\Result\Result;

interface ToolInterface
{
    public function name(): string;
    public function description(): string;
    public function use(mixed ...$args) : Result;
    public function toToolSchema(): array;

    /**
     * Level 1: Metadata - minimal information for browsing/discovery
     * Returns: name, summary, tags (optional), namespace (optional)
     * Target: ~10-30 tokens
     */
    public function metadata(): array;

    /**
     * Level 2: Full specification - complete tool documentation
     * Returns: name, description, parameters, usage, examples, errors, notes
     * Target: ~50-200 tokens depending on complexity
     */
    public function fullSpec(): array;
}