# Use Codex with the Agents SDK

## Overview

Codex CLI can do far more than run ad-hoc tasks. By exposing the CLI as a [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) server and orchestrating it with the OpenAI Agents SDK, you can create deterministic, auditable workflows that scale from a single agent to a complete software delivery pipeline.

This guide walks through the same workflow showcased in the [OpenAI Cookbook](https://github.com/openai/openai-cookbook/blob/main/examples/codex/codex_mcp_agents_sdk/building_consistent_workflows_codex_cli_agents_sdk.ipynb). You will:

- launch Codex CLI as a long-running MCP server,
- build a focused single-agent workflow that produces a playable browser game, and
- orchestrate a multi-agent team with hand-offs, guardrails, and full traces you can review afterwards.

## Prerequisites

Before starting, make sure you have:

- [Codex CLI](/codex/cli) installed locally so `npx codex` can run.
- Python 3.10+ with `pip`.
- Node.js 18+ (required for `npx`).
- An OpenAI API key stored locally. You can create or manage keys in the [OpenAI dashboard](https://platform.openai.com/account/api-keys).

Create a working directory for the guide and add your API key to a `.env` file:

```bash
mkdir codex-workflows
cd codex-workflows
printf "OPENAI_API_KEY=sk-..." > .env
```

## Install dependencies

The Agents SDK handles orchestration across Codex, hand-offs, and traces. Install the latest SDK packages:

```bash
python -m venv .venv
source .venv/bin/activate
pip install --upgrade openai openai-agents python-dotenv
```

<DocsTip>
  Activating a virtual environment keeps the SDK dependencies isolated from the
  rest of your system.
</DocsTip>

## Initialize Codex CLI as a MCP server

Start by turning Codex CLI into a MCP server that the Agents SDK can call. The server exposes two tools—`codex()` to start a conversation and `codex-reply()` to continue one—and keeps Codex alive across multiple agent turns.

Create a file called `codex_mcp.py` and add the following:

```python
import asyncio

from agents import Agent, Runner
from agents.mcp import MCPServerStdio


async def main() -> None:
    async with MCPServerStdio(
        name="Codex CLI",
        params={
            "command": "npx",
            "args": ["-y", "codex", "mcp-server"],
        },
        client_session_timeout_seconds=360000,
    ) as codex_mcp_server:
        print("Codex MCP server started.")
        # More logic coming in the next sections.
        return


if __name__ == "__main__":
    asyncio.run(main())
```

Run the script once to verify that Codex launches successfully:

```bash
python codex_mcp.py
```

The script exits after printing `Codex MCP server started.`. In the next sections you will reuse the same MCP server inside richer workflows.

## Build a single-agent workflow

Let’s start with a scoped example that uses Codex MCP to ship a small browser game. The workflow relies on two agents:

1. **Game Designer** – writes a brief for the game.
2. **Game Developer** – implements the game by calling Codex MCP.

Update `codex_mcp.py` with the following code. It keeps the MCP server setup from above and adds both agents.

```python
import asyncio
import os

from dotenv import load_dotenv

from agents import Agent, Runner, set_default_openai_api
from agents.mcp import MCPServerStdio

load_dotenv(override=True)
set_default_openai_api(os.getenv("OPENAI_API_KEY"))


async def main() -> None:
    async with MCPServerStdio(
        name="Codex CLI",
        params={
            "command": "npx",
            "args": ["-y", "codex", "mcp-server"],
        },
        client_session_timeout_seconds=360000,
    ) as codex_mcp_server:
        developer_agent = Agent(
            name="Game Developer",
            instructions=(
                "You are an expert in building simple games using basic html + css + javascript with no dependencies. "
                "Save your work in a file called index.html in the current directory. "
                "Always call codex with \"approval-policy\": \"never\" and \"sandbox\": \"workspace-write\"."
            ),
            mcp_servers=[codex_mcp_server],
        )

        designer_agent = Agent(
            name="Game Designer",
            instructions=(
                "You are an indie game connoisseur. Come up with an idea for a single page html + css + javascript game that a developer could build in about 50 lines of code. "
                "Format your request as a 3 sentence design brief for a game developer and call the Game Developer coder with your idea."
            ),
            model="gpt-5",
            handoffs=[developer_agent],
        )

        await Runner.run(designer_agent, "Implement a fun new game!")


if __name__ == "__main__":
    asyncio.run(main())
```

Execute the script:

```bash
python codex_mcp.py
```

Codex will read the designer’s brief, create an `index.html` file, and write the full game to disk. Open the generated file in a browser to play the result. Every run produces a different design with unique gameplay twists and polish.

## Expand to a multi-agent workflow

Now turn the single-agent setup into an orchestrated, traceable workflow. The system adds:

- **Project Manager** – creates shared requirements, coordinates hand-offs, and enforces guardrails.
- **Designer**, **Frontend Developer**, **Backend Developer**, and **Tester** – each with scoped instructions and output folders.

Create a new file called `multi_agent_workflow.py`:

```python
import asyncio
import os

from dotenv import load_dotenv

from agents import (
    Agent,
    ModelSettings,
    Runner,
    WebSearchTool,
    set_default_openai_api,
)
from agents.extensions.handoff_prompt import RECOMMENDED_PROMPT_PREFIX
from agents.mcp import MCPServerStdio
from openai.types.shared import Reasoning

load_dotenv(override=True)
set_default_openai_api(os.getenv("OPENAI_API_KEY"))


async def main() -> None:
    async with MCPServerStdio(
        name="Codex CLI",
        params={"command": "npx", "args": ["-y", "codex", "mcp"]},
        client_session_timeout_seconds=360000,
    ) as codex_mcp_server:
        designer_agent = Agent(
            name="Designer",
            instructions=(
                f"""{RECOMMENDED_PROMPT_PREFIX}"""
                "You are the Designer.\n"
                "Your only source of truth is AGENT_TASKS.md and REQUIREMENTS.md from the Project Manager.\n"
                "Do not assume anything that is not written there.\n\n"
                "You may use the internet for additional guidance or research."
                "Deliverables (write to /design):\n"
                "- design_spec.md – a single page describing the UI/UX layout, main screens, and key visual notes as requested in AGENT_TASKS.md.\n"
                "- wireframe.md – a simple text or ASCII wireframe if specified.\n\n"
                "Keep the output short and implementation-friendly.\n"
                "When complete, handoff to the Project Manager with transfer_to_project_manager."
                "When creating files, call Codex MCP with {\"approval-policy\":\"never\",\"sandbox\":\"workspace-write\"}."
            ),
            model="gpt-5",
            tools=[WebSearchTool()],
            mcp_servers=[codex_mcp_server],
        )

        frontend_developer_agent = Agent(
            name="Frontend Developer",
            instructions=(
                f"""{RECOMMENDED_PROMPT_PREFIX}"""
                "You are the Frontend Developer.\n"
                "Read AGENT_TASKS.md and design_spec.md. Implement exactly what is described there.\n\n"
                "Deliverables (write to /frontend):\n"
                "- index.html – main page structure\n"
                "- styles.css or inline styles if specified\n"
                "- main.js or game.js if specified\n\n"
                "Follow the Designer’s DOM structure and any integration points given by the Project Manager.\n"
                "Do not add features or branding beyond the provided documents.\n\n"
                "When complete, handoff to the Project Manager with transfer_to_project_manager_agent."
                "When creating files, call Codex MCP with {\"approval-policy\":\"never\",\"sandbox\":\"workspace-write\"}."
            ),
            model="gpt-5",
            mcp_servers=[codex_mcp_server],
        )

        backend_developer_agent = Agent(
            name="Backend Developer",
            instructions=(
                f"""{RECOMMENDED_PROMPT_PREFIX}"""
                "You are the Backend Developer.\n"
                "Read AGENT_TASKS.md and REQUIREMENTS.md. Implement the backend endpoints described there.\n\n"
                "Deliverables (write to /backend):\n"
                "- package.json – include a start script if requested\n"
                "- server.js – implement the API endpoints and logic exactly as specified\n\n"
                "Keep the code as simple and readable as possible. No external database.\n\n"
                "When complete, handoff to the Project Manager with transfer_to_project_manager_agent."
                "When creating files, call Codex MCP with {\"approval-policy\":\"never\",\"sandbox\":\"workspace-write\"}."
            ),
            model="gpt-5",
            mcp_servers=[codex_mcp_server],
        )

        tester_agent = Agent(
            name="Tester",
            instructions=(
                f"""{RECOMMENDED_PROMPT_PREFIX}"""
                "You are the Tester.\n"
                "Read AGENT_TASKS.md and TEST.md. Verify that the outputs of the other roles meet the acceptance criteria.\n\n"
                "Deliverables (write to /tests):\n"
                "- TEST_PLAN.md – bullet list of manual checks or automated steps as requested\n"
                "- test.sh or a simple automated script if specified\n\n"
                "Keep it minimal and easy to run.\n\n"
                "When complete, handoff to the Project Manager with transfer_to_project_manager."
                "When creating files, call Codex MCP with {\"approval-policy\":\"never\",\"sandbox\":\"workspace-write\"}."
            ),
            model="gpt-5",
            mcp_servers=[codex_mcp_server],
        )

        project_manager_agent = Agent(
            name="Project Manager",
            instructions=(
                f"""{RECOMMENDED_PROMPT_PREFIX}"""
                """
                You are the Project Manager.

                Objective:
                Convert the input task list into three project-root files the team will execute against.

                Deliverables (write in project root):
                - REQUIREMENTS.md: concise summary of product goals, target users, key features, and constraints.
                - TEST.md: tasks with [Owner] tags (Designer, Frontend, Backend, Tester) and clear acceptance criteria.
                - AGENT_TASKS.md: one section per role containing:
                  - Project name
                  - Required deliverables (exact file names and purpose)
                  - Key technical notes and constraints

                Process:
                - Resolve ambiguities with minimal, reasonable assumptions. Be specific so each role can act without guessing.
                - Create files using Codex MCP with {"approval-policy":"never","sandbox":"workspace-write"}.
                - Do not create folders. Only create REQUIREMENTS.md, TEST.md, AGENT_TASKS.md.

                Handoffs (gated by required files):
                1) After the three files above are created, hand off to the Designer with transfer_to_designer_agent and include REQUIREMENTS.md and AGENT_TASKS.md.
                2) Wait for the Designer to produce /design/design_spec.md. Verify that file exists before proceeding.
                3) When design_spec.md exists, hand off in parallel to both:
                   - Frontend Developer with transfer_to_frontend_developer_agent (provide design_spec.md, REQUIREMENTS.md, AGENT_TASKS.md).
                   - Backend Developer with transfer_to_backend_developer_agent (provide REQUIREMENTS.md, AGENT_TASKS.md).
                4) Wait for Frontend to produce /frontend/index.html and Backend to produce /backend/server.js. Verify both files exist.
                5) When both exist, hand off to the Tester with transfer_to_tester_agent and provide all prior artifacts and outputs.
                6) Do not advance to the next handoff until the required files for that step are present. If something is missing, request the owning agent to supply it and re-check.

                PM Responsibilities:
                - Coordinate all roles, track file completion, and enforce the above gating checks.
                - Do NOT respond with status updates. Just handoff to the next agent until the project is complete.
                """
            ),
            model="gpt-5",
            model_settings=ModelSettings(
                reasoning=Reasoning(effort="medium"),
            ),
            handoffs=[designer_agent, frontend_developer_agent, backend_developer_agent, tester_agent],
            mcp_servers=[codex_mcp_server],
        )

        designer_agent.handoffs = [project_manager_agent]
        frontend_developer_agent.handoffs = [project_manager_agent]
        backend_developer_agent.handoffs = [project_manager_agent]
        tester_agent.handoffs = [project_manager_agent]

        task_list = """
Goal: Build a tiny browser game to showcase a multi-agent workflow.

High-level requirements:
- Single-screen game called "Bug Busters".
- Player clicks a moving bug to earn points.
- Game ends after 20 seconds and shows final score.
- Optional: submit score to a simple backend and display a top-10 leaderboard.

Roles:
- Designer: create a one-page UI/UX spec and basic wireframe.
- Frontend Developer: implement the page and game logic.
- Backend Developer: implement a minimal API (GET /health, GET/POST /scores).
- Tester: write a quick test plan and a simple script to verify core routes.

Constraints:
- No external database—memory storage is fine.
- Keep everything readable for beginners; no frameworks required.
- All outputs should be small files saved in clearly named folders.
"""

        result = await Runner.run(project_manager_agent, task_list, max_turns=30)
        print(result.final_output)


if __name__ == "__main__":
    asyncio.run(main())
```

Run the script and watch the generated files:

```bash
python multi_agent_workflow.py
ls -R
```

The project manager agent writes `REQUIREMENTS.md`, `TEST.md`, and `AGENT_TASKS.md`, then coordinates hand-offs across the designer, frontend, backend, and tester agents. Each agent writes scoped artifacts in its own folder before handing control back to the project manager.

## Trace the workflow

Codex automatically records traces that capture every prompt, tool call, and hand-off. After the multi-agent run completes, open the [Traces dashboard](https://platform.openai.com/trace) to inspect the execution timeline.

The high-level trace highlights how the project manager verifies hand-offs before moving forward. Click into individual steps to see prompts, Codex MCP calls, files written, and execution durations. These details make it easy to audit every hand-off and understand how the workflow evolved turn by turn.
These traces make it easy to debug workflow hiccups, audit agent behavior, and measure performance over time without requiring any additional instrumentation.

## Recap

In this guide you:

- exposed Codex CLI as an MCP server that stays alive across agent runs,
- built a single-agent workflow that deterministically produces a playable game, and
- orchestrated a multi-agent delivery pipeline with clear guardrails and artifacts for every role.

## Keep going

Here are a few ways to apply the same patterns to your own projects:

1. **Scale real-world rollouts** – Use MCP-powered hand-offs for large refactors or migrations where you need repeatable outputs and audit trails.
2. **Accelerate delivery without losing control** – Gate hand-offs on tests, required files, or trace reviews to keep quality high while parallelizing work.
3. **Integrate with existing tooling** – Connect the Agents SDK to Jira, GitHub, or CI/CD webhooks for closed-loop automation that still remains observable.

Once you are comfortable with the pattern, adapt the project manager’s instructions for your own repositories and ship Codex-backed workflows tailored to your team.