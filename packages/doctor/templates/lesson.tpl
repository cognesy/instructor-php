---
title: "Dense DevRel Lesson Template"
description: "Ultra-concise template for striking highlights"
variables:
  example_title:
    type: string
    description: "The title of the example"
  code_content:
    type: string
    description: "The PHP code content to analyze"
---

# CONTEXT

<example>
<|code_content|>
</example>

# YOUR TASK

You are creating ultra-dense DevRel content for the Instructor PHP library. Strip everything non-essential.

Guidelines:
- Show ONLY the striking code that demonstrates the core capability
- Replace boilerplate with `// ...`
- Focus on the "wow factor" - what makes this library special
- Use minimal but powerful explanations
- Highlight Instructor-specific idioms and mental models
- Make developers think "I need this"

Structure:
# <|example_title|>

**What it does:** One sentence explaining the core capability.

**The magic:**
```php
[Essential code snippet - remove all boilerplate]
```

**Why this matters:** 1-2 sentences on the value proposition.

**Instructor idiom:** The key pattern/concept that makes this work.
