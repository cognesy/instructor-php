# Symfony Package Cheatsheet

Status: bundle surface, config translation, and baseline core bindings are in place.

Current guarantees:

- package directory exists under `packages/symfony`
- Composer package name is `cognesy/instructor-symfony`
- PSR-4 namespace root is `Cognesy\Instructor\Symfony\`
- docs live under `packages/symfony/docs/`
- bundle alias is `instructor`
- Symfony config is translated into `LLMConfig`, `EmbeddingsConfig`, `StructuredOutputConfig`, and `HttpClientConfig`
- the container exposes `CanProvideConfig`, `CanSendHttpRequests`, `CanCreateInference`, `CanCreateEmbeddings`, and `CanCreateStructuredOutput`

Planned next steps:

- harden transport selection and shared event-bus wiring
- layer in AgentCtrl, native agents, persistence, telemetry, logging, testing, and docs
