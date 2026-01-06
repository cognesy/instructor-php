# Agents in Laravel: Library Work (Instructor PHP)

Goal: the minimal work required inside this library so Laravel apps can run agents quickly and safely.

---

## 1) Public API Surface (Stable)

- Keep `AgentBuilder::base()` as the primary entrypoint.
- Ensure `Cognesy\Addons\Agent\Core\Data\AgentState` is stable and documented.
- Keep capabilities as the main extension mechanism (UseBash, UseFileTools, UseTaskPlanning, UseSkills).

---

## 2) Event Hooks (Observability)

- Guarantee `EventBus` events for:
  - step completed
  - tool called / completed
  - agent finished / failed
- Keep payloads serializable (array-safe).

---

## 3) Persistence Helpers (Optional, Minimal)

Provide helpers to serialize/deserialize state:

- `AgentState` → array
- array → `AgentState`

These should be in a small utility class so Laravel apps don't re‑invent it.

---

## 4) Execution Policies (Safe Defaults)

Ensure execution policies are:

- read‑only by default for file tools
- network disabled by default for bash
- easy to configure per agent

Expose a clean way to pass `ExecutionPolicy` via capabilities.

---

## 5) Tool Naming / Discovery (Optional)

If adopting namespaced tools and discovery:

- implement canonical tool names internally
- provide provider‑safe alias mapping (no dot names in schemas)
- expose `tool.list` and `tool.describe`

This can be deferred if it slows down Laravel integration.

---

## 6) Basic Docs for Laravel Users

Add a short Laravel section in the library docs:

- minimal job setup
- how to build an agent
- safe defaults for file & bash tools
- example event wiring

---

## Library Checklist

- [ ] `AgentState` serialization helper
- [ ] Event payloads stable and documented
- [ ] ExecutionPolicy examples
- [ ] Minimal Laravel snippet in docs
