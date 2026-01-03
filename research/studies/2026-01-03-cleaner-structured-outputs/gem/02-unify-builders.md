# Analysis of Config and Request Builders

## 2. Confusing Separation: `ConfigBuilder` vs. `RequestBuilder`

### Observation
The process of configuring a request is split across two builder classes: `StructuredOutputConfigBuilder` and `StructuredOutputRequestBuilder`.

- **`StructuredOutputRequestBuilder`** is responsible for gathering core request data:
  - `messages` (the input)
  - `requestedSchema` (the desired output structure)
  - `model`
  - `prompt`, `system`, `examples`
  - `options`
- **`StructuredOutputConfigBuilder`** is responsible for gathering operational parameters and metadata:
  - `outputMode`
  - `maxRetries`
  - `retryPrompt`
  - `toolName`, `toolDescription`

This separation is confusing and artificial. From a user's perspective, all of these parameters are just "configuration" for a single request. The distinction between what belongs to a "request" and what belongs to "config" is not intuitive. For example, why is `model` part of the request, but `maxRetries` is part of the config?

Furthermore, both builders have their own `with()` methods, and the main `StructuredOutput::with()` method has to delegate to both, which is a sign of a leaky abstraction. The final objects they produce, `StructuredOutputRequest` and `StructuredOutputConfig`, are both passed around together, indicating they are two halves of the same whole.

### Improvement Opportunity: Unify into a Single `RequestBuilder`

**Proposal:**
Combine `StructuredOutputConfigBuilder` and `StructuredOutputRequestBuilder` into a single, comprehensive `RequestBuilder`. Similarly, merge `StructuredOutputRequest` and `StructuredOutputConfig` into a single `Request` data object.

**Justification:**
- **Simplicity:** A single builder with a single `with()` method is dramatically simpler for the user. All configuration happens in one place.
- **Clarity:** It eliminates the confusing and arbitrary distinction between "request" and "config" parameters. All parameters that define the job to be done are now part of the `Request`.
- **Reduced Complexity:** This change would remove two classes (`StructuredOutputConfigBuilder`, `StructuredOutputConfig`) and simplify the `StructuredOutput` facade, which would no longer need to manage two separate builders.
- **Streamlined Data Flow:** A single `Request` object would flow through the system, rather than passing around a pair of `(request, config)` objects.

**Action Items:**
1.  Create a new, unified `RequestBuilder` that incorporates all methods from both existing builders.
2.  Create a new `Request` class that holds all data from both `StructuredOutputRequest` and `StructuredOutputConfig`.
3.  Refactor `StructuredOutput` to use only the new `RequestBuilder`.
4.  Update downstream components (like `ResponseIteratorFactory` and the generators) to accept the new unified `Request` object.
5.  Delete the old builder and data classes.

I will now proceed to investigate the pipeline's internal components more deeply.
