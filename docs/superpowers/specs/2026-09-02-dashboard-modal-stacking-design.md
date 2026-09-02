# Dashboard Modal Stacking Design

## Problem

Borrow, return, success, notification, approval, and other dashboard dialogs can render beneath the Bootstrap backdrop or appear as normal page content. When that happens, the dialog is visually below the page overlay and cannot receive pointer or keyboard interaction. A modal must remain a viewport-layered dialog, centered independently of the dashboard document flow.

## Root cause

`frontend/assets/css/borrower-dashboards.css` currently applies this rule to every direct child of a borrower dashboard content region:

```css
.borrower-dashboard .content > * {
  position: relative;
  z-index: 1;
}
```

The student and teacher dashboard modal elements are direct children of `.content`. The selector therefore overrides Bootstrap's modal positioning/stacking contract through specificity: the modal receives `position: relative` and `z-index: 1`, while Bootstrap's backdrop remains above it. The teacher dashboard also has a custom fixed borrow layer, but the blanket direct-child rule can override that layer's stacking value as well.

## Chosen approach

Keep the dashboard content-layer treatment for ordinary content, but exclude modal elements from that rule:

```css
.borrower-dashboard .content > :not(.modal) {
  position: relative;
  z-index: 1;
}
```

This lets Bootstrap's `.modal` rule keep control of `position: fixed` and `z-index: 1055`, while preserving the existing pseudo-background layering for cards and dashboard panels. It also covers the teacher custom borrow layer because it carries the `.modal` class. No modal markup, event handlers, or borrowing behavior need to change.

An explicit modal override was rejected because it would leave the conflicting blanket selector in place and rely on a future-proof specificity/order relationship. Moving modal nodes to `body` at runtime was rejected as unnecessary DOM and JavaScript complexity for a CSS cascade issue.

## Scope

- Student dashboard: Borrow, Return, and Borrowing Complete modals.
- Teacher dashboard: Borrow, Return, and custom borrow modal layer.
- Staff/admin dashboard: existing Bootstrap modals, including approval, notification, and borrower overview dialogs, included in regression verification.
- Other pages using Bootstrap modals must retain their current behavior.

## Behavior and layout contract

- Opening a modal creates the normal Bootstrap backdrop above dashboard content.
- The modal remains above the backdrop and accepts clicks, typing, focus, close actions, and submission.
- The modal is centered in the viewport by Bootstrap's fixed modal layout or the teacher custom fixed layer.
- The modal does not occupy a position at the bottom of the dashboard page or push page content.
- Ordinary direct children of borrower dashboard `.content` retain their existing `position: relative; z-index: 1` layering.
- Existing responsive modal sizing, scrolling, and mobile footer behavior remain unchanged.

## Testing and verification

The implementation will begin with a failing frontend regression test that checks the shared selector excludes modal direct children while retaining the ordinary-content layering rule. After the minimal CSS change, the test must pass.

Manual browser verification will cover:

1. Student dashboard: open Borrow, interact with the barcode input and buttons, close it, then open Return and Borrowing Complete.
2. Teacher dashboard: open Borrow and Return, including the custom borrow layer's close and form controls.
3. Staff/admin dashboard: open representative approval, notification, and overview modals and confirm they remain centered and interactive.
4. Desktop and narrow viewport sizes: dialogs stay within the viewport, remain centered, and scroll internally when content is tall.

The full existing frontend test suite will be run after the targeted regression test passes.
