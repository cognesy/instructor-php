---
title: Philosophy
description: 'The principles behind Instructor PHP.'
---

> The philosophy behind Instructor was originally formulated by
> [Jason Liu](https://twitter.com/jxnlco), creator of the Python version, and adapted for this
> PHP port.

> "Simplicity is a great virtue, but it requires hard work to achieve it and education to
> appreciate it. And to make matters worse: complexity sells better." -- Edsger Dijkstra

## Simplicity

Most users only need to learn `StructuredOutput` and a response model class to get started.
There is no new prompting language, no framework-specific base class to extend, and no hidden
abstractions between you and the LLM.

## Transparency

Instructor writes very few prompts on your behalf, and the ones it does write are visible and
configurable. The library does not try to hide what it sends to the model -- you stay in control
of the conversation.

## Flexibility

If you already have code that calls an LLM provider directly, adopting Instructor is incremental.
Add a `responseModel` to your existing call and use `get()` to receive a typed result. Any plain
PHP class works as a response model -- no base class or interface is required.

## The Zen of Instructor

Maintain the flexibility and power of PHP classes without unnecessary constraints.

1. Define a data schema: `class UserProfile { ... }`
2. Add validators and methods on that schema.
3. Encapsulate LLM logic in a function: `function extract($text): UserProfile`
4. Write typed computations against the result, or call methods on the object directly.

It should be that simple.

## Our Goals

The goal of the library -- and its documentation -- is to help you be a better PHP developer and,
as a result, a better AI engineer.

- The library is a result of a desire for simplicity.
- It should help maintain simplicity in your codebase.
- It will not try to write prompts for you.
- It will not create indirections or abstractions that make debugging harder.

The library is designed to be adaptable and open-ended, allowing you to extend its functionality
based on your specific requirements. If you have questions or ideas, reach out on
[GitHub Discussions](https://github.com/cognesy/instructor-php/discussions) or
[@ddebowczyk](https://twitter.com/ddebowczyk).
