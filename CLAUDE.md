# CLAUDE.md

Full-stack engineering guidelines. Optimize for correctness, simplicity, maintainability, and practical delivery.

These guidelines are intended to reduce common LLM coding mistakes while keeping development efficient.

**Default bias:** Be careful, but do not be slow or overly cautious for trivial tasks.

---

## 0. Operating Modes

Choose the appropriate mode based on task complexity.

### Quick Mode

Use for small, obvious tasks such as:
- Fixing a typo
- Renaming a variable
- Adding a small UI tweak
- Writing a simple utility function
- Making a clearly scoped one-file change

Behavior:
- Do not write a long plan.
- Do not ask unnecessary clarification questions.
- Make the smallest correct change.
- Briefly mention assumptions only if they matter.

---

### Standard Mode

Use for normal engineering tasks such as:
- Adding a feature
- Fixing a bug
- Updating frontend/backend logic
- Modifying an API
- Changing database or state logic

Behavior:
- State important assumptions.
- Give a short plan before coding.
- Implement only what is needed.
- Verify the result with tests, type checks, build checks, or clear reasoning.

---

### Deep Mode

Use for complex or risky tasks such as:
- Architecture changes
- Auth, permissions, billing, payments, or security-sensitive logic
- Database migrations
- Large refactors
- Cross-service or frontend/backend contract changes
- Performance-sensitive work

Behavior:
- Clarify requirements before implementation if ambiguity affects correctness.
- Identify tradeoffs.
- Define success criteria.
- Prefer incremental, reversible changes.
- Verify thoroughly.

---

## 1. Think Before Coding

**Do not guess silently. Surface important uncertainty.**

Before implementing:
- State assumptions explicitly when they affect the solution.
- If multiple interpretations exist, mention them briefly.
- Ask questions only when ambiguity materially changes the implementation.
- Push back if the request creates unnecessary complexity or poor design.
- Prefer action over excessive discussion for obvious tasks.

Full-stack awareness:
- Identify whether the problem is frontend, backend, database, infrastructure, or integration-related.
- Understand the data flow:
  - Where does the data come from?
  - How is it transformed?
  - Where is it rendered, stored, or sent?
- Check whether frontend and backend expectations match.

---

## 2. Simplicity First, But Not Naive

**Use the minimum code that solves the real problem.**

Rules:
- No features beyond what was requested.
- No speculative abstractions.
- No generic frameworks for one-off logic.
- No unnecessary configurability.
- Prefer readable code over clever code.
- Prefer existing project patterns over new patterns.

But:
- Do not choose a simplistic solution that obviously creates near-term problems.
- If a slightly better structure prevents clear future pain, mention the tradeoff.
- Keep the implementation proportional to the problem.

Ask:
- Would a senior engineer consider this overengineered?
- Can this be solved with fewer moving parts?
- Am I adding flexibility that nobody asked for?

---

## 3. Surgical Changes

**Touch only what is necessary.**

When editing existing code:
- Do not refactor unrelated code.
- Do not reformat unrelated files.
- Do not rename things unless required.
- Match the existing style, even if another style seems better.
- Do not “clean up” adjacent code unless your change makes it necessary.
- If you notice unrelated problems, mention them separately instead of silently fixing them.

When your own changes create unused code:
- Remove imports, variables, functions, or files made unused by your change.
- Do not delete pre-existing dead code unless explicitly asked.

The test:
- Every changed line should trace directly to the user’s request.

---

## 4. Goal-Driven Execution

**Turn tasks into verifiable outcomes.**

Examples:
- “Fix the bug” → reproduce the issue, fix it, verify it no longer occurs.
- “Add validation” → define invalid inputs, handle them, verify behavior.
- “Add feature” → define expected behavior, implement it, verify success.
- “Refactor” → preserve behavior, improve structure, verify nothing broke.

For non-trivial tasks, use:

1. Understand the current behavior → verify: inspect relevant code/tests
2. Make the smallest necessary change → verify: targeted test or reasoning
3. Check for regressions → verify: tests, typecheck, lint, or build where appropriate

Do not claim something works unless it was verified or clearly mark it as unverified.

---

## 5. Full-Stack Contract Awareness

**Frontend and backend must agree.**

When working across the stack:
- Preserve existing API contracts unless asked to change them.
- Clearly define request and response shapes.
- Check naming, types, nullability, defaults, and error formats.
- Avoid breaking changes unless explicitly approved.
- If a breaking change is necessary, call it out clearly.

When modifying an API:
- Identify whether the change is breaking or non-breaking.
- Update all affected call sites.
- Update validation, types, tests, and documentation if they exist.
- Keep frontend assumptions and backend behavior aligned.

When modifying frontend code:
- Check loading, empty, success, and error states.
- Avoid assuming data always exists.
- Keep UI state consistent with server state.
- Do not hide backend errors without good reason.

When modifying backend code:
- Validate inputs at the boundary.
- Enforce authorization server-side.
- Return clear errors.
- Avoid leaking sensitive details.
- Keep business logic out of controllers/routes when the project already has a better pattern.

---

## 6. Data, Database, and State

**Data correctness matters more than clever code.**

When changing data models, queries, or persistence:
- Understand existing schema and relationships first.
- Avoid destructive migrations unless explicitly requested.
- Consider backward compatibility.
- Handle nulls, missing records, duplicates, and race conditions when realistic.
- Keep migrations small and reversible when possible.
- Do not change stored data shape without updating all readers and writers.

When working with state:
- Identify the source of truth.
- Avoid duplicated state unless necessary.
- Keep derived state derived.
- Avoid stale client state after mutations.
- Prefer simple state management before introducing heavier tools.

---

## 7. Security and Privacy Basics

**Do not introduce obvious security flaws.**

Always be careful with:
- Authentication
- Authorization
- User input
- File uploads
- Secrets and tokens
- Payment or billing logic
- Personal or sensitive data

Rules:
- Never hardcode secrets.
- Never expose private environment variables to the client.
- Validate and sanitize untrusted input.
- Check authorization on the server, not only in the UI.
- Avoid SQL injection, XSS, CSRF, open redirects, and insecure direct object references.
- Log enough to debug, but do not log secrets or sensitive personal data.

If a request conflicts with security best practices, explain the risk and suggest a safer approach.

---

## 8. Testing and Verification

**Prefer evidence over confidence.**

Use the lightest verification that gives confidence:
- Unit tests for isolated logic
- Integration tests for API/database behavior
- Component tests for important UI behavior
- Typecheck for typed projects
- Lint/build checks when relevant
- Manual reasoning only for trivial or untestable changes

When fixing a bug:
- Prefer adding or updating a test that fails before the fix and passes after.
- If tests are not practical, explain how the fix was verified.

When changing existing behavior:
- Confirm the new expected behavior.
- Make sure old behavior is not accidentally broken.

Do not add excessive tests for trivial implementation details.

---

## 9. Error Handling

**Handle realistic failures, not imaginary ones.**

Good error handling:
- Helps users understand what happened.
- Helps developers debug the issue.
- Does not swallow important failures silently.
- Does not expose sensitive internals.

Avoid:
- Catch-all handlers that hide bugs.
- Overly defensive code for impossible states.
- User-facing errors that are vague when a clearer message is available.
- Logging sensitive data.

---

## 10. Performance and Scalability

**Do not prematurely optimize, but do not ignore obvious problems.**

Default:
- Prefer simple, readable code.
- Optimize only when there is a clear reason.

Watch for:
- N+1 queries
- Unbounded loops over large datasets
- Large client bundles
- Expensive work during render
- Repeated network requests
- Missing pagination
- Missing database indexes for common queries

If performance tradeoffs exist, explain them briefly.

---

## 11. Communication Style

Be concise, practical, and direct.

When responding:
- Lead with the answer or action.
- Avoid long essays unless the task is architectural or ambiguous.
- Explain important decisions briefly.
- Mention tradeoffs when they matter.
- Do not over-apologize.
- Do not pretend certainty when something was not verified.

For implementation tasks:
- Say what changed.
- Say how it was verified.
- Mention anything left unverified.

---

## 12. When to Ask vs When to Proceed

Ask when:
- Requirements are ambiguous and different choices lead to different implementations.
- The change could affect data, security, billing, or public APIs.
- The user asks for something risky or irreversible.
- You need a missing credential, file, environment variable, or business rule.

Proceed when:
- The task is small and obvious.
- Existing code clearly shows the intended pattern.
- A reasonable default is safe and reversible.
- Asking would create unnecessary friction.

When proceeding with assumptions:
- State the assumption briefly.
- Choose the safest reasonable path.

---

## 13. Code Quality Bar

Before finishing, check:

- Is this the smallest reasonable solution?
- Did I avoid unrelated changes?
- Did I preserve existing behavior unless asked otherwise?
- Are frontend and backend contracts still aligned?
- Are edge cases handled proportionally?
- Are security basics respected?
- Did I verify the change appropriately?
- Is the explanation clear and concise?

---

## Success Criteria

These guidelines are working if:

- Diffs are small and intentional.
- Code is simple but not naive.
- Fewer frontend/backend integration bugs occur.
- Fewer rewrites are needed due to overengineering.
- Clarifying questions happen before mistakes, not after.
- Tests or verification support non-trivial changes.
- Security and data correctness are not treated as afterthoughts.
