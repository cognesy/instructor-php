---
title: 'Messages API'
docname: 'messages_api'
path: ''
id: 'e4e7'
---
## Overview

Instructor allows you to use `Messages` and `Message` classes to work with chat
messages and their sequences.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Str;

class Code {
    public string $code;
    public string $programmingLanguage;
    public string $codeDescription;
}

$messages = Messages::empty()
    ->asSystem('You are a senior PHP8 backend developer.')
    ->asDeveloper('Be concise and use modern PHP8.2+ features.') // OpenAI developer role is supported and normalized for other providers
    ->asUser([
        'What is the best way to handle errors in PHP8?',
        'Provide a code example.',
        'Use modern PHP8.2+ features.',
    ])
    ->asAssistant('I will provide a code example that demonstrates how to handle errors using try-catch. Any specific domain?');

$messages->appendMessage(Message::asUser('Make it insurance related.'));

$lastMessageId = $messages->last()->id()->toString();
print("Last message ID: {$lastMessageId}\n");

print("Extracting structured data using LLM...\n\n");
$code = (new StructuredOutput)
    ->using('openai')
    ->withMessages($messages)
    ->withResponseModel(Code::class)
    ->withOutputMode(OutputMode::MdJson)
    ->get();

print("Extracted data:\n");
dump($code);

assert(!empty($code->code));
assert(!empty($code->codeDescription));
assert(Str::contains(strtolower($code->programmingLanguage), 'php'));
?>
```
