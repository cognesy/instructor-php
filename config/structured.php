<?php

return [
    // default mode
    'defaultMode' => 'json',

    // max retries
    'maxRetries' => 3,

    // should Instructor use object references or quote objects
    'useObjectReferences' => false,

    // default tool name and description
    'defaultToolName' => 'extracted_data',
    'defaultToolDescription' => 'Function call based on user instructions.',

    // default prompts
    'defaultRetryPrompt' => "JSON generated incorrectly, fix following errors:\n",
    'defaultJsonPrompt' => "Response must follow JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object.\n",
    'defaultJsonSchemaPrompt' => "Response must follow provided JSON Schema. Respond correctly with strict JSON object.\n",
    'defaultToolsPrompt' => "Extract correct and accurate data from the input using provided tools.\n",
    'defaultMdJsonPrompt' => "Response must validate against this JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object within a ```json {} ``` codeblock.\n",

    // default chat structure - order of sections in the structured output chat sequence
    'defaultChatStructure' => [
        // potentially cached - predefined sections used to construct the script
        'system',
        'pre-cached',
        'pre-cached-prompt', 'cached-prompt', 'post-cached-prompt',
        'pre-cached-examples', 'cached-examples', 'post-cached-examples',
        'pre-cached-input', 'cached-input', 'post-cached-input',
        'cached-messages',
        'post-cached',
        // never cached
        'pre-prompt', 'prompt', 'post-prompt',
        'pre-examples', 'examples', 'post-examples',
        'pre-input', 'input', 'post-input',
        'messages',
        'pre-retries', 'retries', 'post-retries'
    ],
];