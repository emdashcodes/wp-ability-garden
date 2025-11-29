"""
Prompt Templates
================

Functions that return the appropriate prompts for seed and contributor agents.
"""

from pathlib import Path


PROMPTS_DIR = Path(__file__).parent / "prompts"


def get_seed_prompt() -> str:
    """Return the seed agent prompt for initial setup."""
    prompt_file = PROMPTS_DIR / "seed_prompt.md"
    return prompt_file.read_text()


def get_contributor_prompt() -> str:
    """Return the contributor agent prompt."""
    prompt_file = PROMPTS_DIR / "contributor_prompt.md"
    return prompt_file.read_text()
