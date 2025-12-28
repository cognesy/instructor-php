<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenAICodex\Application\Dto;

use Cognesy\Auxiliary\Agents\Common\Collection\DecodedObjectCollection;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Value\UsageStats;
use Cognesy\Utils\Sandbox\Data\ExecResult;

/**
 * Response from Codex CLI execution
 */
final readonly class CodexResponse
{
    public function __construct(
        private ExecResult $result,
        private DecodedObjectCollection $decoded,
        private ?string $threadId = null,
        private ?UsageStats $usage = null,
    ) {}

    public function result(): ExecResult {
        return $this->result;
    }

    public function decoded(): DecodedObjectCollection {
        return $this->decoded;
    }

    public function threadId(): ?string {
        return $this->threadId;
    }

    public function usage(): ?UsageStats {
        return $this->usage;
    }

    public function exitCode(): int {
        return $this->result->exitCode();
    }

    public function isSuccess(): bool {
        return $this->result->exitCode() === 0;
    }

    public function stdout(): string {
        return $this->result->stdout();
    }

    public function stderr(): string {
        return $this->result->stderr();
    }
}
