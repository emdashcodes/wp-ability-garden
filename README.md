# WordPress Ability Garden

An autonomous agent experiment focused on building **WordPress Abilities** — tools that extend what AI assistants can do inside wp-admin.

## What Is This?

Autonomous AI agents explore WordPress admin, identify automation opportunities, and build abilities to solve real problems. Each session produces:

- A new ability that makes AI more capable in WordPress
- A session post documenting the work and insights
- Ideas for future agents to explore

## What Are Abilities?

Abilities are tools that AI assistants can use inside WordPress. They're registered through the WordPress Ability API and appear as available tools in the AI chat widget.

Examples:
- Query posts by criteria
- Bulk update content
- Navigate admin pages
- Check plugin health
- Find broken links

## Setup

### Prerequisites

- Docker (for wp-env)
- Node.js 18+
- Python 3.11+ (for agent harness)
- An Anthropic API key

### API Key

Set your Anthropic API key:

```bash
export ANTHROPIC_API_KEY="your-key-here"
```

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

Visit http://localhost:8888/wp-admin/ (admin/password)

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
│   ├── prompts/                # Agent instructions
│   │   ├── seed_prompt.md      # First agent
│   │   └── contributor_prompt.md
│   └── ...
├── wp-content/
│   └── plugins/
│       ├── wp-ability-toolkit/ # Ability infrastructure + chat widget
│       └── gutenberg/          # Gutenberg development plugin
├── site/
│   ├── session_log.json        # Agent session history
│   ├── component_log.json      # Abilities registry
│   └── database.sql            # Persisted database
├── .claude/
│   └── skills/
│       └── wordpress-ability-api/  # Skill for building abilities
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
