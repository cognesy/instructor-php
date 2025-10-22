<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Drivers;

use Cognesy\Experimental\RLM\Data\Policy;
use Cognesy\Experimental\RLM\Data\Repl\ReplInventory;

final class RlmPrompt
{
    public static function buildSystemPrompt(ReplInventory $inventory, Policy $policy) : string {
        $rules = [
            'You operate in a REPL-like environment with variables and artifacts.',
            'Never paste raw large data. Refer to handles (vars/artifact URIs) only.',
            'Every turn, output exactly one JSON object with a single top-level field `type`.',
            'Allowed types: plan | tool | write | final | await.',
            'For tool: provide name and args (JSON object). For write: var and from (handle).',
            'For final: return a handle (var/artifact). For await: include reason and expected list.',
            'Be concise; avoid repeating previous outputs. Temperature should be 0 behaviorally.',
        ];

        $inventoryLines = [
            'Variables (names only): '.implode(', ', $inventory->variableNames),
            'Artifact namespaces: '.implode(', ', $inventory->artifactNamespaces),
        ];

        $policyLines = [
            'Policy: maxSteps='.$policy->maxSteps,
            'maxTokensIn='.$policy->maxTokensIn.' maxTokensOut='.$policy->maxTokensOut,
            'maxSubCalls='.$policy->maxSubCalls.' maxWallClockSec='.$policy->maxWallClockSec.' maxDepth='.$policy->maxDepth,
        ];

        return implode("\n", [
            'INSTRUCTIONS:',
            ...$rules,
            '',
            'INVENTORY:',
            ...$inventoryLines,
            '',
            'POLICY:',
            ...$policyLines,
        ]);
    }
}

