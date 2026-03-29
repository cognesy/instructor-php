<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\AgentCtrl;

enum AgentCtrlExecutionContext: string
{
    case Cli = 'cli';
    case Http = 'http';
    case Messenger = 'messenger';

    public function configKey(): string
    {
        return match ($this) {
            self::Cli => 'allow_cli',
            self::Http => 'allow_http',
            self::Messenger => 'allow_messenger',
        };
    }
}
