<?php

return [
    'defaultPreset' => 'base',

    'presets' => [
        'base' => [
            // default mode
            'defaultOutputMode' => 'tool_call',

            // max retries
            'maxRetries' => 0,

            // should Instructor use object references or quote objects
            'useObjectReferences' => false,

            // default schema name
            'defaultSchemaName' => 'default_schema',
            'defaultSchemaDescription' => '',

            // default tool name and description
            'defaultToolName' => 'extracted_data',
            'defaultToolDescription' => 'Function call based on user instructions.',

            // default output class
            'defaultOutputClass' => 'Cognesy\Instructor\Extras\Structure\Structure',

            // default prompts
            'retryPrompt' => "JSON generated incorrectly, fix following errors:\n",

            // default extraction prompts
            'jsonPrompt' => "Response must follow JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object.\n",
            'jsonSchemaPrompt' => "Response must follow provided JSON Schema. Respond correctly with strict JSON object.\n",
            'mdJsonPrompt' => "Response must validate against this JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object within a ```json {} ``` codeblock.\n",
            'toolsPrompt' => "Extract correct and accurate data from the input using provided tools. Response must follow JSON Schema:\n<|json_schema|>\n Make sure to follow defined parameter types.\n",

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
            'defaultToAnonymousClass' => false,
            'deserializationErrorPrompt' => "Failed to serialize response:\n<|json|>\n\nSerializer error:\n<|error|>\n\nExpected schema:\n<|jsonSchema|>\n",

            // transformation
            'throwOnTransformationFailure' => false,
        ],
    ],
];