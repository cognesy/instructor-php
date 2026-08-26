# Step 8: Prove replacement and package boundaries

## Outcome

Independent implementations and clean consumers prove replaceability before
any Composer package multiplication.

## Size and parallelism

Medium. External fixtures, package proof, and architecture checks can run in
parallel after the shared public surface stabilizes.

## Work

- Build at least two independent implementations, including the in-memory
  workspace and an external observer, model, or execution fixture.
- Run shared conformance against every provider of a replaceable contract.
- Add architecture tests for contracts, static kernel, modules, SDK, CLI, and
  protocol dependency direction.
- Pack proposed packages and install them into clean PHP 8.3, 8.4, and 8.5
  consumers with minimum and maximum supported dependency resolution.
- Test absence, duplicate providers, invalid implementations, partial boot
  failure, and reverse-order disposal.
- Measure cohesion and independent change cadence before proposing package
  splits.
- Publish compatibility policy based on Composer semantic versions and
  conformance, not runtime binding revisions.

## Acceptance evidence

- An external fixture replaces a standard definition using public APIs only.
- Conformance passes for standard and alternative implementations.
- Clean consumers need no path repositories or root dev autoloading.
- Architecture checks reject implementation and framework leakage.
- Every proposed package has explicit evidence for independent existence.

## Boundary

Do not create a package per interface or module. Source boundaries are already
useful and may remain the final packaging shape.

## Enables

[Step 9](09-deliver-scoped-resource-lifecycle.md) can add lifecycle for a
concrete user feature without destabilizing the ordinary static host.
