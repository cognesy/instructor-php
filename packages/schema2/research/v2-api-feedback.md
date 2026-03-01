# Schema2 V2 API Surface Review — Critical Feedback

Date: 2026-03-01

---

## What the plan gets right

- The callsite replacement matrix naming specific files is genuinely useful. That level of concreteness is rare and good.
- The acceptance gates (import bans) are the right idea — measurable, not vague.
- The decision to NOT introduce a flood of contracts is correct.
- The execution order (ToolCallBuilder first) is sensible.

---

## Gaps that need to be fixed before this is truly execution-ready

### 1. `Schema.php` is its own coupling problem the plan never mentions

`packages/schema2/src/Data/Schema/Schema.php:82-155` — the data class instantiates `SchemaToJsonSchema`, `SchemaFactory`, and `JsonSchemaToSchema` directly inside its convenience methods (`toJsonSchema()`, `fromTypeName()`, `string()`, `object()`, etc.).

This means even after you introduce `CanRenderSchema`, every caller who writes `$schema->toJsonSchema()` bypasses the contract and hardwires the concrete visitor. The `Schema` data class must not know its own factory. Those static factory methods (`Schema::string()`, `Schema::object()`) belong on `SchemaFactory`, not on the node class. The plan should explicitly call out removing them.

### 2. The `ToolCallBuilder` removal is underdefined — the reference-expansion mechanism is the real problem

The plan says "move tool-call envelope assembly out of schema package." But looking at `StructuredOutputSchemaRenderer.php:40-54`, the actual coordination is:

1. A `ToolCallBuilder` is created
2. `$toolCallBuilder->onObjectRef(...)` is passed as a callback to `SchemaToJsonSchema::toArray()`
3. The visitor calls the callback when it encounters an `ObjectRefSchema`
4. After rendering, `ToolCallBuilder::definitions()` resolves the queued refs recursively

The envelope assembly (`['type' => 'function', 'function' => [...]]`) is trivial. The *real* thing `ToolCallBuilder` owns is stateful `$defs` expansion — the queue of unresolved object references. If you just drop `ToolCallBuilder`, you lose `$defs` support silently. The plan needs a section on where this stateful reference-tracking logic lands after the move.

### 3. `TypeDetails` as "temporary" with no replacement is a debt accumulator

`TypeDetails` is imported by: `dynamic` (`Field`, `FieldFactory`, `HandlesFieldDefinitions`, `HandlesFieldSchema`, `HandlesFieldAccess`, `HandlesSerialization`, `ProvidesSchema`, `StructureFactory`), `instructor` (`Sequence`, `Maybe`), and is embedded into every schema node. Calling it "temporary; keep only while downstream migration is incomplete" without saying what replaces it is not a plan — it's a placeholder. Either it stays and its imports are acceptable, or it goes and the plan must name the replacement type.

### 4. `InputField`/`OutputField` → `Descriptions` circular dependency is not addressed

The plan says "move attribute/reflection helpers to `experimental`" for `InputField`/`OutputField`. But:

- `packages/schema2/src/Utils/Descriptions.php:46-47` already imports `InputField` and `OutputField`
- `Descriptions.php` is used by `ClassInfo.php` and `FunctionInfo.php`
- Those are used everywhere including in non-experimental code

If you move `InputField`/`OutputField` to experimental, you create a circular dependency: `schema` → `experimental`. Either `Descriptions.php` must stop reading those attributes (and only read `Description`), or the attributes stay in schema and the plan must acknowledge that.

### 5. `FunctionCallFactory` in addons has a double dependency the plan doesn't resolve

`packages/addons/src/FunctionCall/FunctionCallFactory.php:7-8` imports both `Schema\Reflection\FunctionInfo` AND uses `Dynamic\StructureFactory`. The plan says addons should use "native reflection and/or dynamic-local callable analyzer" — but even after removing `FunctionInfo`, it still depends on `dynamic`. The plan should state clearly: does `addons` depend on `dynamic` long-term, or is `FunctionCall` being restructured to not need a `Structure` at all?

---

## The simpler path for `dynamic` that both docs miss

The two documents treat the `dynamic` decoupling as requiring a large redesign (`DynamicSchema`, `DynamicRecord`, `RecordNormalizer`, etc.). That's the right long-term direction, but there's a much smaller change that achieves the schema2 boundary goal immediately without the full redesign:

**`StructureFactory` doesn't need to import `ClassInfo` or `FunctionInfo` directly.** It can route through `SchemaFactory`:

- `StructureFactory::fromCallable($fn)` → `SchemaFactory::schema($fn)` → `Schema` → `StructureFactory::fromSchema()`
- `StructureFactory::fromClass($class)` → `SchemaFactory::schema($class)` → `Schema` → `StructureFactory::fromSchema()`

`fromSchema()` already exists in `StructureFactory` at line 81. The full reflection-to-field pipeline is already there. This means `dynamic` only needs `SchemaFactory` + `Schema` (both Group A public API), and `ClassInfo`/`FunctionInfo` imports disappear from `dynamic` immediately, without any `DynamicRecord` work.

The plan should split this into:

- **Phase 1 (schema2 boundary):** Route `dynamic`'s reflection through `SchemaFactory`, drop `Reflection\*` imports from `StructureFactory`. Achievable in one PR.
- **Phase 2 (dynamic redesign):** The full `DynamicSchema`/`DynamicRecord`/array-first redesign from `better-dynamic-structure-design.md`. Independent of the schema2 cleanup.

Currently the two documents treat these as one effort without defining the seam, which makes the work seem larger than it is and makes it harder to sequence.

---

## What to add to the document

**Section: Schema data class self-cleanup**

List `Schema.php`'s convenience statics as a removal target: `toJsonSchema()`, `fromTypeName()`, `string()`, `int()`, `float()`, `bool()`, `array()`, `object()`, `enum()`, `collection()`. These instantiate the factory inside the node, directly contradicting the `CanRenderSchema` contract objective.

**Section: Reference-tracking design after ToolCallBuilder moves**

Define where the `$defs` expansion logic lives after `ToolCallBuilder` exits the schema package. Options:
- (a) a standalone `ReferenceExpander` service in instructor
- (b) baked into the `CanRenderSchema` implementation
- (c) a callback-based renderer that returns both JSON schema and `$defs` as a value object

Pick one and name it.

**Section: Unified execution order across schema2 + dynamic**

The current execution order only covers schema2. It should explicitly state that step 3 ("Remove dynamic/addons/experimental imports of schema reflection") is achieved by routing dynamic through `SchemaFactory` (Phase 1 of dynamic), NOT by the full dynamic redesign (Phase 2). The dynamic full redesign is a separate and independent effort.

**Section: TypeDetails disposition**

Either state "TypeDetails stays as permanent public API" or name the replacement. Do not leave it as "temporary."

**Corrected acceptance gate for `Descriptions.php`**

The import ban on `Schema\Utils\*` needs to first resolve whether `Descriptions.php` stays in schema (reading only `Description` attribute) or moves. Add this as an explicit prerequisite to gate 1.

---

## Minor: The name `JsonSchemaToSchema` is called out but not fixed

The doc notes it has a "bad name, contract ok." For a document claiming to define a clean 2.0 boundary, the target name should be stated. `CanParseJsonSchema` is already proposed as the contract; the concrete implementation should be named consistently (e.g., `DefaultJsonSchemaParser`).

---

## Summary assessment

The plan is directionally correct and more concrete than most architecture documents. The main actionable improvements are:

1. Add the `Schema.php` static factory cleanup as an explicit target.
2. Define where the `$defs`/reference-expansion logic goes after `ToolCallBuilder` moves.
3. Split the dynamic work into "route through SchemaFactory" (achievable now, removes Reflection imports) vs "full DynamicRecord redesign" (separate, from `better-dynamic-structure-design.md`).
4. Resolve the `InputField`/`OutputField`/`Descriptions` circular dependency explicitly before naming them as removal targets.
5. Decide `TypeDetails`: permanent API or named replacement — not "temporary."
