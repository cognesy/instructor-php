Feature: Custom Slash Command
Invocation: User or Agent
Core Purpose: Reusable prompts for single-shot tasks.
Context: Shared / Main
Best For: Standardizing PRs, running tests, simple refactors.

Feature: Sub-Agent
Invocation: Agent (Delegation)
Core Purpose: Handle complex, multi-step tasks autonomously.
Context: Clean / Isolated
Best For: TDD workflows, security audits, root cause analysis.

Feature: Agent Skill
Invocation: Agent (Automatic)
Core Purpose: Provide on-demand knowledge and deterministic tools.
Context: Progressive Disclosure
Best For: API docs, style guides, complex file manipulations.

Feature: Lifecycle Hook
Invocation: Event-driven
Core Purpose: Enforce rules via deterministic automation.
Context: External / Event data
Best For: Code formatting, blocking commits, logging tool usage.

Category: Skills
Model: Parent model
Context Window: Stays in the main context window; can follow up easily; progressively disclosed.
Activation: Model invoked (whereas slash commands are user invoked).
Parallelism: None.
Conclusion: Simple, lightweight tasks, template-driven.
Examples: PR Formatter, newsletter creation, EXIF data extractor, standardized output with examples.

Category: Subagents
Model: Custom model or parent model
Context Window: Disposable, isolated context window; all context in one file.
Activation: Model invoked or user activated.
Parallelism: Can chain or run multiple in parallel.
Conclusion: Complex, multi-step work with deep context and independent reasoning.
Examples: Code reviewer (deep context, can forget previous context), landing page copy (each with distinct memory), problem-solver.
