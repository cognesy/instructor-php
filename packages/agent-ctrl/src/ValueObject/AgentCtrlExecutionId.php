<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ValueObject;

use Cognesy\Utils\Identifier\OpaqueExternalId;
use Cognesy\Utils\Uuid;

final readonly class AgentCtrlExecutionId extends OpaqueExternalId
{
    public static function fresh(): self
    {
        return new self(Uuid::uuid4());
    }
}
