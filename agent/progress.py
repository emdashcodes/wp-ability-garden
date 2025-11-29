"""
Progress Display
================

Functions for displaying progress information to the console.
"""

import json
from pathlib import Path


def print_session_header(iteration: int, is_seed: bool, project_dir: Path = None) -> None:
    """Print a header for the current session."""
    session_type = "SEED AGENT" if is_seed else "CONTRIBUTOR AGENT"

    # Get actual session number from log
    session_num = iteration
    if project_dir:
        session_log = project_dir / "site" / "session_log.json"
        if session_log.exists():
            data = json.loads(session_log.read_text())
            sessions = data.get("sessions", [])
            session_num = len(sessions) + 1

    print(f"\n{'=' * 70}")
    print(f"  SESSION {session_num}: {session_type}")
    print("=" * 70 + "\n")


def print_progress_summary(project_dir: Path) -> None:
    """Print a summary of project progress."""
    # Component count
    components = []
    component_log = project_dir / "site" / "component_log.json"
    if component_log.exists():
        data = json.loads(component_log.read_text())
        components = data.get("abilities", [])

        # Count by type
        by_type = {}
        for c in components:
            ctype = c.get("type", "unknown")
            by_type[ctype] = by_type.get(ctype, 0) + 1

        type_summary = ", ".join(f"{count} {t}{'s' if count > 1 else ''}" for t, count in by_type.items())
        print(f"\nComponents: {len(components)} total ({type_summary})")
    else:
        print("\nNo components yet")

    # Session count
    session_log = project_dir / "site" / "session_log.json"
    if session_log.exists():
        data = json.loads(session_log.read_text())
        sessions = data.get("sessions", [])
        print(f"Sessions: {len(sessions)}")
    else:
        print("Sessions: 0")

    # Status message
    component_count = len(components)
    if component_count == 0:
        print("Status: Fresh garden, ready to plant!")
    elif component_count < 5:
        print("Status: Garden is sprouting")
    elif component_count < 10:
        print("Status: Garden is growing nicely")
    else:
        print("Status: Garden is flourishing!")
