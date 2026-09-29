---
allowed-tools: Bash(git:*), Bash(ls:*)
description: Implement spec using Claude Code subagents
---

# Implementation mit Subagents

### Phase 1: Implementation  
- Execute `/speckit.implement` and delegate the tasks to the specific subagents.
- **laravel-senior-developer**: Implement the backend parts of the specification
- **frontend-component-builder**: Implement the frontend parts of the specification
- **code-reviewer**: Review structural decisions in parallel

### Phase 2: Tests & Quality
- **qa-issue-validatore**: Final review of the issue and implementation

### Finalize ###
Create a descriptive commit message, push and create a PR