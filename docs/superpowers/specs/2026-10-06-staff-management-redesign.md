# Staff Management Surface Redesign

## Goal

Make the administrator's staff-management page readable and purposeful without changing its API contracts or account-management behavior.

## Approved direction

Use a restrained Swiss-style admin surface: white and neutral panels, navy text, hairline rules, compact tabular layout, and one deliberate blue accent. Avoid permanent colored blocks and decorative clutter.

## Scope

- Hide the success and error alert containers until they contain an action result.
- Give feedback alerts an explicit message role and compact presentation.
- Strengthen the `Staff Accounts` section hierarchy.
- Improve role and account-status presentation with readable pills.
- Group account actions so reset, status, and demotion are visually distinct.
- Make `Assign Staff Role` a separate, clearly explained workflow.
- Preserve existing selectors, modal IDs, service calls, confirmation metadata, and role-management behavior.
- Preserve profile-change request rendering and review behavior.

## Interaction behavior

- Successful and failed mutations continue to use the existing alert mechanism.
- Only one feedback alert is visible at a time.
- Empty-state rows remain available for staff accounts and borrower candidates.
- Destructive actions retain their confirmation flow.
- Password and promotion modals retain their existing fields and submit paths.

## Verification

- Add frontend contract coverage for hidden feedback alerts and the staff-management structure.
- Run the focused frontend tests.
- Run the complete frontend suite.
- Run the relevant backend staff markup/contract tests.
- Inspect the served page through the XAMPP junction after changes.
