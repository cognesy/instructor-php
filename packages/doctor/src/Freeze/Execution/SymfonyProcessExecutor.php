<?php declare(strict_types=1);

namespace Cognesy\Doctor\Freeze\Execution;

use Symfony\Component\Process\Process;

class SymfonyProcessExecutor implements CommandExecutorInterface
{
    #[\Override]
    public function execute(array $commandArray, string $commandString): ExecutionResult
    {
        $process = new Process($commandArray);
        $cwd = getcwd();
        if ($cwd !== false) {
            $process->setWorkingDirectory($cwd);
        }
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