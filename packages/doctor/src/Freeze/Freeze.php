<?php

declare(strict_types=1);

namespace Cognesy\Doctor\Freeze;

use Cognesy\Doctor\Freeze\Execution\CommandExecutorInterface;
use Cognesy\Doctor\Freeze\Execution\ExecExecutor;
use Cognesy\Doctor\Freeze\Execution\ShellExecutor;

class Freeze
{
    public static function file(string $filePath, ?CommandExecutorInterface $executor = null): FreezeCommand {
        return new FreezeCommand($filePath, $executor);
    }

    public static function execute(string $command, ?CommandExecutorInterface $executor = null): FreezeCommand {
        return (new FreezeCommand(null, $executor))->execute($command);
    }
    
    public static function withShellExecutor(): CommandExecutorInterface {
        return new ShellExecutor();
    }
    
    public static function withExecExecutor(): CommandExecutorInterface {
        return new ExecExecutor();
    }
}