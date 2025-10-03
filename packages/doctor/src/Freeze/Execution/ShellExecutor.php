<?php declare(strict_types=1);

namespace Cognesy\Doctor\Freeze\Execution;

class ShellExecutor implements CommandExecutorInterface
{
    #[\Override]
    public function execute(array $commandArray, string $commandString): ExecutionResult
    {
        // Use shell_exec for a different approach
        $output = '';
        $errorOutput = '';
        $exitCode = 0;

        // Redirect stderr to capture error output
        $fullCommand = $commandString . ' 2>&1';
        
        // Execute command and capture output
        $result = shell_exec($fullCommand);
        
        if ($result === null) {
            return new ExecutionResult(
                success: false,
                output: '',
                errorOutput: 'Command execution failed',
                command: $commandString,
                exitCode: -1,
            );
        }

        // Check if the command was successful by looking at the output
        $resultStr = $result !== false ? $result : '';
        $success = !str_contains($resultStr, 'ERROR') && !str_contains($resultStr, 'error:');

        return new ExecutionResult(
            success: $success,
            output: $success ? $result : '',
            errorOutput: $success ? '' : $result,
            command: $commandString,
            exitCode: $success ? 0 : 1,
        );
    }
}