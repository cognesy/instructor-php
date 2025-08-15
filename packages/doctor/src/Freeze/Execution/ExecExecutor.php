<?php declare(strict_types=1);

namespace Cognesy\Doctor\Freeze\Execution;

class ExecExecutor implements CommandExecutorInterface
{
    public function execute(array $commandArray, string $commandString): ExecutionResult
    {
        $output = [];
        $exitCode = 0;

        // Use exec() for yet another approach
        exec($commandString . ' 2>&1', $output, $exitCode);
        
        $outputString = implode("\n", $output);
        $success = $exitCode === 0;

        return new ExecutionResult(
            success: $success,
            output: $success ? $outputString : '',
            errorOutput: $success ? '' : $outputString,
            command: $commandString,
            exitCode: $exitCode,
        );
    }
}