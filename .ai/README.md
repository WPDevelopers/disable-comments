# .ai/ — Agent Context Directory

This directory stores documentation and context generated or maintained by AI agents (Claude Code, etc.) during development work.

## Purpose

- **Deep-dive architecture docs** — detail too verbose for `CLAUDE.md`
- **Investigation notes** — written while exploring complex features
- **Feature context** — background and rationale for non-obvious design decisions

This is NOT a replacement for code comments, git history, or `docs/`. It supplements `CLAUDE.md` with detail that would otherwise bloat it.

> **Agents:** When you discover context worth preserving, write it here — not into `CLAUDE.md`. Reference the file from `CLAUDE.md` with a one-line pointer.

## Structure

```text
.ai/
  README.md       # This file — directory overview
  security/       # Vulnerability reports and fix guidance (excluded from git)
```

## Rules for Agents

- **Write here, not in `CLAUDE.md`** — keep CLAUDE.md as a concise rules + pointers file
- **One file per topic** — don't append unrelated notes to an existing file
- **This directory is excluded from distribution zips** — `.distignore` covers `.ai/` recursively
