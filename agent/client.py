"""
Claude SDK Client Configuration
===============================

Functions for creating and configuring the Claude Agent SDK client
for WordPress development.
"""

import json
import os
from pathlib import Path

from claude_agent_sdk import ClaudeAgentOptions, ClaudeSDKClient
from claude_agent_sdk.types import HookMatcher

from security import bash_security_hook


# =============================================================================
# Digital Garden: AI learning about AI, WordPress, and how they work together
# =============================================================================
PROJECT_DESCRIPTION = """You are part of the Digital Garden — a self-documenting WordPress site built by AI agents.

The goal: Learn about AI, learn about yourself, learn about WordPress, and discover how the two can work together. Document everything. Build tools that make the next agent smarter than the last.

This is a digital garden:
- Content is interconnected through links
- Notes have growth stages: seedling (draft) → growing (reviewed) → evergreen (fact-checked)
- Visitors explore and discover rather than reading chronologically

Topics: AI agents, Claude Agent SDK, WordPress development, the Ability API.

IMPORTANT - Research & Citations:
You MUST research and cite sources when writing content. Use WebSearch, WebFetch, and mcp__perplexity-mcp__perplexity_search_web to verify facts. Include citations as inline links — readers should see your sources. Content cannot reach evergreen status without verified, cited claims.

Every agent writes a session post documenting what they built.

IMPORTANT - Skills:
You MUST activate relevant skills before starting work. Use Skill("skill-name") to load them.
At minimum, always activate wp-env. Activate others based on your task.

Key files:
- site/site-vision.md: Garden philosophy
- site/component_log.json: Themes, plugins, blocks
- site/session_log.json: Agent session history
- claude-progress.txt: Notes from previous agents

Constraints:
- Follow WordPress Coding Standards (WPCS)
- Use wp-env for the development environment
- Escape all output, sanitize all input
- Test with WP-CLI and Puppeteer before finishing
- Never leave the site in a broken state
"""
# =============================================================================


# Puppeteer MCP tools for browser automation
PUPPETEER_TOOLS = [
    "mcp__puppeteer__puppeteer_navigate",
    "mcp__puppeteer__puppeteer_screenshot",
    "mcp__puppeteer__puppeteer_click",
    "mcp__puppeteer__puppeteer_fill",
    "mcp__puppeteer__puppeteer_select",
    "mcp__puppeteer__puppeteer_hover",
    "mcp__puppeteer__puppeteer_evaluate",
]

# Perplexity MCP tools for AI-powered web search
PERPLEXITY_TOOLS = [
    "mcp__perplexity-mcp__perplexity_search_web",
]

# Built-in tools
BUILTIN_TOOLS = [
    "Read",
    "Write",
    "Edit",
    "Glob",
    "Grep",
    "Bash",
    "Skill",  # Enable project Skills (.claude/skills/)
    "WebFetch",  # Fetch and analyze web content
    "WebSearch",  # Search the web for research/validation
]


def create_client(project_dir: Path, model: str) -> ClaudeSDKClient:
    """
    Create a Claude Agent SDK client with WordPress-appropriate security.

    Args:
        project_dir: Directory for the project
        model: Claude model to use

    Returns:
        Configured ClaudeSDKClient

    Security layers:
    1. Sandbox - OS-level bash command isolation
    2. Permissions - File operations restricted to project_dir
    3. Security hooks - Bash commands validated (see security.py)
    4. WP-CLI validation - Dangerous WordPress commands blocked
    """
    security_settings = {
        "sandbox": {"enabled": True, "autoAllowBashIfSandboxed": True},
        "permissions": {
            "defaultMode": "acceptEdits",
            "allow": [
                "Read(./**)",
                "Write(./**)",
                "Edit(./**)",
                "Glob(./**)",
                "Grep(./**)",
                "Bash(*)",
                "Skill(*)",
                *PUPPETEER_TOOLS,
            ],
        },
    }

    settings_file = project_dir / ".claude_settings.json"
    with open(settings_file, "w") as f:
        json.dump(security_settings, f, indent=2)

    return ClaudeSDKClient(
        options=ClaudeAgentOptions(
            model=model,
            system_prompt={
                "type": "preset",
                "preset": "claude_code",
                "append": PROJECT_DESCRIPTION,
            },
            setting_sources=["project"],
            allowed_tools=[
                *BUILTIN_TOOLS,
                *PUPPETEER_TOOLS,
                *PERPLEXITY_TOOLS,
            ],
            mcp_servers={
                "puppeteer": {
                    "command": "npx",
                    "args": ["puppeteer-mcp-server"],
                    "env": {
                        "NODE_OPTIONS": "--max-old-space-size=4096",
                    },
                },
                "perplexity-mcp": {
                    "command": "uvx",
                    "args": ["perplexity-mcp"],
                    "env": {
                        "PERPLEXITY_API_KEY": os.environ.get("PERPLEXITY_API_KEY", ""),
                    },
                },
            },
            hooks={
                "PreToolUse": [
                    HookMatcher(matcher="Bash", hooks=[bash_security_hook]),
                ],
            },
            max_turns=1000,
            cwd=str(project_dir.resolve()),
            settings=str(settings_file.resolve()),
        )
    )
