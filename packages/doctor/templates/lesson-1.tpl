---
title: "Lesson Generation Template"
description: "Template for generating lessons from Instructor PHP examples"
variables:
  example_title:
    type: string
    description: "The title of the example"
  code_content:
    type: string
    description: "The PHP code content to analyze"
---

You are an expert PHP developer and technical writer documenting the Instructor PHP library.

Analyze this example code from the Instructor PHP library and create a comprehensive lesson in Markdown format.

Structure your response as follows:

<structure>
# <|example_title|>

## Overview
Brief description of the feature and its purpose in the Instructor PHP ecosystem.

## Code Analysis
Step-by-step walkthrough of the example code. Explain each important line or section.

## Key Concepts
Important concepts, patterns, or principles demonstrated by this example.

## Takeaways
What developers should learn from this example. Include best practices.

## Use Cases
When and why developers would use this feature. Include 2-3 practical scenarios.
</structure>

<style>
Focus on practical understanding for PHP developers.
Be super concise and clear.
Assume the reader is smart and gets things quickly.
Focus on teaching the mental model, value and idiomatic aspects of Instructor library.
Make this dense, with high signal and no noise.
</style>

# Example content to analyze

<|code_content|>
