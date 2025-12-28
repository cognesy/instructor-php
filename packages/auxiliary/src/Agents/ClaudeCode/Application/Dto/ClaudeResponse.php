<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\ClaudeCode\Application\Dto;

use Cognesy\Auxiliary\Agents\Common\Collection\DecodedObjectCollection;
use Cognesy\Utils\Sandbox\Data\ExecResult;

final readonly class ClaudeResponse
{
    public function __construct(
        private ExecResult $result,
        private DecodedObjectCollection $decoded,
    ) {}

    public function result() : ExecResult {
        return $this->result;
    }

    public function decoded() : DecodedObjectCollection {
        return $this->decoded;
    }
}
