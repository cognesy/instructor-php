<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Envelope;

/**
 * Represents a single step in a workflow execution.
 * 
 * Each step knows how to execute itself given an envelope and returns
 * a new envelope with the result of the execution.
 */
interface WorkflowStepInterface
{
    /**
     * Execute this workflow step.
     * 
     * @param Envelope $envelope Current execution context
     * @return Envelope Result of step execution
     */
    public function execute(Envelope $envelope): Envelope;
}