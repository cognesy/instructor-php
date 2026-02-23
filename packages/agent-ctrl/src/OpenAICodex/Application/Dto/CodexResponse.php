<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Application\Dto;

use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Value\UsageStats;
use Cognesy\AgentCtrl\OpenAICodex\Domain\ValueObject\CodexThreadId;
use Cognesy\Sandbox\Data\ExecResult;

/**
 * Response from Codex CLI execution
 */
final readonly class CodexResponse
{
    private ?CodexThreadId $threadId;
    /** @var list<string> */
    private array $parseFailureSamples;

    public function __construct(
        private ExecResult $result,
        private DecodedObjectCollection $decoded,
        CodexThreadId|string|null $threadId = null,
        private ?UsageStats $usage = null,
        private string $messageText = '',
        private int $parseFailures = 0,
        array $parseFailureSamples = [],
    ) {
        $this->threadId = match (true) {
            $threadId instanceof CodexThreadId => $threadId,
            is_string($threadId) && $threadId !== '' => CodexThreadId::fromString($threadId),
            default => null,
        };
        $this->parseFailureSamples = array_values($parseFailureSamples);
    }

    public function result(): ExecResult {
        return $this->result;
    }

    public function decoded(): DecodedObjectCollection {
        return $this->decoded;
    }

    public function threadId(): ?CodexThreadId {
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

    public function messageText(): string {
        return $this->messageText;
    }

    public function parseFailures(): int {
        return $this->parseFailures;
    }

    /**
     * @return list<string>
     */
    public function parseFailureSamples(): array {
        return $this->parseFailureSamples;
    }
}
