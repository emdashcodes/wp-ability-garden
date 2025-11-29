# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## Project Overview

**WordPress Ability Garden** is an autonomous agent experiment focused on building WordPress Abilities — tools that extend what AI assistants can do inside wp-admin.

The project has two parts:
- **`agent/`**: Python harness that orchestrates autonomous agents using the Claude Agent SDK
- **`wp-content/plugins/wp-ability-toolkit/`**: The ability infrastructure and chat widget

## What Are Abilities?

Abilities are tools that AI assistants can use inside WordPress. They bridge the gap between what an AI can understand and what WordPress can do.

- **Server-side abilities** (PHP) — Query data, modify content, manage settings
- **Client-side abilities** (TypeScript) — Navigate pages, interact with UI, handle browser state

## Commands

### Running the Agent

```bash
cd agent
source venv/bin/activate
./venv/bin/python autonomous_agent.py ..              # Run unlimited
./venv/bin/python autonomous_agent.py .. --max-iterations 3
./venv/bin/python autonomous_agent.py .. --model claude-sonnet-4-5-20250929
```

### WordPress Environment

```bash
./init.sh                    # Start wp-env and activate plugins
npx wp-env stop              # Stop wp-env
npx wp-env run cli wp ...    # Run WP-CLI commands
```

### Building wp-ability-toolkit

```bash
cd wp-content/plugins/wp-ability-toolkit
pnpm install
pnpm run build
```

## Required Skill

**Always activate before ability work:**

```
Skill("wordpress-ability-api")
```

This skill provides:
- Scaffolding scripts for new abilities
- Templates for server and client abilities
- Validation scripts
- Best practices and patterns

## Existing Abilities

Check `site/component_log.json` for the current list of abilities.

Built-in abilities in wp-ability-toolkit:
- `wp-ability-toolkit/create-ability` — Helps brainstorm new abilities
- `wp-ability-toolkit/content-review` — Reviews content for promotion
- `wp-ability-toolkit/navigate` — Navigates to admin pages
- `wp-ability-toolkit/reload` — Reloads the current page

## Testing Approach

Use Puppeteer to test abilities via the chat widget:

1. Navigate to wp-admin
2. Open the chat widget
3. Ask the AI to use the ability
4. Verify the result

```
mcp__puppeteer__puppeteer_navigate → http://localhost:8888/wp-admin/
mcp__puppeteer__puppeteer_screenshot → Capture state
mcp__puppeteer__puppeteer_click → Interact with chat
mcp__puppeteer__puppeteer_type → Send messages
```

## Adding a New Ability

1. Activate the skill: `Skill("wordpress-ability-api")`
2. Use the scaffolding script or templates
3. Register in wp-ability-toolkit
4. Test via the chat widget
5. Add to `site/component_log.json`
6. Document in your session post

## Tracking Files

| File | Purpose |
|------|---------|
| `site/component_log.json` | Registry of abilities built |
| `site/session_log.json` | Timeline of agent sessions |
| `site/database.sql` | Exported database (persisted between sessions) |

## WordPress Coding Standards

For PHP abilities:
- Escape output: `esc_html()`, `esc_attr()`, `esc_url()`
- Sanitize input: `sanitize_text_field()`, `wp_kses_post()`
- Use proper capability checks
- Prefix functions appropriately

## Agent Harness Files

| File | Purpose |
|------|---------|
| `autonomous_agent.py` | Entry point, CLI argument parsing |
| `agent.py` | Session loop, `run_autonomous_agent()` |
| `client.py` | SDK client config with security hooks + MCP |
| `security.py` | Bash allowlist + WP-CLI command validation |
| `prompts.py` | Loads prompts from `prompts/` directory |
| `progress.py` | Console output helpers |

## Important Notes

- **One ability per session** — Stay focused
- **Test via chat widget** — Verify abilities work
- **Document everything** — Write session posts
- **Explore wp-admin** — Find real problems to solve
