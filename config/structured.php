<?php

return [
    'defaultPreset' => 'base',

    'presets' => [
        'base' => [
            // default mode
            'outputMode' => 'tool_call',

            // max retries
            'maxRetries' => 0,

            // should Instructor use object references or quote objects
            'useObjectReferences' => false,

            // default schema name
            'schemaName' => 'default_schema',
            'schemaDescription' => '',

            // default tool name and description
            'toolName' => 'extracted_data',
            'toolDescription' => 'Function call based on user instructions.',

            // default output class
            'outputClass' => 'Cognesy\Instructor\Extras\Structure\Structure',

            // default prompts
            'retryPrompt' => "JSON generated incorrectly, fix following errors:\n",

            // default extraction prompts
            'modePrompts' => [
                'tool_call' => "Extract correct and accurate data from the input using provided tools.\n",
                'json' => "Response must follow JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object.\n",
                'json_schema' => "Response must follow provided JSON Schema. Respond correctly with strict JSON object.\n",
                'md_json' => "Response must validate against this JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object within a ```json {} ``` codeblock.\n",
            ],

            // default chat structure - order of sections in the structured output chat sequence
            'chatStructure' => [
                // potentially cached - predefined sections used to construct the script
                'system',
                'pre-cached',
                    'pre-cached-prompt', 'cached-prompt', 'post-cached-prompt',
                    'pre-cached-examples', 'cached-examples', 'post-cached-examples',
                    'cached-messages',
                'post-cached',
                // never cached
                'pre-prompt', 'prompt', 'post-prompt',
                'pre-examples', 'examples', 'post-examples',
                'pre-messages', 'messages', 'post-messages',
                'pre-retries', 'retries', 'post-retries'
            ],

            // deserialization
            'defaultToStdClass' => false,
            'deserializationErrorPrompt' => "Failed to serialize response:\n<|json|>\n\nSerializer error:\n<|error|>\n\nExpected schema:\n<|jsonSchema|>\n",

            // transformation
            'throwOnTransformationFailure' => false,
        ],
    ],
];