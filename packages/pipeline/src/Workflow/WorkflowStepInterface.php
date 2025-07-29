<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Computation;

/**
 * Represents a single step in a workflow execution.
 * 
 * Each step knows how to execute itself given an computation and returns
 * a new computation with the result of the execution.
 */
interface WorkflowStepInterface
{
    /**
     * Execute this workflow step.
     * 
     * @param Computation $computation Current execution context
     * @return Computation Result of step execution
     */
    public function execute(Computation $computation): Computation;
}