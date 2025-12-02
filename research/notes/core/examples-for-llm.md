# Examples for LLM

We need a way to inject examples in a more structured way than as a text in PHPDocs.

- It mixes instructions with examples.
- It's not easy to extract examples from PHPDocs and manage them separately (e.g. using larger external source of examples)
- PHPDocs cannot be easily manipulated - it's not easy to inject / replace examples in PHPDocs.

## Examples & CanProvideExamples contract

Initially just to feed extraction prompts, esp. for weak models. Later: to
generate examples for prompt optimization.

## Questions

Do examples need to be provided at a class level or at a property level?
