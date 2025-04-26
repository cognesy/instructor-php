---
title: 'Quickstart'
description: 'Start working with LLMs in under 5 minutes'
---

This guide will help you get started with Polyglot in your PHP project in under 5 minutes.

For detailed setup instructions, see [Setup](setup).


## Install Polyglot with Composer

To install Polyglot in your project, run following command in your terminal:

```bash
composer require cognesy/instructor-polyglot
```

> NOTE: Polyglot is already included in Instructor for PHP package, so if you have it installed, you don't need to install Polyglot separately.


## Create and Run Example

### Step 1: Prepare your OpenAI API Key

In this example, we'll use OpenAI as the LLM provider. You can get it from the [OpenAI dashboard](https://platform.openai.com/).

### Step 2: Create a New PHP File

In your project directory, create a new PHP file `test-polyglot.php`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Cognesy\Polyglot\LLM\Inference;

// Set up OpenAI API key
$apiKey = 'your-openai-api-key';
putenv("OPENAI_API_KEY=" . $apiKey);
// WARNING: In real project you should set up API key in .env file.

$answer = Inference::text('What is capital of Germany');

echo "USER: What is capital of Germany\n";
echo "ASSISTANT: $answer\n";
```

<Warning>
    You should never put your API keys directly in your real project code to avoid getting them compromised. Set them up in your .env file.
</Warning>

### Step 3: Run the Example

Now, you can run the example:

```bash
php test-polyglot.php

# Output:
# USER: What is capital of Germany
# ASSISTANT: Berlin
```


## Next Steps

You can start using Polyglot in your project right away after installation.

But it's recommended to publish configuration files and prompt templates to your project directory, so you can
customize the library's behavior and use your own prompt templates.

You should also set up LLM provider API keys in your `.env` file instead of putting them directly in your code.

See setup instructions for more details.
