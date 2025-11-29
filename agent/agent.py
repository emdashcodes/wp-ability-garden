"""
Agent Session Logic
===================

Core agent interaction functions for the Digital Garden project.
"""

import asyncio
import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

from claude_agent_sdk import ClaudeSDKClient

from client import create_client
from progress import print_session_header, print_progress_summary
from prompts import get_seed_prompt, get_contributor_prompt


# Configuration
AUTO_CONTINUE_DELAY_SECONDS = 3
DB_BACKUP_PATH = "site/database.sql"


def cleanup_incomplete_sessions(project_dir: Path) -> None:
    """Remove incomplete session stubs (where agent never finished)."""
    session_log_path = project_dir / "site" / "session_log.json"
    if session_log_path.exists():
        data = json.loads(session_log_path.read_text())
        if data.get("sessions"):
            # Keep only sessions that have an agent_report (completed)
            data["sessions"] = [
                s for s in data["sessions"]
                if s.get("agent_report") is not None
            ]
            session_log_path.write_text(json.dumps(data, indent=2))


def get_next_session_id(project_dir: Path) -> int:
    """Get the next session ID from session_log.json."""
    # First clean up any incomplete sessions from interrupted runs
    cleanup_incomplete_sessions(project_dir)

    session_log_path = project_dir / "site" / "session_log.json"
    if session_log_path.exists():
        data = json.loads(session_log_path.read_text())
        if data.get("sessions"):
            return max(s["id"] for s in data["sessions"]) + 1
    return 1


def log_session_start(project_dir: Path, session_id: int, session_type: str) -> datetime:
    """Log the start of a session. Returns the start time."""
    start_time = datetime.now(timezone.utc)

    session_log_path = project_dir / "site" / "session_log.json"

    # Ensure site directory exists
    session_log_path.parent.mkdir(parents=True, exist_ok=True)

    if session_log_path.exists():
        data = json.loads(session_log_path.read_text())
    else:
        data = {"sessions": []}

    data["sessions"].append({
        "id": session_id,
        "started_at": start_time.isoformat(),
        "ended_at": None,
        "type": session_type,
        "duration_seconds": None,
        "agent_report": None
    })

    session_log_path.write_text(json.dumps(data, indent=2))
    return start_time


def log_session_end(project_dir: Path, session_id: int, start_time: datetime) -> None:
    """Log the end of a session with duration."""
    end_time = datetime.now(timezone.utc)
    duration = (end_time - start_time).total_seconds()

    session_log_path = project_dir / "site" / "session_log.json"
    if session_log_path.exists():
        data = json.loads(session_log_path.read_text())
        for session in data["sessions"]:
            if session["id"] == session_id:
                session["ended_at"] = end_time.isoformat()
                session["duration_seconds"] = int(duration)
                break
        session_log_path.write_text(json.dumps(data, indent=2))


def export_database(project_dir: Path) -> None:
    """Export WordPress database to SQL file for persistence."""
    backup_path = project_dir / DB_BACKUP_PATH
    backup_path.parent.mkdir(parents=True, exist_ok=True)

    print("Exporting database for persistence...")
    try:
        result = subprocess.run(
            ["npx", "wp-env", "run", "cli", "wp", "db", "export",
             f"/var/www/html/{DB_BACKUP_PATH}", "--add-drop-table"],
            cwd=project_dir,
            capture_output=True,
            text=True,
            timeout=60
        )
        if result.returncode == 0:
            print(f"   Database exported to {DB_BACKUP_PATH}")
        else:
            print(f"   Warning: Database export failed: {result.stderr[:200]}")
    except subprocess.TimeoutExpired:
        print("   Warning: Database export timed out")
    except Exception as e:
        print(f"   Warning: Database export error: {e}")


async def run_agent_session(
    client: ClaudeSDKClient,
    message: str,
    project_dir: Path,
) -> tuple[str, str]:
    """
    Run a single agent session using Claude Agent SDK.

    Returns:
        (status, response_text)
    """
    print("Sending prompt to Claude Agent SDK...\n")

    try:
        await client.query(message)

        response_text = ""
        async for msg in client.receive_response():
            msg_type = type(msg).__name__

            if msg_type == "AssistantMessage" and hasattr(msg, "content"):
                for block in msg.content:
                    block_type = type(block).__name__

                    if block_type == "TextBlock" and hasattr(block, "text"):
                        response_text += block.text
                        print(block.text, end="", flush=True)
                    elif block_type == "ToolUseBlock" and hasattr(block, "name"):
                        print(f"\n[Tool: {block.name}]", flush=True)
                        if hasattr(block, "input"):
                            input_str = str(block.input)
                            if len(input_str) > 200:
                                print(f"   Input: {input_str[:200]}...", flush=True)
                            else:
                                print(f"   Input: {input_str}", flush=True)

            elif msg_type == "UserMessage" and hasattr(msg, "content"):
                for block in msg.content:
                    block_type = type(block).__name__

                    if block_type == "ToolResultBlock":
                        result_content = getattr(block, "content", "")
                        is_error = getattr(block, "is_error", False)

                        if "blocked" in str(result_content).lower():
                            print(f"   [BLOCKED] {result_content}", flush=True)
                        elif is_error:
                            error_str = str(result_content)[:500]
                            print(f"   [Error] {error_str}", flush=True)
                        else:
                            print("   [Done]", flush=True)

        print("\n" + "-" * 70 + "\n")
        return "continue", response_text

    except Exception as e:
        print(f"Error during agent session: {e}")
        return "error", str(e)


async def run_autonomous_agent(
    project_dir: Path,
    model: str,
    max_iterations: Optional[int] = None,
) -> None:
    """
    Run the autonomous agent loop for the Digital Garden.

    Args:
        project_dir: Directory for the project
        model: Claude model to use
        max_iterations: Maximum number of iterations (None for unlimited)
    """
    print("\n" + "=" * 70)
    print("  DIGITAL GARDEN")
    print("  A WordPress site built by autonomous agents")
    print("=" * 70)
    print(f"\nProject directory: {project_dir}")
    print(f"Model: {model}")
    if max_iterations:
        print(f"Max iterations: {max_iterations}")
    else:
        print("Max iterations: Unlimited (will run until stopped)")
    print()

    # Check if this is a fresh start
    component_log = project_dir / "site" / "component_log.json"
    is_first_run = not component_log.exists()

    if is_first_run:
        print("Fresh garden - seed agent will establish the foundation")
        print()
        print("=" * 70)
        print("  NOTE: First session takes 15-30+ minutes!")
        print("  The seed agent is setting up WordPress, theme, and plugins.")
        print("  This may appear to hang - it's working. Watch for [Tool: ...] output.")
        print("=" * 70)
        print()
    else:
        print("Continuing existing garden")
        print_progress_summary(project_dir)

    iteration = 0

    while True:
        iteration += 1

        if max_iterations and iteration > max_iterations:
            print(f"\nReached max iterations ({max_iterations})")
            print("To continue, run the script again without --max-iterations")
            break

        session_type = "seed" if is_first_run else "contributor"
        session_id = get_next_session_id(project_dir)

        print_session_header(iteration, is_first_run, project_dir)

        start_time = log_session_start(project_dir, session_id, session_type)

        client = create_client(project_dir, model)

        if is_first_run:
            prompt = get_seed_prompt()
            is_first_run = False
        else:
            prompt = get_contributor_prompt()

        async with client:
            status, response = await run_agent_session(client, prompt, project_dir)

        log_session_end(project_dir, session_id, start_time)

        # Export database after each session for persistence
        export_database(project_dir)

        if status == "continue":
            print(f"\nAgent will auto-continue in {AUTO_CONTINUE_DELAY_SECONDS}s...")
            print_progress_summary(project_dir)
            await asyncio.sleep(AUTO_CONTINUE_DELAY_SECONDS)

        elif status == "error":
            print("\nSession encountered an error")
            print("Will retry with a fresh session...")
            await asyncio.sleep(AUTO_CONTINUE_DELAY_SECONDS)

        if max_iterations is None or iteration < max_iterations:
            print("\nPreparing next session...\n")
            await asyncio.sleep(1)

    print("\n" + "=" * 70)
    print("  SESSION COMPLETE")
    print("=" * 70)
    print(f"\nProject directory: {project_dir}")
    print_progress_summary(project_dir)

    print("\n" + "-" * 70)
    print("  TO VIEW THE GARDEN:")
    print("-" * 70)
    print(f"\n  cd {project_dir.resolve()}")
    print("  ./init.sh")
    print("  # Then visit http://localhost:8888")
    print("-" * 70)

    print("\nDone!")
