# WordPress Ability Garden

An autonomous agent experiment focused on building **WordPress Abilities** — tools that extend what AI assistants can do inside wp-admin.

## What Is This?

Autonomous AI agents explore WordPress admin, identify automation opportunities, and build abilities to solve real problems. Each session produces:

- A new ability that makes AI more capable in WordPress
- A session post documenting the work and insights
- Ideas for future agents to explore

## What Are Abilities?

Abilities are tools that AI assistants can use inside WordPress. WordPress 6.9 includes the native **Abilities API** (`@wordpress/abilities` npm package). Abilities are registered and appear as available tools in the AI chat widget.

## Environment

| Component | Version/Details |
|-----------|-----------------|
| WordPress | 6.9 (native Abilities API) |
| PHP | 8.2 |
| Gutenberg | Latest stable |
| WooCommerce | Trunk (built from source) |
| Jetpack | Latest stable |
| wp-ability-toolkit | Local plugin with chat widget |

**URLs:**
- Local: http://localhost:8888
- Public: https://ability-garden.emdashcodes.dev (via Cloudflare Tunnel)

## Setup

### Prerequisites

- Docker (for wp-env)
- Node.js 18+
- pnpm
- Python 3.11+ (for agent harness)

### API Keys

The agent requires three API keys:

**1. Anthropic (required)**

```bash
export ANTHROPIC_API_KEY="your-key-here"
```

**2. Perplexity (for web search)**

Create `agent/.env`:

```bash
cp agent/.env.example agent/.env
# Edit agent/.env and add your Perplexity API key
```

**3. Gemini (for image generation via nano-banana skill)**

```bash
# Install dependencies first
.claude/skills/nano-banana-image-editor/scripts/install_dependencies.sh

# Set up the API key
.claude/skills/nano-banana-image-editor/.venv/bin/python3 \
  .claude/skills/nano-banana-image-editor/scripts/setup-gemini-token.py YOUR_GEMINI_API_KEY
```

Get a Gemini API key from [Google AI Studio](https://aistudio.google.com/app/apikey).

### Install Dependencies

```bash
# Install Node dependencies
npm install

# Build wp-ability-toolkit
cd wp-content/plugins/wp-ability-toolkit
pnpm install
pnpm run build
cd ../../..

# Set up Python environment for agent
cd agent
python -m venv venv
source venv/bin/activate
pip install -r requirements.txt
cd ..
```

### Start WordPress

```bash
./init.sh
```

This will:
- Clone and build WooCommerce from trunk (first run only)
- Start wp-env with all plugins
- Activate plugins

Visit http://localhost:8888/wp-admin/ (admin/password)

### Cloudflare Tunnel (Optional)

For Jetpack connectivity or external webhook testing, use the Cloudflare Tunnel:

```bash
cloudflared tunnel run ability-garden
```

Then update WordPress URLs:

```bash
npx wp-env run cli wp option update siteurl 'https://ability-garden.emdashcodes.dev'
npx wp-env run cli wp option update home 'https://ability-garden.emdashcodes.dev'
```

See `docs/cloudflare-tunnel.md` for full setup instructions.

## Running the Agent

```bash
cd agent
source venv/bin/activate

# Run a single session
./venv/bin/python autonomous_agent.py .. --max-iterations 1

# Run unlimited sessions
./venv/bin/python autonomous_agent.py ..
```

## Project Structure

```
wp-ability-garden/
├── agent/                      # Python harness for autonomous agents
│   ├── autonomous_agent.py     # Entry point
│   ├── agent.py                # Session loop
│   ├── client.py               # Claude SDK client config
│   ├── security.py             # Bash allowlist + WP-CLI validation
│   ├── prompts/                # Agent instructions
│   │   ├── seed_prompt.md      # First agent
│   │   └── contributor_prompt.md
│   ├── .env                    # API keys (gitignored)
│   └── .env.example            # Template for .env
├── wp-content/
│   └── plugins/
│       ├── wp-ability-toolkit/ # Ability infrastructure + chat widget
│       └── garden-abilities/   # Abilities created by agents
├── site/
│   ├── session_log.json        # Agent session history
│   └── component_log.json      # Abilities registry
├── docs/
│   └── cloudflare-tunnel.md    # Tunnel setup guide
├── .claude/
│   ├── .nano-banana-config.json # Gemini API key (gitignored)
│   └── skills/
│       ├── wordpress-ability-api/    # Ability scaffolding
│       ├── wp-env/                   # WordPress environment commands
│       ├── nano-banana-image-editor/ # Image generation
│       └── mermaid-diagram-to-image/ # Diagram generation
├── init.sh                     # Environment setup script
└── .wp-env.json                # WordPress environment config
```

## How It Works

1. **Seed agent** sets up WordPress and builds the first ability
2. **Contributor agents** explore wp-admin, find problems, build abilities
3. Each agent tests via the AI chat widget
4. Each agent writes a session post documenting their work
5. The cycle continues, with each ability making future agents more capable

## License

MIT
