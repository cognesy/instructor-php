<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Progress;

use Cognesy\AgentCtrl\Event\AgentErrorOccurred;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted as AgentCtrlExecutionCompleted;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted as AgentCtrlExecutionStarted;
use Cognesy\AgentCtrl\Event\AgentTextReceived;
use Cognesy\AgentCtrl\Event\CommandSpecCreated;
use Cognesy\AgentCtrl\Event\ExecutionAttempted;
use Cognesy\AgentCtrl\Event\ProcessExecutionCompleted;
use Cognesy\AgentCtrl\Event\ProcessExecutionStarted;
use Cognesy\AgentCtrl\Event\RequestBuilt;
use Cognesy\AgentCtrl\Event\ResponseDataExtracted;
use Cognesy\AgentCtrl\Event\ResponseParsingCompleted;
use Cognesy\AgentCtrl\Event\ResponseParsingStarted;
use Cognesy\AgentCtrl\Event\SandboxInitialized;
use Cognesy\AgentCtrl\Event\SandboxPolicyConfigured;
use Cognesy\AgentCtrl\Event\SandboxReady;
use Cognesy\AgentCtrl\Event\StreamChunkProcessed;
use Cognesy\AgentCtrl\Event\StreamProcessingCompleted;
use Cognesy\AgentCtrl\Event\StreamProcessingStarted;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Events\Event;
use Cognesy\Http\Events\DebugStreamChunkReceived;
use Cognesy\Http\Events\DebugStreamLineReceived;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\Response\ResponseTransformationFailed;
use Cognesy\Instructor\Events\Response\ResponseValidated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated;
use Cognesy\Polyglot\Inference\Events\StreamEventParsed;

final class RuntimeProgressProjector
{
    public function project(object $event): ?RuntimeProgressUpdate
    {
        return match (true) {
            $this->isStartedEvent($event) => $this->makeUpdate($event, RuntimeProgressStatus::Started),
            $this->isCompletedEvent($event) => $this->makeUpdate($event, RuntimeProgressStatus::Completed),
            $this->isFailedEvent($event) => $this->makeUpdate($event, RuntimeProgressStatus::Failed),
            $this->isStreamEvent($event) => $this->makeUpdate($event, RuntimeProgressStatus::Stream),
            $this->isProgressEvent($event) => $this->makeUpdate($event, RuntimeProgressStatus::Progress),
            default => null,
        };
    }

    private function isStartedEvent(object $event): bool
    {
        return match (true) {
            $event instanceof AgentExecutionStarted,
            $event instanceof AgentCtrlExecutionStarted,
            $event instanceof ProcessExecutionStarted,
            $event instanceof ResponseParsingStarted,
            $event instanceof StreamProcessingStarted,
            $event instanceof StructuredOutputStarted => true,
            default => false,
        };
    }

    private function isCompletedEvent(object $event): bool
    {
        return match (true) {
            $event instanceof AgentExecutionCompleted,
            $event instanceof AgentCtrlExecutionCompleted,
            $event instanceof ResponseValidated,
            $event instanceof StreamProcessingCompleted => true,
            default => false,
        };
    }

    private function isFailedEvent(object $event): bool
    {
        return match (true) {
            $event instanceof AgentExecutionFailed,
            $event instanceof AgentErrorOccurred,
            $event instanceof ResponseTransformationFailed => true,
            default => false,
        };
    }

    private function isStreamEvent(object $event): bool
    {
        return match (true) {
            $event instanceof PartialInferenceDeltaCreated,
            $event instanceof StreamEventParsed,
            $event instanceof PartialResponseGenerated,
            $event instanceof AgentTextReceived,
            $event instanceof StreamChunkProcessed,
            $event instanceof DebugStreamChunkReceived,
            $event instanceof DebugStreamLineReceived => true,
            default => false,
        };
    }

    private function isProgressEvent(object $event): bool
    {
        return match (true) {
            $event instanceof AgentStepCompleted,
            $event instanceof HttpRequestSent,
            $event instanceof RequestBuilt,
            $event instanceof CommandSpecCreated,
            $event instanceof SandboxInitialized,
            $event instanceof SandboxPolicyConfigured,
            $event instanceof SandboxReady,
            $event instanceof ExecutionAttempted,
            $event instanceof ProcessExecutionCompleted,
            $event instanceof ResponseDataExtracted,
            $event instanceof ResponseParsingCompleted => true,
            default => false,
        };
    }

    private function makeUpdate(object $event, RuntimeProgressStatus $status): RuntimeProgressUpdate
    {
        return new RuntimeProgressUpdate(
            status: $status,
            source: $this->source($event),
            eventType: $event::class,
            message: (string) $event,
            operationId: $this->operationId($event),
            payload: $this->payload($event),
        );
    }

    private function source(object $event): string
    {
        return match (true) {
            $event instanceof AgentExecutionStarted,
            $event instanceof AgentStepCompleted,
            $event instanceof AgentExecutionCompleted,
            $event instanceof AgentExecutionFailed => 'agents',
            $event instanceof AgentCtrlExecutionStarted,
            $event instanceof AgentCtrlExecutionCompleted,
            $event instanceof AgentErrorOccurred,
            $event instanceof AgentTextReceived,
            $event instanceof RequestBuilt,
            $event instanceof CommandSpecCreated,
            $event instanceof SandboxInitialized,
            $event instanceof SandboxPolicyConfigured,
            $event instanceof SandboxReady,
            $event instanceof ProcessExecutionStarted,
            $event instanceof ExecutionAttempted,
            $event instanceof ProcessExecutionCompleted,
            $event instanceof StreamProcessingStarted,
            $event instanceof StreamChunkProcessed,
            $event instanceof StreamProcessingCompleted,
            $event instanceof ResponseParsingStarted,
            $event instanceof ResponseDataExtracted,
            $event instanceof ResponseParsingCompleted => 'agent_ctrl',
            $event instanceof PartialInferenceDeltaCreated,
            $event instanceof StreamEventParsed => 'inference',
            $event instanceof StructuredOutputStarted,
            $event instanceof PartialResponseGenerated,
            $event instanceof ResponseValidated,
            $event instanceof ResponseTransformationFailed => 'structured_output',
            $event instanceof HttpRequestSent,
            $event instanceof DebugStreamChunkReceived,
            $event instanceof DebugStreamLineReceived => 'http',
            default => 'runtime',
        };
    }

    private function operationId(object $event): ?string
    {
        return match (true) {
            method_exists($event, 'executionId') => (string) $event->executionId(),
            property_exists($event, 'executionId') && is_string($event->executionId) => $event->executionId,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(object $event): array
    {
        return match (true) {
            $event instanceof Event && is_array($event->data) => $event->data,
            default => [],
        };
    }
}
