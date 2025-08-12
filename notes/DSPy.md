# DSPy-in-PHP8: Modular, Optimizable Language Programs

*Substrate = **Signature/Schema**. Node = **Module**. Edge = **Field→Field**. State = **Tunables**. Learning = **TextGrad** over traces.*

---

# Core Primitives (DDD, strict types)

**Signature** · role: contract of I/O types & semantics

* Source: `Instructor\Signature`, `Schema` → JSON Schema
* Fields: named, stable, typed (DTOs, enums, value objects)
* Rationale: name-driven binding; zero stringly-typed plumbing
* Limitation solved: prompt drift, brittle parsing → **structured outputs**

**Module** · role: pure function over Signature

* API: `forward(InputDTO, Tracer): Prediction`
* Children: `children(): iterable<NamedChild>`; rewriting: `withReplacedChild(name, Module)`
* Rationale: composite DAG; explicit wiring; optimizer-friendly
* Limitation solved: reflection hacks → **explicit composition contract**

**Prediction** · role: typed carrier of outputs (+ aux)

* Contains: `OutputDTO`, optional `reasoning`, `usage`
* Rationale: uniform return type; effortless chaining
* Limitation solved: ad-hoc JSON/regex → **stable DTOs**

**Tunables** · role: text-parameters

* Fields: `instructions`, `fewShot: list<Example>`, (optional) `style`, `format`, `stopwords`
* Implemented by leaf `TunableModule` (`withTunables`, `tunables()`)
* Rationale: prompts/examples as first-class state
* Limitation solved: hidden prompt state → **versioned, serializable knobs**

---

# Execution & Observability

**Tracer / TraceEvent / TraceLog**

* Events: path, input DTO, output DTO, tunables snapshot, usage (tokens, latency), model id
* Rationale: provenance for optimization & debugging
* Limitation solved: “why did it answer X?” → **replayable traces**

**DAG Runner**

* Walk: parent `forward` invokes children; tracer logs per-node
* Concurrency: Fibers/batch on independent nodes; bounded pools
* Limitation solved: sequential bottlenecks → **fan-out/fan-in** patterns

---

# Optimization (TextGrad mental model)

**Metric** · role: task-level objective

* Types: exact match, F1, ROUGE/BLEU, JSON schema validity, cost-aware score
* Rationale: unifies evaluation across candidates

**Evaluator** · role: run program over dataset

* Outputs: aggregate score + `TraceLog` (+ per-example diagnostics)
* Pitfall handled: eval leakage → strict train/dev/test discipline

**Critic** (Text-as-gradient)

* Input: `TraceEvent`, global signals (metric deltas, failure modes)
* Output: `Critique` = rationale + proposed edits (`instructions`, `fewShot`, constraints)
* Rationale: chain-rule analogue (localize blame to nodes)
* Limitation solved: blind search → **guided tunable edits**

**Optimizer**

* Variants: RandomFewShot, BootstrapFewShot, InstructionSearch, **TextGradSearch** (critic-driven), MIPRO-style hybrid
* Mechanic: enumerate tunable nodes → propose variants → score → keep argmax
* Relationship: uses `ModuleWalk.enumerate/replaceAt` (no reflection)
* Pitfalls handled: instability, overfitting → early stop, dev gating, diversity of examples

**CandidateFactory**

* Sources: edits from Critic; templates (“Be concise”, formatting guards); mining good few-shots from traces
* Rationale: keeps proposal space compact & meaningful

---

# Composition & Introspection (without reflection)

**NamedChild** + **children()/withReplacedChild()**

* Rationale: path-addressable nodes (`root.expand`, `root.answer`)
* Enables: program rewriting, partial compilation, A/B on subtrees

**ModuleWalk**

* `enumerate(program) → path=>Module`
* `replaceAt(program, path, newModule) → program'`
* Rationale: persistent, immutable updates for reproducibility

**ProgramSerializer**

* Store: module class, ctor args, tunables, child graph, signature/schema hashes, training set hash, metric id
* Rationale: exact rebuild; cache compiled programs; diffable configs

---

# Leaf Modules (InstructorPHP leverage)

**PredictModule** (Tunable)

* Uses: `Instructor\Inference`, `StructuredOutput` → OutputDTO
* Render: `Signature` + `Schema` + `Tunables` + `Example[]` → prompt
* Returns: `Prediction(OutputDTO, reasoning?, usage)`
* Pitfalls handled: parsing failures → schema-constrained extraction

**ChainOfThoughtModule / ToolUseModule** (optional)

* Adds aux fields: `reasoning`, `tool_calls`, `completions`
* Rationale: richer signals for Critic & aggregation

---

# Data & Types (DX-first)

**DTOs for everything**

* Inputs/Outputs: value objects; never associative arrays
* Strong enums, UIDs, `NonEmptyString`, `TokenCount` etc.
* Benefit: IDE support, static analysis, predictable serialization

**Few-Shot Example**

* Reuse `Instructor\Example`; wrap in Tunables; maintain source provenance (id, dataset split)

---

# QA Pipeline (use case skeleton)

**Signatures**:

* `Expand: QuestionInput → SubQuestionsOutput`
* `Search: SubQuestion → SearchResultsOutput` (service, non-LLM)
* `Aggregate: AggregationInput → AggregatedContext`
* `Answer: AnswerInput → AnswerOutput`

**Program**: `QuestionAnswerProgram`

* Children: `expand`(Tunable), `aggregate`(Tunable), `answer`(Tunable)
* Flow: question → subquestions → search (fan-out) → facts (aggregate) → answer (fan-in)
* Trace: per-node I/O + tunables + usage

**Optimization**

* Target: `expand/aggregate/answer` nodes
* Loop: run→trace→critic→propose→score→replace
* Guardrails: schema validity, cost cap, latency budget

---

# Failure Modes & Guardrails

* **Prompt drift**: solved by versioned Tunables + diffing + serialization
* **Stringly-typed glue**: solved by DTOs + Instructor StructuredOutput
* **Brittle composition**: solved by explicit children/replace API
* **Overfitting**: dev split gating, cross-validation, diversity penalties
* **Non-determinism**: set seeds, n-best with vote/refine, temperature discipline
* **Latency/cost spikes**: batch, early exit on low-confidence, node-level budgets
* **Schema breakage**: strict Signature/Schema hashing; refuse to run on mismatch

---

# Engineering Patterns

* **Composite + Visitor** for modules & optimization
* **Immutable updates** for reproducibility
* **Observer** (Tracer), **Strategy** (Optimizer/Critic), **Factory** (Candidates)
* **Fiber-based concurrency** for candidate scoring; bounded pools
* **Config as code**: tunables & graph captured as typed configs

---

# DX & Workflow

* **Author** small leaf modules; expose tunables
* **Compose** DAG with explicit child names
* **Run** with `InMemoryTracer` to debug I/O quickly
* **Optimize** per-node or whole-graph; persist compiled variant
* **Ship** compiled program; rollback by swapping serialized configs

---

# North Star Heuristics (mental anchors)

* “**PyTorch autograd vibes**”: traces = tape; critics = gradients; replace nodes = parameter update
* “**DSPy discipline**”: signatures first; swap strategies, keep contracts
* “**Text as parameters**”: prompts/examples are knobs; make them addressable, serializable, testable
* “**No magic**”: explicit wiring, path-based introspection, typed surfaces only

---

# Minimal Interfaces (remember)

* `Signature`, `Module`, `Prediction`, `Tunable`, `Tracer`, `Metric`, `Optimizer`, `Critic`, `ModuleWalk`, `ProgramSerializer`
* Plus InstructorPHP: `Inference`, `StructuredOutput`, `Signature`, `Schema`, `Example`

This is the compact map—enough to align a senior engineer and start cutting clean, production-ready code.
