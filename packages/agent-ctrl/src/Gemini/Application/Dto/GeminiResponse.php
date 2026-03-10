<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Application\Dto;

use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\AgentCtrl\Gemini\Domain\Value\TokenUsage;
use Cognesy\AgentCtrl\Gemini\Domain\ValueObject\GeminiSessionId;
use Cognesy\Sandbox\Data\ExecResult;

/**
 * Response from Gemini CLI execution
 */
final readonly class GeminiResponse
{
    private ?GeminiSessionId $sessionId;
    /** @var list<string> */
    private array $parseFailureSamples;

    /**
     * @param list<array{tool:string,input:array,output:?string,isError:bool,toolId:string}> $toolCalls
     */
    public function __construct(
        private ExecResult $result,
        private DecodedObjectCollection $decoded,
        GeminiSessionId|string|null $sessionId = null,
        private string $messageText = '',
        private ?TokenUsage $usage = null,
        private array $toolCalls = [],
        private int $parseFailures = 0,
        array $parseFailureSamples = [],
    ) {
        $this->sessionId = match (true) {
            $sessionId instanceof GeminiSessionId => $sessionId,
            is_string($sessionId) && $sessionId !== '' => GeminiSessionId::fromString($sessionId),
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

    public function sessionId(): ?GeminiSessionId
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

    /**
     * @return list<array{tool:string,input:array,output:?string,isError:bool,toolId:string}>
     */
    public function toolCalls(): array
    {
        return $this->toolCalls;
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
