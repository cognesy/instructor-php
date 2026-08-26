# Step 4: Modularize agent and provider runtime

## Outcome

Agent construction, model/provider resolution, secrets, and execution are
replaceable before boot. `TellAgentFactory` and `TellRuntime` stop acting as
the application-wide composition root.

## Size and parallelism

Large. Model/secrets extraction and agent/execution extraction can proceed in
parallel behind Step 3 contracts, followed by one parity integration pass.

## Work

- Implement `paths.standard`, `secrets.standard`, `model.polyglot`,
  `agent.cognesy`, and `execution.default` definitions.
- Move definition loading and loop construction behind `CanBuildTellAgent`.
- Move provider/model selection and reasoning checks behind
  `CanResolveTellModel`.
- Publish a secret resolver, never a materialized secret map.
- Make execution consume workspace, configuration, clock, cancellation, tools,
  and observation explicitly.
- Preserve execution modes as policy in `execution.default`, not kernel logic.
- Keep Composer Agents discovery separate from Tell module composition.
- Keep the deterministic driver seam inside agent/model composition; do not
  fake policy, tools, events, or persistence implicitly.
- Evaluate reuse of `AgentBuilder` through a focused spike rather than making
  it a prerequisite.
- Prove subagent re-entry receives the intended request-local configuration and
  does not open a second hidden composition root.

## Acceptance evidence

- Existing execution scenarios have equal responses, failures, cancellation,
  events, budgets, and durable records through the modular path.
- An application replaces model and deterministic driver factories without
  subclassing or editing Tell.
- One run uses one immutable model resolution; no pre-boot replacement can
  affect an already booted host.
- Secret values are absent from descriptions and normalized events.
- Subagent tests prove consistent host and execution policy ownership.

## Boundary

Agents continues to own inference lifecycle. This step adds no dynamic module
replacement or Cordis dependency.

## Enables

The SDK can execute against explicit runtime capabilities while workspace
facades move in [Step 5](05-modularize-workspace-and-conversations.md).
