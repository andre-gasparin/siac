# Always-On Project Architecture Rule

Read and follow `@../../AGENTS.md` before changing code. Use `@../../README.md` for the current project structure and commands.

For frontend work:

- Implement business UI inside `resources/js/features/<feature>`.
- Put application shell, navigation, and global layouts in `resources/js/app`.
- Put genuinely reusable, feature-independent code in `resources/js/shared`.
- Keep `resources/js/pages` as thin Inertia adapters that only default-export a feature page.
- Do not add business logic, state, requests, or templates to page adapters.
- Do not import feature internals from another feature.
- `shared` must not import from `app` or `features`.
- Do not manually edit Wayfinder-generated `resources/js/actions` or `resources/js/routes`.
- Use Wayfinder functions instead of hardcoded backend URLs.
- Preserve the component names expected by Laravel unless backend rendering and tests are intentionally updated in the same task.
- Run formatting, lint, TypeScript, build, and relevant Pest tests after changes.

Treat `AGENTS.md` as the canonical source whenever instructions overlap.
