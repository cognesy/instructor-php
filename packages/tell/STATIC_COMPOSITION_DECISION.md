# Tell static composition decision

Status: accepted on 2026-08-26.

Tell's static host uses ordinary named, factory-backed module definitions with
explicit graph validation. It does not use `Cognesy\Utils\Context`, `Layer`,
or `Key` in product code.

## Evidence

`tests/Unit/CompositionPrimitiveDecisionTest.php` exercises both candidates
against the same two-module graph and adverse cases.

The comparison established:

- Both options retain factory and result typing, although each still needs an
  advertised-interface check.
- Named definitions reject duplicates directly. Raw `Layer::merge()` silently
  selects the right provider, so the adapter needs a shadow registry.
- Named definitions aggregate missing requirements before construction. Raw
  Context lookup reports one missing service at a time.
- A named dependency graph has explicit order. `dependsOn()` works, but its
  reverse phrasing and merge behavior need adapter-specific rules.
- Neither option owns cleanup. The host must track constructed modules and
  dispose them in reverse order.
- Named definitions retain module IDs for diagnostics. The adapter must
  duplicate that metadata outside Context.
- Named dependencies are confined to factory calls. Context must be confined
  to an adapter to avoid becoming a product-level service locator.
- Named composition uses arrays, closures, and one graph pass. The adapter adds
  Context and Layer while retaining the same graph pass.

Both candidates can construct fresh implementations in deterministic order.
Neither owns lifecycle. At Tell's graph size their measured construction cost is
negligible beside CLI bootstrap, so semantic clarity decides the choice.

## Rejected trade-off

A constrained Context/Layer adapter could prohibit `merge()` and add duplicate,
missing-dependency, and lifecycle bookkeeping. That bookkeeping is already the
complete useful core of the named-factory host, while Context would no longer
provide its principal composition behavior. Adopting it would therefore make
the design larger, not smaller.

The existing Utils primitives remain useful for applications that deliberately
want typed context composition. Tell does not change their right-biased merge
contract.

## Consequences

- Module definitions are immutable and contain fresh-instance factories.
- Singleton capability identity is the advertised interface FQCN.
- Duplicate module IDs and singleton providers fail admission.
- All missing mandatory capabilities are reported in one pre-boot error.
- Only module factories can resolve declared dependencies.
- Runtime products and public facades never receive a general container.
- The host owns reverse-order, exhaustive cleanup; Cordis remains outside the
  static composition phase.
