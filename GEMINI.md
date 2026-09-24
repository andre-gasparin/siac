# Gemini and Antigravity Project Instructions

Before planning, editing, or reviewing code in this repository:

1. Read and follow `@AGENTS.md`.
2. Read `@README.md` for the current application structure and commands.
3. For frontend work, follow the feature-based architecture defined in those files.

The native Antigravity workspace rule is available at `@.agents/rules/project-architecture.md`.

Key constraints:

- Keep functional backend behavior in `app/Features/<Feature>` and read
  `app/Features/README.md` before backend changes.
- Keep Models, Providers, middleware, Fortify, and `database/*` in Laravel's
  conventional locations.
- Keep root route files as aggregators for feature-owned routes.
- Keep real frontend implementation in `resources/js/features`, `resources/js/app`, or `resources/js/shared`.
- Keep `resources/js/pages` as thin Inertia adapters only.
- Do not edit Wayfinder-generated `resources/js/actions` or `resources/js/routes` manually.
- Use Wayfinder functions instead of hardcoded backend URLs.
- Preserve feature boundaries and run the relevant format, lint, type, build, and Pest checks.

`AGENTS.md` is the canonical source. Do not duplicate or override its detailed Laravel, Inertia, Vue, Wayfinder, Tailwind, or Pest rules here.
