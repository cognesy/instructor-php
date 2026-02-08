<?php declare(strict_types=1);

namespace Cognesy\Doctor\Freeze;

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Sandbox;

class Freeze
{
    public static function file(string $filePath, ?CanExecuteCommand $executor = null): FreezeCommand {
        $exec = $executor ?? self::makeSandbox();
        return new FreezeCommand($filePath, $exec);
    }

    public static function execute(string $command, ?CanExecuteCommand $executor = null): FreezeCommand {
        $exec = $executor ?? self::makeSandbox();
        return (new FreezeCommand(null, $exec))->execute($command);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

    private static function makeSandbox() : CanExecuteCommand {
        $dir = sys_get_temp_dir();
        $policy = ExecutionPolicy::in($dir)->inheritEnvironment(true);
        return Sandbox::host($policy);
    }
}
