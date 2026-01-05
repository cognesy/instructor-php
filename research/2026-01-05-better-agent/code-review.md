# Better Agent (PHP8) - Notes (2026-01-05)

## Scope
- AGENT.md for intended behavior and API.
- Core implementation under `packages/addons/src/Agent/`.
- Compare with “mini Claude Code” lessons (v0–v4): minimal loop, tool-first, explicit planning, subagents for context isolation, skills for domain knowledge, cache-aware context handling.

## Current Architecture (quick map)
- `Agent` orchestrates steps using a driver + tool executor + state processors. (`packages/addons/src/Agent/Agent.php`)
- `ToolCallingDriver` handles tool-calling inference and tool execution. (`packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php`)
- `ReActDriver` is an alternative structured-output driver. (`packages/addons/src/Agent/Drivers/ReAct/ReActDriver.php`)
- `ToolExecutor` executes tool calls and formats tool result messages. (`packages/addons/src/Agent/ToolExecutor.php`, `packages/addons/src/Agent/Drivers/ToolCalling/ToolExecutionFormatter.php`)
- Task planning exists via `TodoWriteTool` + `PersistTasksProcessor`. (`packages/addons/src/Agent/Extras/Tasks/*`)
- Subagents via `SpawnSubagentTool` + `AgentSpec`/`AgentRegistry`. (`packages/addons/src/Agent/Tools/Subagent/SpawnSubagentTool.php`, `packages/addons/src/Agent/Agents/*`)
- Skills via `LoadSkillTool` + `SkillLibrary`. (`packages/addons/src/Agent/Skills/*`)

## Gaps vs “mini Claude Code” lessons

### 1) Skill loading & cache-preservation
- **Issue**: `SpawnSubagentTool::createInitialState()` preloads full skill content into the *system prompt*. This breaks prompt-cache benefits and violates the “skills are tool_result, not system prompt” guidance. (`packages/addons/src/Agent/Tools/Subagent/SpawnSubagentTool.php`)
- **Issue**: Skill library expects `skills/*.md` instead of `skills/<name>/SKILL.md`, and resource discovery doesn’t match the SKILL.md directory convention. (`packages/addons/src/Agent/Skills/SkillLibrary.php`)

### 2) Skill metadata vs content (progressive disclosure)
- **Issue**: There is no first-class, automatic “skill list metadata” injection into the system prompt. The model can only discover skills by calling `load_skill` with `list_skills`. Mini Claude Code loads only metadata at startup and full content on-demand.

### 3) Subagent context isolation quality
- **Issue**: `SpawnSubagentTool::extractResponse()` only returns the *first line* of the subagent output. This is too lossy and not aligned with “return a concise summary” behavior in mini v3. (`packages/addons/src/Agent/Tools/Subagent/SpawnSubagentTool.php`)

### 4) Explicit planning prompts
- **Issue**: TodoWrite is implemented, but there’s no gentle reminder injection (initial and periodic) to encourage usage, unlike mini v2. (`packages/addons/src/Agent/Extras/Tasks/*`)

### 5) Tool set minimization and default UX
- **Issue**: Default builder config (maxSteps=3, maxTokens=8192) is very tight; may undercut multi-step tool loops which are core to “model as agent.” (`packages/addons/src/Agent/AgentBuilder.php`)
- **Issue**: `LlmQueryTool` creates a second LLM call path (tool inside tool). Mini approach recommends the main loop as the “brain,” using tools for external actions only. (`packages/addons/src/Agent/Tools/LlmQueryTool.php`)

### 6) Stop conditions vs “model decides when to stop”
- **Issue**: Continuation criteria include `ErrorPresenceCheck` and `FinishReasonCheck` in addition to tool-call presence. This can lead to early termination that isn’t purely “no tool calls => done.” (`packages/addons/src/Agent/AgentBuilder.php`, `packages/addons/src/Agent/Continuation/ToolCallPresenceCheck.php`)

### 7) Safety defaults for bash
- **Observation**: Bash is sandboxed but defaults to `withNetwork(true)` and no explicit dangerous-command blocking. Mini v1 uses explicit denylist. This is likely okay for advanced use, but “safe by default” could be tightened. (`packages/addons/src/Agent/Tools/BashTool.php`)

## Recommendations (actionable)

### A) Skills: align with SKILL.md + tool_result injection
1. **Support folder-based SKILL.md**
   - Scan `skills/*/SKILL.md` and `skills/*/resources/*` as per mini v4 convention.
   - Keep metadata (name, description) loaded at startup; load full body on demand.
   - Update `SkillLibrary::scanDirectory()` and `findResources()` to respect this layout. (`packages/addons/src/Agent/Skills/SkillLibrary.php`)

2. **Inject skill content as tool result, not system prompt**
   - Remove preloading skills into subagent system prompt.
   - Instead, pass a tool-result message at subagent start OR allow subagent to call `load_skill` based on metadata list.
   - Consider a `SkillLoaderTool` that returns `<skill-loaded>` wrapper to make the content explicit for the model.

3. **Add skill metadata to default system prompt**
   - Add a processor (or builder default) that appends “Available skills” metadata to the system prompt once per run. Avoid changing it per step to keep cache stable.

### B) Subagents: return concise summary, not first line
- Replace first-line truncation with a short, capped summary (e.g., first 5–10 lines or 1–2k chars).
- Keep the “summary only” constraint in the subagent system prompt to enforce brevity.
- This retains context isolation while preserving useful detail. (`packages/addons/src/Agent/Tools/Subagent/SpawnSubagentTool.php`)

### C) Planning nudges
- Implement v2-style reminders:
  - Add initial reminder in the first user message if `TodoWrite` is enabled.
  - Add periodic reminders (e.g., after N steps without todo updates).
- This can be done via a new state processor or a small “reminder injection” decorator. (`packages/addons/src/Agent/Extras/Tasks/*`)

### D) Simpler default loop + limits
- Raise default `maxSteps` and `maxTokens` to allow multi-step tasks (mini agents commonly rely on 10–30 steps).
- Consider splitting “hard limits” (safety) from “soft guidance” (prompt/metadata) so the model has room to complete the loop.

### E) Tool set discipline
- Encourage minimal tool sets by default (bash + read/write/edit + todo + skills + subagents).
- De-emphasize `LlmQueryTool` in templates; it’s easy to create redundant “LLM inside LLM” loops that add cost and latency. Provide it only for special workflows.

### F) Cache-awareness
- Make “append-only messages” a first-class design goal (already true for `AppendStepMessages`).
- Ensure system prompt remains stable across steps; avoid dynamic prompt injection per step.
- For subagents, prefer skill injection via tool results, not system prompt edits.

## Potential quick wins
- Adjust subagent response extraction to preserve multi-line summary.
- Add SKILL.md scanning support.
- Add “available skills” metadata to default system prompt once.
- Add Todo reminder injection.
- Increase default maxSteps/maxTokens in `AgentBuilder`.

## Files referenced
- `packages/addons/src/Agent/Agent.php`
- `packages/addons/src/Agent/AgentBuilder.php`
- `packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php`
- `packages/addons/src/Agent/Drivers/ReAct/ReActDriver.php`
- `packages/addons/src/Agent/Tools/Subagent/SpawnSubagentTool.php`
- `packages/addons/src/Agent/Skills/SkillLibrary.php`
- `packages/addons/src/Agent/Skills/LoadSkillTool.php`
- `packages/addons/src/Agent/Extras/Tasks/TodoWriteTool.php`
- `packages/addons/src/Agent/Extras/Tasks/PersistTasksProcessor.php`
- `packages/addons/src/Agent/Tools/BashTool.php`
- `packages/addons/src/StepByStep/StateProcessing/Processors/AppendStepMessages.php`
