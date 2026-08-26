# Tell capability contracts

Tell capability contracts live under `Cognesy\Tell\Contracts`. They are
operation-oriented replacement seams for host composition, not mirrors of
every internal class.

The contract layer deliberately reuses the public domain types Tell already
exposes:

- Agents owns `AgentLoop`, `AgentDefinition`, `AgentState`, execution status,
  budgets, and cooperative cancellation;
- Polyglot owns `LLMConfig` and inference usage; and
- Config owns resolved secret values and their redacted debug projection.

Tell adds boundary values only for effective configuration and provenance,
resolved paths, normalized source-free event envelopes, extension discovery,
and framework-neutral command descriptors. Contracts do not import Cordis,
Symfony Console, filesystem workspace implementations, provider drivers, or
private Agents implementation types.

## Cardinality

`TellCapabilityContracts::cardinalities()` is the executable source of truth.
Execution, agent construction, model, secrets, workspace, conversations,
configuration, paths, discovery, dispatch, observation, application assembly,
protocol, cancellation, and clock capabilities are singletons. Branch
configuration is an optional singleton. Extension, tool, and command
contributions are ordered aggregators.

Duplicate singleton providers are graph errors. Optional means that a consumer
must work when no provider exists; it does not permit duplicates. Contributions
retain module order and their aggregate rejects duplicate semantic keys.

## Shell boundaries

`TellCommandDescriptor` contains a validated name and a fresh-object factory.
It does not know Symfony, but the standard CLI module validates that each
factory creates a Symfony command. `CanRunTellProtocol` receives a decoded,
bounded protocol request and a framework-neutral frame writer; stdin, stdout,
and Symfony remain in the shell adapter.
