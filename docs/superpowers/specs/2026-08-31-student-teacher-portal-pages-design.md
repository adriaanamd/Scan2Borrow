# Student and Teacher Portal Pages

## Problem

The student search and history routes are authorized correctly, but their browser modules still contain teacher branches and their templates load teacher-oriented styles. This allows faculty presentation concerns to remain coupled to student pages.

## Design

Student and teacher circulation tabs will have independent page boundaries.

- Student search and history keep their existing `/student/*` routes, student API endpoints, and student-owned HTML/controllers.
- Teacher Borrow and History keep their `/teacher/*` routes, teacher API endpoints, and teacher-owned HTML/controllers.
- Student controllers will have no teacher role detection, teacher endpoints, teacher labels, teacher CSS classes, or cached-role reads.
- Teacher controllers will have no dependency on student page controllers; they may reuse neutral models/utilities such as the bulk borrow cart.
- Student templates will load only the shared base styles and student visual styles. Teacher templates will load only the shared base styles and teacher visual styles.
- Navigation remains role-specific and server route policies remain strict.

## Data flow

The server maps each role-owned route to its corresponding feature template. Each template declares its role explicitly in `data-navbar-role` and `data-app-page`. Its page entry calls only the matching API namespace. No role is inferred from session storage or selected dynamically at runtime.

## Error handling

Existing bounded loading, empty, and error states remain in each page. API errors are rendered inside the page’s own result/table region. Unauthorized access is handled by the existing page access authorizer and redirects to the authenticated role home.

## Verification

Regression coverage will assert:

- student templates do not reference teacher styles or modules;
- teacher templates do not reference student page controllers;
- each role uses its own route, page marker, navigation role, and API endpoints;
- the full frontend and backend suites remain green.
