# Step 6: Modularize configuration, tools, and observation

## Outcome

Configuration, paths, extension discovery, tools, and normalized observation
have explicit owners and replaceable static definitions.

## Size and parallelism

Large but divisible. Configuration/paths, discovery/tools, and observation can
run as three parallel tracks after workspace contracts stabilize.

## Work

- Complete `paths.standard` and `configuration.standard`, including optional
  branch-reader cardinality and explicit provenance.
- Make user defaults, workspace settings, host settings, and request intent
  follow one tested precedence path.
- Implement `extensions.composer` with structured discovery diagnostics.
- Implement `tools.standard` with validation, policy, approval, cancellation,
  output bounds, ask-user, coding tools, and subagent adapters.
- Implement `observation.standard` over the normalized event envelope.
- Characterize raw `TellEvent::source` compatibility while keeping raw Agents
  and provider objects out of the observer contract.
- Provide explicit PSR logging or telemetry adapters at the observation edge.
- Validate duplicate tool and contribution keys at graph admission.
- Keep all replacement pre-boot; do not promise lossless hot observer swap.

## Acceptance evidence

- Configuration precedence and provenance are identical across SDK and CLI.
- No product module independently reads or mutates Tell environment inputs.
- Invalid extension metadata is visible without partially registering tools.
- Direct and agent-mediated tools share approval, cancellation, and bounds.
- Event order, terminal outcome, redaction, and trace parity remain stable.
- External observer and tool fixtures integrate through public contracts only.

## Boundary

CLI rendering and protocol framing remain outside these modules. Observation
normalizes domain events; it does not replace Agents inference lifecycle.

## Enables

[Step 7](07-migrate-cli-protocol-and-sdk-wiring.md) can adapt one complete host
instead of rebuilding collaborators at each shell entry point.
