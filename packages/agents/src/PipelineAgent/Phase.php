<?php declare(strict_types=1);

namespace Cognesy\Agents\PipelineAgent;

/**
 * Defines the phases of agent execution lifecycle.
 */
enum Phase: string
{
    case BeforeExecution = 'before_execution';
    case BeforeStep = 'before_step';
    case ExecuteStep = 'execute_step';
    case AfterStep = 'after_step';
    case AfterExecution = 'after_execution';
    case OnError = 'on_error';
}
