"""
Security Hooks for WordPress Agent
==================================

Pre-tool-use hooks that validate bash commands for security.
Uses an allowlist approach - only explicitly permitted commands can run.

Adapted for WordPress development with wp-env, WP-CLI, and PHP.
"""

import os
import shlex


# Allowed commands for WordPress development
ALLOWED_COMMANDS = {
    # File inspection
    "ls",
    "cat",
    "head",
    "tail",
    "wc",
    "grep",
    # File operations
    "cp",
    "mkdir",
    "chmod",  # For making scripts executable; validated separately
    "rm",  # For cleanup; needs validation
    "mv",  # For moving/renaming files
    "touch",  # For creating empty files
    # Directory
    "pwd",
    "cd",
    # PHP development
    "php",
    "composer",
    "phpcs",  # PHP CodeSniffer (WPCS)
    "phpcbf",  # PHP CodeSniffer fixer
    "phpunit",  # Testing
    # WordPress tooling
    "wp",  # WP-CLI
    "wp-env",  # WordPress environment
    "npx",  # For running wp-env and other tools
    # Node.js (for block development)
    "npm",
    "node",
    # Python (for testing/scripting)
    "python",
    "python3",
    # Version control
    "git",
    # Process management
    "ps",
    "lsof",
    "sleep",
    "pkill",  # For killing dev servers; validated separately
    # Script execution
    "init.sh",  # Init scripts; validated separately
    # Docker (wp-env uses Docker)
    "docker",
    "docker-compose",
    # Additional utilities
    "echo",
    "which",
    "env",
    "curl",
    "wget",
    "source",
    "bash",
}

# Commands that need additional validation
COMMANDS_NEEDING_EXTRA_VALIDATION = {"pkill", "chmod", "init.sh", "rm", "wp"}


def split_command_segments(command_string: str) -> list[str]:
    """Split a compound command into individual command segments."""
    import re
    segments = re.split(r"\s*(?:&&|\|\|)\s*", command_string)
    result = []
    for segment in segments:
        sub_segments = re.split(r'(?<!["\'])\s*;\s*(?!["\'])', segment)
        for sub in sub_segments:
            sub = sub.strip()
            if sub:
                result.append(sub)
    return result


def extract_commands(command_string: str) -> list[str]:
    """Extract command names from a shell command string."""
    commands = []
    import re
    segments = re.split(r'(?<!["\'])\s*;\s*(?!["\'])', command_string)

    for segment in segments:
        segment = segment.strip()
        if not segment:
            continue

        try:
            tokens = shlex.split(segment)
        except ValueError:
            # If shlex fails (complex quotes), try simple split on pipes
            # and extract first word of each segment
            pipe_segments = segment.split("|")
            for ps in pipe_segments:
                ps = ps.strip()
                if ps:
                    first_word = ps.split()[0] if ps.split() else ""
                    if first_word:
                        commands.append(os.path.basename(first_word))
            continue

        if not tokens:
            continue

        expect_command = True

        for token in tokens:
            if token in ("|", "||", "&&", "&"):
                expect_command = True
                continue

            if token in (
                "if", "then", "else", "elif", "fi", "for", "while",
                "until", "do", "done", "case", "esac", "in", "!", "{", "}",
            ):
                continue

            if token.startswith("-"):
                continue

            if "=" in token and not token.startswith("="):
                continue

            if expect_command:
                cmd = os.path.basename(token)
                commands.append(cmd)
                expect_command = False

    return commands


def validate_pkill_command(command_string: str) -> tuple[bool, str]:
    """Validate pkill commands - only allow killing dev-related processes."""
    allowed_process_names = {
        "node", "npm", "npx", "php", "wp", "wp-env",
        "docker", "phpunit", "composer",
    }

    try:
        tokens = shlex.split(command_string)
    except ValueError:
        return False, "Could not parse pkill command"

    if not tokens:
        return False, "Empty pkill command"

    args = [t for t in tokens[1:] if not t.startswith("-")]

    if not args:
        return False, "pkill requires a process name"

    target = args[-1]
    if " " in target:
        target = target.split()[0]

    if target in allowed_process_names:
        return True, ""
    return False, f"pkill only allowed for dev processes: {allowed_process_names}"


def validate_chmod_command(command_string: str) -> tuple[bool, str]:
    """Validate chmod commands - only allow making files executable."""
    import re

    try:
        tokens = shlex.split(command_string)
    except ValueError:
        return False, "Could not parse chmod command"

    if not tokens or tokens[0] != "chmod":
        return False, "Not a chmod command"

    mode = None
    files = []

    for token in tokens[1:]:
        if token.startswith("-"):
            return False, "chmod flags are not allowed"
        elif mode is None:
            mode = token
        else:
            files.append(token)

    if mode is None:
        return False, "chmod requires a mode"

    if not files:
        return False, "chmod requires at least one file"

    if not re.match(r"^[ugoa]*\+x$", mode):
        return False, f"chmod only allowed with +x mode, got: {mode}"

    return True, ""


def validate_init_script(command_string: str) -> tuple[bool, str]:
    """Validate init.sh script execution."""
    try:
        tokens = shlex.split(command_string)
    except ValueError:
        return False, "Could not parse init script command"

    if not tokens:
        return False, "Empty command"

    script = tokens[0]

    if script == "./init.sh" or script.endswith("/init.sh"):
        return True, ""

    return False, f"Only ./init.sh is allowed, got: {script}"


def validate_rm_command(command_string: str) -> tuple[bool, str]:
    """Validate rm commands - block dangerous patterns."""
    try:
        tokens = shlex.split(command_string)
    except ValueError:
        return False, "Could not parse rm command"

    if not tokens or tokens[0] != "rm":
        return False, "Not an rm command"

    dangerous_paths = {
        "/", "/*", "/.", "/..", "/home", "/home/*", "/Users", "/Users/*",
        "/etc", "/etc/*", "/var", "/var/*", "/usr", "/usr/*", "/bin",
        "/sbin", "/lib", "/opt", "/root", "/System", "/Applications",
        "~", "~/*", "$HOME", "$HOME/*",
        # WordPress-specific protected paths
        "wp-includes", "wp-admin", "wp-config.php",
    }

    paths = []
    has_recursive = False
    has_force = False

    for token in tokens[1:]:
        if token.startswith("-"):
            if "r" in token or "R" in token:
                has_recursive = True
            if "f" in token:
                has_force = True
        else:
            paths.append(token)

    for path in paths:
        normalized = path.rstrip("/")
        if normalized == "":
            normalized = "/"

        if path in dangerous_paths or normalized in dangerous_paths:
            return False, f"rm blocked for dangerous path: {path}"

        # Block WordPress core paths
        if "wp-includes" in path or "wp-admin" in path:
            return False, f"rm blocked for WordPress core path: {path}"

        if path.startswith("/") and path.count("/") <= 2:
            if has_recursive and has_force:
                return False, f"rm -rf blocked for system-level path: {path}"

    return True, ""


def validate_wp_command(command_string: str) -> tuple[bool, str]:
    """
    Validate WP-CLI commands - block dangerous operations.

    Blocks:
    - Database operations that could destroy data (db drop, db reset)
    - Core file modifications
    - Multisite super-admin operations
    """
    try:
        tokens = shlex.split(command_string)
    except ValueError:
        return False, "Could not parse wp command"

    if not tokens or tokens[0] != "wp":
        return False, "Not a wp command"

    # Get the subcommand
    subcommands = [t for t in tokens[1:] if not t.startswith("-")]

    if not subcommands:
        return True, ""  # Just `wp` with flags is fine

    main_cmd = subcommands[0]
    sub_cmd = subcommands[1] if len(subcommands) > 1 else None

    # Block dangerous database operations
    if main_cmd == "db":
        dangerous_db_ops = {"drop", "reset", "clean"}
        if sub_cmd in dangerous_db_ops:
            return False, f"wp db {sub_cmd} is blocked for safety"

    # Block core download/update (could break the install)
    if main_cmd == "core":
        dangerous_core_ops = {"download", "update", "verify-checksums"}
        if sub_cmd in dangerous_core_ops:
            return False, f"wp core {sub_cmd} is blocked - use wp-env for core management"

    # Block config operations
    if main_cmd == "config":
        if sub_cmd in {"create", "delete", "set"}:
            # Check if it's touching sensitive values
            config_keys = [t for t in tokens if not t.startswith("-") and t not in ["wp", "config", sub_cmd]]
            sensitive_keys = {"DB_PASSWORD", "AUTH_KEY", "SECURE_AUTH_KEY", "LOGGED_IN_KEY", "NONCE_KEY"}
            for key in config_keys:
                if key.upper() in sensitive_keys:
                    return False, f"wp config {sub_cmd} blocked for sensitive key: {key}"

    return True, ""


def get_command_for_validation(cmd: str, segments: list[str]) -> str:
    """Find the specific command segment that contains the given command."""
    for segment in segments:
        segment_commands = extract_commands(segment)
        if cmd in segment_commands:
            return segment
    return ""


async def bash_security_hook(input_data, tool_use_id=None, context=None):
    """
    Pre-tool-use hook that validates bash commands using an allowlist.
    """
    if input_data.get("tool_name") != "Bash":
        return {}

    command = input_data.get("tool_input", {}).get("command", "")
    if not command:
        return {}

    commands = extract_commands(command)

    if not commands:
        return {
            "decision": "block",
            "reason": f"Could not parse command for security validation: {command}",
        }

    segments = split_command_segments(command)

    for cmd in commands:
        if cmd not in ALLOWED_COMMANDS:
            return {
                "decision": "block",
                "reason": f"Command '{cmd}' is not in the allowed commands list",
            }

        if cmd in COMMANDS_NEEDING_EXTRA_VALIDATION:
            cmd_segment = get_command_for_validation(cmd, segments)
            if not cmd_segment:
                cmd_segment = command

            if cmd == "pkill":
                allowed, reason = validate_pkill_command(cmd_segment)
                if not allowed:
                    return {"decision": "block", "reason": reason}
            elif cmd == "chmod":
                allowed, reason = validate_chmod_command(cmd_segment)
                if not allowed:
                    return {"decision": "block", "reason": reason}
            elif cmd == "init.sh":
                allowed, reason = validate_init_script(cmd_segment)
                if not allowed:
                    return {"decision": "block", "reason": reason}
            elif cmd == "rm":
                allowed, reason = validate_rm_command(cmd_segment)
                if not allowed:
                    return {"decision": "block", "reason": reason}
            elif cmd == "wp":
                allowed, reason = validate_wp_command(cmd_segment)
                if not allowed:
                    return {"decision": "block", "reason": reason}

    return {}
