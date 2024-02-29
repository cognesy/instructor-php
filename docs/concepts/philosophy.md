# Philosophy

!!! note

    Philosophy behind Instructor was formulated by [Jason Liu](https://twitter.com/jxnlco), the creator of original version of Instructor in Python and adapted for the PHP port.

Instructor values [simplicity](https://eugeneyan.com/writing/simplicity/) and flexibility in leveraging language models. It offers a streamlined approach for structured output, avoiding unnecessary dependencies or complex abstractions.

> “Simplicity is a great virtue, but it requires hard work to achieve it and education to appreciate it. And to make matters worse: complexity sells better.” — Edsger Dijkstra

### Proof that its simple

1. Most users will only need to learn `responseModel` and `Instructor::respond()` to get started.
2. No new prompting language to learn, no new abstractions to learn.

### Proof that its transparent

1. We write very little prompts, and we don't try to hide the prompts from you.
2. We give you config over the prompts we do write ('reasking' and in the future - JSON_MODE prompts).

### Proof that its flexible

1. If you build a system with OpenAI directly, it is easy to incrementally adopt Instructor by just adding `Instructor::respond()` with data schemas fed in via `responseModel`.
2. Use any class to define your data schema (no need to inherit from some base class).

## The zen of `instructor`

Maintain the flexibility and power of PHP classes, without unnecessary constraints.

Begin with a function and a return type hint – simplicity is key. I've learned that the goal of a making a useful framework is minimizing regret, both for the author and hopefully for the user.

1. Define data schema `<?php class StructuredData { ... }`
2. Define validators and methods on your schema.
3. Encapsulate all your LLM logic into a function `<?php function extract($input) : StructuredData`
4. Define typed computations against your data with `<?php function compute(StructuredData $data):` or call methods on your schema `<?php $data->compute()`

It should be that simple.

## Our Goals

The goal for the library, [documentation](https://cognesy.github.io/instructor-php/), and [blog](https://cognesy.github.io/instructor-php/blog/), is to help you be a better programmer and, as a result, a better AI engineer.

- The library is a result of our desire for simplicity.
- The library should help maintain simplicity in your codebase.
- We won't try to write prompts for you,
- We don't try to create indirections or abstractions that make it hard to debug in the future

Please note that the library is designed to be adaptable and open-ended, allowing you to customize and extend its functionality based on your specific requirements. If you have any further questions or ideas hit us up [@jnxlco](https://twitter.com/jxnlco) or [@ddebowczyk](https://twitter.com/ddebowczyk)

Cheers!
