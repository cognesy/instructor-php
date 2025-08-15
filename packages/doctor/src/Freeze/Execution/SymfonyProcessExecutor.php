<?php declare(strict_types=1);

namespace Cognesy\Doctor\Freeze\Execution;

use Symfony\Component\Process\Process;

class SymfonyProcessExecutor implements CommandExecutorInterface
{
    public function execute(array $commandArray, string $commandString): ExecutionResult
    {
        $process = new Process($commandArray);
        $process->setWorkingDirectory(getcwd());
        $process->run();

        return new ExecutionResult(
            success: $process->isSuccessful(),
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
            command: $commandString,
            exitCode: $process->getExitCode(),
        );
    }
}