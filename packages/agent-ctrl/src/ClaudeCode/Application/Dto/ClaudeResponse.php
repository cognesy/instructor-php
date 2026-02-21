<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Application\Dto;

use Cognesy\AgentCtrl\Common\Collection\DecodedObjectCollection;
use Cognesy\Sandbox\Data\ExecResult;

final readonly class ClaudeResponse
{
    private ClaudeEventCollection $events;

    public function __construct(
        private ExecResult $result,
        private DecodedObjectCollection $decoded,
        ?ClaudeEventCollection $events = null,
    ) {
        $this->events = $events ?? ClaudeEventCollection::empty();
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
}
