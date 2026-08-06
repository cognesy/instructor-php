---
title: 'Safe and full eval trace policies'
docname: 'agent_eval_trace_policies'
order: 13
id: 'ae13'
tags:
  - 'agents'
  - 'evals'
  - 'traces'
  - 'security'
---
## Overview

Eval traces are safe by default: tool arguments, results, and error payloads are represented by
hashes, byte counts, and shape-only previews. Opt into `full()` only when the trace destination
is trusted and the payload is appropriate to retain. The policy is attached to a target, so the
same boundary applies to local runs and to hydrated HTTP target snapshots.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Evals\EvalTracePolicy;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Agents\Tool\Tools\BaseTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

final class SensitiveLookupTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'sensitive_lookup',
            description: 'Returns a sensitive value for the trace-policy example.',
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): string
    {
        return 'lookup result: secret-result-7f3a';
    }

    #[\Override]
    public function toToolSchema(): ToolDefinition
    {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters'),
        )->toArray());
    }
}

$secret = 'customer-token-4111111111111111';
$target = static function (EvalTracePolicy $policy) use ($secret): LocalAgentTarget {
    return LocalAgentTarget::fromFactory(static fn() => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps(
            ScenarioStep::toolCall('sensitive_lookup', ['token' => $secret]),
            ScenarioStep::final('Lookup complete.'),
        )))
        ->withCapability(new UseTools(new SensitiveLookupTool()))
        ->build(), $policy);
};

// Safe is the default and keeps the secret out of serialized eval evidence.
$safeRun = $target(EvalTracePolicy::safe())->open()->send('Look up the customer token.')->run();
$safeJson = json_encode($safeRun->toArray(), JSON_THROW_ON_ERROR);

// Full is an explicit opt-in for a trusted, deliberately controlled destination.
$fullRun = $target(EvalTracePolicy::full())->open()->send('Look up the customer token.')->run();
$fullJson = json_encode($fullRun->toArray(), JSON_THROW_ON_ERROR);

if (str_contains($safeJson, $secret)
    || str_contains($safeJson, 'secret-result-7f3a')
    || !str_contains($fullJson, $secret)
    || !str_contains($fullJson, 'secret-result-7f3a')
) {
    throw new RuntimeException('EvalTracePolicy did not enforce the expected safe/full boundary.');
}

$safeTool = $safeRun->toArray()['steps'][0]['toolExecutions'][0];
$fullTool = $fullRun->toArray()['steps'][0]['toolExecutions'][0];
echo 'Safe arguments: ' . json_encode($safeTool['arguments'], JSON_THROW_ON_ERROR) . "\n";
echo 'Full arguments: ' . json_encode($fullTool['arguments'], JSON_THROW_ON_ERROR) . "\n";
echo "Result: safe traces digest payloads; full traces retain them by explicit opt-in.\n";
?>
```
