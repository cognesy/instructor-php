<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Application\Dto;

use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\Sandbox\Data\ExecResult;

final readonly class ClaudeResponse
{
    private ClaudeEventCollection $events;
    /** @var list<string> */
    private array $parseFailureSamples;

    public function __construct(
        private ExecResult $result,
        private DecodedObjectCollection $decoded,
        ?ClaudeEventCollection $events = null,
        private string $messageText = '',
        private int $parseFailures = 0,
        array $parseFailureSamples = [],
    ) {
        $this->events = $events ?? ClaudeEventCollection::empty();
        $this->parseFailureSamples = array_values($parseFailureSamples);
    }

    public function result() : ExecResult {
        return $this->result;
    }

    public function decoded() : DecodedObjectCollection {
        return $this->decoded;
    }

    public function events() : ClaudeEventCollection {
        return $this->events;
    }

    public function messageText() : string {
        return $this->messageText;
    }

    public function parseFailures() : int {
        return $this->parseFailures;
    }

    /**
     * @return list<string>
     */
    public function parseFailureSamples() : array {
        return $this->parseFailureSamples;
    }
}
