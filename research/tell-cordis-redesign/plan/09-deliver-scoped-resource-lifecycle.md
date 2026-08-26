# Step 9: Deliver scoped-resource lifecycle

## Outcome

Tell ships one valuable resource-owning feature with explicit lifecycle,
approval, cancellation, bounds, and cleanup. Cordis is introduced behind that
feature, not as architecture for its own sake.

## Size and parallelism

Large. Product acceptance for the selected feature and Cordis consumer proof
can proceed in parallel; adapter implementation waits for both gates.

## Product gate

The selected feature is host-scoped persistent shell jobs. The decision,
public PHP shape, ownership rules, lifecycle, approval and output bounds,
failure recovery, and executable scenarios are defined in
`concept/persistent-shell-jobs.md`. `packages/agent-ctrl` supplied useful
process/result/stream vocabulary, but its one-shot bridge executor is not the
lifecycle implementation.

MCP lifecycle is rejected for this first increment because it would add
transport, protocol/version negotiation, discovery, schema conversion, and
authentication policy before proving the smaller resource-ownership boundary.

## Cordis production gate

- A tagged `cordis-php/cordis` release resolves from Packagist.
- Cordis CI and tests are green for its declared PHP and Symfony ranges.
- Clean Tell consumers install it without a path repository.
- Provider unpublication, restart, abandonment, and reverse cleanup have
  regression coverage.
- At least one clean non-Cordis-repository consumer proves normal use.
- Tell's PHP `^8.3` floor remains unchanged.

## Work

- Add an optional Cordis-backed resource-host adapter outside the static kernel.
- Translate explicitly selected factory definitions into scoped providers and
  consumers; never reuse disposed instances.
- Put clients, processes, listeners, protocol transports, and cleanup in the
  same owner scope.
- Validate configuration and approval policy before external effects.
- Prevent resource capability handles from escaping their owning scope.
- Bridge Cordis lifecycle diagnostics into normalized, redacted Tell
  observation without merging them with Agents inference events.
- Demonstrate the selected feature in D11 Harness.

## Acceptance evidence

- The feature has a direct PHP SDK example and, where appropriate, a CLI or
  protocol adapter.
- Normal completion, cancellation, failure, abandonment, and shutdown release
  resources deterministically.
- A failed start publishes no partial capabilities.
- Unrelated static SDK and CLI use does not boot Cordis.
- Packed Tell consumers resolve the tagged Cordis dependency normally.

## Boundary

This step delivers scoped lifecycle, not live configuration reload. The
ordinary host remains immutable after boot.

## Enables

Only demonstrated operational demand may justify optional reconciliation in
[Step 10](10-enable-supervised-reconciliation.md).
