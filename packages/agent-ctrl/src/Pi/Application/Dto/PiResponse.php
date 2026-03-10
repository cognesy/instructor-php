<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Application\Dto;

use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\AgentCtrl\Pi\Domain\Value\TokenUsage;
use Cognesy\AgentCtrl\Pi\Domain\ValueObject\PiSessionId;
use Cognesy\Sandbox\Data\ExecResult;

/**
 * Response from Pi CLI execution
 */
final readonly class PiResponse
{
    private ?PiSessionId $sessionId;
    /** @var list<string> */
    private array $parseFailureSamples;

    public function __construct(
        private ExecResult $result,
        private DecodedObjectCollection $decoded,
        PiSessionId|string|null $sessionId = null,
        private string $messageText = '',
        private ?TokenUsage $usage = null,
        private ?float $cost = null,
        private int $parseFailures = 0,
        array $parseFailureSamples = [],
    ) {
        $this->sessionId = match (true) {
            $sessionId instanceof PiSessionId => $sessionId,
            is_string($sessionId) && $sessionId !== '' => PiSessionId::fromString($sessionId),
            default => null,
        };
        $this->parseFailureSamples = array_values($parseFailureSamples);
    }

    public function exitCode(): int
    {
        return $this->result->exitCode();
    }

    public function stdout(): string
    {
        return $this->result->stdout();
    }

    public function stderr(): string
    {
        return $this->result->stderr();
    }

    public function result(): ExecResult
    {
        return $this->result;
    }

    public function decoded(): DecodedObjectCollection
    {
        return $this->decoded;
    }

    public function sessionId(): ?PiSessionId
    {
        return $this->sessionId;
    }

    public function messageText(): string
    {
        return $this->messageText;
    }

    public function usage(): ?TokenUsage
    {
        return $this->usage;
    }

    public function cost(): ?float
    {
        return $this->cost;
    }

    public function isSuccess(): bool
    {
        return $this->result->exitCode() === 0;
    }

    public function parseFailures(): int
    {
        return $this->parseFailures;
    }

    /**
     * @return list<string>
     */
    public function parseFailureSamples(): array
    {
        return $this->parseFailureSamples;
    }
}
