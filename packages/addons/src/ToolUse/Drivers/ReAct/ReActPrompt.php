<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct;

use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Utils\Json\Json;

final class ReActPrompt
{
    public static function buildSystemPrompt(Tools $tools) : string {
        $toolSchemas = $tools->toToolSchema();
        $toolList = $tools->descriptions();
        $catalog = [
            'tools' => $toolList,
            'schemas' => array_map(
                fn(array $item) => [
                    'name' => $item['function']['name'] ?? '',
                    'parameters' => $item['function']['parameters'] ?? [],
                ],
                $toolSchemas,
            ),
        ];

        $rules = [
            'You are a ReAct-style agent using Thought/Action/Observation steps.',
            'At each step, output strictly valid JSON that matches the provided schema.',
            'If you choose to act, set type=call_tool and provide tool + args.',
            'When acting, args must be a JSON object whose keys match the selected tool parameters.',
            'If you can finish, set type=final_answer and provide answer.',
            'Keep the thought concise and actionable.',
            'Use only the available tools and their parameter schemas.',
        ];

        return implode("\n", [
            'INSTRUCTIONS:',
            ...$rules,
            '',
            'TOOL CATALOG (names, descriptions):',
            Json::encode($catalog['tools']),
            '',
            'TOOL SCHEMAS (name, parameters):',
            Json::encode($catalog['schemas']),
        ]);
    }
}
