<?php

declare(strict_types=1);

namespace Cognesy\Tell\Protocol;

use InvalidArgumentException;

final class TellAgentProtocolException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $protocolCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
