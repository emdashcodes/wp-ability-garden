#!/usr/bin/env python3
"""
Digital Garden Agent
====================

A WordPress digital garden built by autonomous agents.

Topics: AI, Claude Agent SDK, WordPress Development

Example Usage:
    python autonomous_agent.py ~/Dev/wp-claude-agent-garden
    python autonomous_agent.py ~/Dev/wp-claude-agent-garden --max-iterations 5
"""

import argparse
import asyncio
from pathlib import Path

from dotenv import load_dotenv

# Load .env file from agent directory
load_dotenv(Path(__file__).parent / ".env")

from agent import run_autonomous_agent


DEFAULT_MODEL = "claude-opus-4-5-20251101"


def parse_args() -> argparse.Namespace:
    """Parse command line arguments."""
    parser = argparse.ArgumentParser(
        description="Digital Garden: WordPress site built by autonomous agents",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Start a new garden (seed agent sets up WordPress)
  python autonomous_agent.py ~/Dev/wp-claude-agent-garden

  # Continue an existing garden (contributor agents add features)
  python autonomous_agent.py ~/Dev/wp-claude-agent-garden

  # Use a different model
  python autonomous_agent.py ~/Dev/wp-claude-agent-garden --model claude-sonnet-4-5-20250929

  # Limit iterations for testing
  python autonomous_agent.py ~/Dev/wp-claude-agent-garden --max-iterations 3

Prerequisites:
  - Docker (for wp-env)
  - Node.js 18+ (for wp-env and block development)
  - Claude Code CLI (authenticated)

The garden will be available at https://ability-garden.emdashcodes.dev once running.
        """,
    )

    parser.add_argument(
        "project_dir",
        type=Path,
        nargs="?",
        default=Path.cwd(),
        help="Project directory (default: current directory)",
    )

    parser.add_argument(
        "--max-iterations",
        type=int,
        default=None,
        help="Maximum number of agent iterations (default: unlimited)",
    )

    parser.add_argument(
        "--model",
        type=str,
        default=DEFAULT_MODEL,
        help=f"Claude model to use (default: {DEFAULT_MODEL})",
    )

    return parser.parse_args()


def main() -> None:
    """Main entry point."""
    args = parse_args()

    project_dir = args.project_dir.resolve()
    project_dir.mkdir(parents=True, exist_ok=True)

    try:
        asyncio.run(
            run_autonomous_agent(
                project_dir=project_dir,
                model=args.model,
                max_iterations=args.max_iterations,
            )
        )
    except KeyboardInterrupt:
        print("\n\nInterrupted by user")
        print("To resume, run the same command again")
        print("\nTo view the garden:")
        print(f"  cd {project_dir}")
        print("  ./init.sh")
        print("  # Visit https://ability-garden.emdashcodes.dev")
    except Exception as e:
        print(f"\nFatal error: {e}")
        raise


if __name__ == "__main__":
    main()
