# Documentation

The source of truth for what Vaytoven is, how it's built, and what's coming next.

| File | Purpose | Format |
|------|---------|--------|
| [`SRS.md`](SRS.md) | Software Requirements Specification — what the system must do, with FR-IDs. **Read first.** | Markdown |
| [`SRS.docx`](SRS.docx) | Same as `SRS.md`, formatted for stakeholders who want a Word document. | DOCX |
| [`schema.sql`](schema.sql) | Full Postgres schema. 33 tables, fully commented. | SQL |
| [`architecture.md`](architecture.md) | High-level architecture: services, data flow, deployment topology, key trade-offs. | Markdown |
| [`architecture.docx`](architecture.docx) | Architecture in Word format. | DOCX |
| [`roadmap.md`](roadmap.md) | 22-week build plan with milestones and dependencies. | Markdown |
| [`roadmap.docx`](roadmap.docx) | Roadmap in Word format. | DOCX |
| [`landing-page-spec.md`](landing-page-spec.md) | Content + design decisions for the marketing site. | Markdown |
| [`pitch-deck.pptx`](pitch-deck.pptx) | Investor and partner narrative. 14 slides. | PPTX |
| [`CODEX_HANDOFF.md`](CODEX_HANDOFF.md) | Current Laravel build state, environment blockers, local setup commands, and VS Code/Codex continuation prompt. | Markdown |

## Updating these docs

- **SRS** — every behavior-changing PR that implements or modifies a requirement should update `SRS.md` in the same PR. Don't ship features without a corresponding FR.
- **schema.sql** — keep this in sync with `backend/database/migrations/` line-for-line. The migrations are the source of truth for the running DB; this file is the source of truth for the design.
- **architecture.md** — update whenever a service is added, replaced, or significantly redesigned.
- **roadmap.md** — update at the start of each milestone with what actually shipped vs. planned, and re-baseline the remaining timeline.
- **CODEX_HANDOFF.md** — update when the active Laravel build state, local blockers, or continuation prompt changes.

## Versioning

The SRS is versioned in its frontmatter (`Version 1.1`). Bump the minor version (`1.1 → 1.2`) for any new requirement; bump the major version (`1.x → 2.0`) for any change that breaks an existing FR's contract.
