<?php

declare(strict_types=1);

namespace App\Http\Documentation;

final class ApiEndpointCatalog
{
    /** @return list<array{method: string, path: string, tag: string, summary: string, description: string, auth: string, parameters: list<string>, response: string}> */
    public function all(): array
    {
        return array_values(array_map(
            static fn (ApiEndpoint $endpoint): array => $endpoint->toArray(),
            [
                $this->endpoint('GET', '/api/auth/session', 'Authentication', 'Read the current session', 'Returns the signed-in borrower, guest, or staff session.', 'Authenticated session', [], 'JSON session identity or an unauthenticated state.'),
                $this->endpoint('POST', '/api/auth/logout', 'Authentication', 'Log out the current session', 'Invalidates the current session and rotates the session identifier.', 'Authenticated session', ['Body: csrf'], 'JSON success response with a redirect path.'),
                $this->endpoint('GET', '/logout', 'Authentication', 'Legacy logout route', 'Compatibility route for navigation links that log out through a browser request.', 'Authenticated session', [], 'HTTP redirect to the login page.'),
                $this->endpoint('POST', '/api/auth/borrower/login', 'Authentication', 'Sign in a borrower by barcode', 'Authenticates a student or teacher using an ID barcode.', 'Public', ['Body: barcode'], 'JSON authentication result or registration requirement.'),
                $this->endpoint('POST', '/api/auth/student/login', 'Authentication', 'Sign in through the student login alias', 'Compatibility alias for borrower barcode login.', 'Public', ['Body: barcode'], 'JSON authentication result or registration requirement.'),
                $this->endpoint('POST', '/api/auth/staff/login', 'Authentication', 'Sign in a staff member', 'Authenticates an administrator or librarian with barcode and password.', 'Public', ['Body: barcode, password'], 'JSON authentication result with the staff dashboard redirect.'),
                $this->endpoint('POST', '/api/auth/register', 'Authentication', 'Begin borrower registration', 'Creates a pending student or teacher registration and sends an OTP.', 'Public', ['Body: barcode, role, profile fields, photo_data'], 'JSON registration result with OTP verification redirect.'),
                $this->endpoint('POST', '/api/auth/otp', 'Authentication', 'Verify borrower registration OTP', 'Completes the pending borrower registration after OTP validation.', 'Registration session', ['Body: otp'], 'JSON success response with login redirect.'),
                $this->endpoint('POST', '/api/auth/otp/resend', 'Authentication', 'Resend borrower registration OTP', 'Sends a replacement OTP for the pending borrower registration.', 'Registration session', ['Body: csrf'], 'JSON success response.'),

                $this->endpoint('GET', '/api/books', 'Books', 'List the staff book inventory', 'Returns inventory records for staff inventory management.', 'Admin or librarian', ['Query: search, status, category, floor'], 'JSON inventory list and filter metadata.'),
                $this->endpoint('GET', '/api/student/books', 'Books', 'Search available books', 'Returns the borrower catalog with availability and borrower-specific state.', 'Student or teacher', ['Query: search, category_name, status, floor, sort'], 'JSON book list, categories, floors, and total.'),
                $this->endpoint('POST', '/api/books', 'Books', 'Create or update a book', 'Creates a new physical copy or updates an existing inventory record.', 'Admin or librarian', ['Body: action, book fields, csrf'], 'JSON mutation result.'),

                $this->endpoint('GET', '/api/student/dashboard', 'Borrower', 'Read the student dashboard', 'Returns the current borrower profile, loan stats, current loans, and recommendations.', 'Student or teacher', [], 'JSON borrower dashboard data.'),
                $this->endpoint('GET', '/api/teacher/dashboard', 'Borrower', 'Read the teacher dashboard', 'Returns the current teacher profile, loan stats, current loans, and recommendations.', 'Student or teacher', [], 'JSON borrower dashboard data.'),
                $this->endpoint('GET', '/api/student/history', 'Borrower', 'Read borrowing history', 'Returns the authenticated borrower’s complete borrowing history.', 'Student or teacher', [], 'JSON borrowing history list.'),
                $this->endpoint('GET', '/api/receipt', 'Borrower', 'Read a borrowing receipt', 'Returns a receipt only when its transaction belongs to the authenticated borrower.', 'Student or teacher', ['Query: code'], 'JSON receipt with borrower, book, dates, and status.'),
                $this->endpoint('POST', '/api/student/borrow', 'Borrower', 'Submit a borrow request', 'Creates a pending borrow request subject to the three-book capacity rule and staff approval.', 'Student or teacher', ['Body: action=borrow, book_barcode, due_date, csrf'], 'JSON transaction code and request message.'),
                $this->endpoint('POST', '/api/student/return', 'Borrower', 'Return a book', 'Returns a book by barcode or transaction code for the authenticated borrower.', 'Student or teacher', ['Body: action=return_unified, return_input, csrf'], 'JSON return result.'),
                $this->endpoint('POST', '/api/student/dashboard', 'Borrower', 'Borrower dashboard action', 'Compatibility action endpoint for borrower dashboard forms.', 'Student or teacher', ['Body: action, borrowing fields, csrf'], 'JSON borrowing or return result.'),
                $this->endpoint('POST', '/api/teacher/dashboard', 'Borrower', 'Teacher dashboard action', 'Compatibility action endpoint for teacher dashboard forms.', 'Student or teacher', ['Body: action, borrowing fields, csrf'], 'JSON borrowing or return result.'),

                $this->endpoint('GET', '/api/guest/dashboard', 'Guest', 'Read the guest dashboard', 'Returns the current visitor profile, borrowing summary, visits, and security activity.', 'Guest session', [], 'JSON guest dashboard data.'),
                $this->endpoint('GET', '/api/guest/books', 'Guest', 'Browse guest books', 'Returns available catalog records for a guest visitor.', 'Guest session', ['Query: search, category, floor, id'], 'JSON book list and total.'),
                $this->endpoint('GET', '/api/guest/history', 'Guest', 'Read guest borrowing history', 'Returns filtered visitor borrowing history.', 'Guest session', ['Query: status, from, to'], 'JSON visitor borrowing history.'),
                $this->endpoint('GET', '/api/guest/borrowed', 'Guest', 'Read active guest loans', 'Returns the visitor’s currently released books.', 'Guest session', [], 'JSON active visitor loans.'),
                $this->endpoint('GET', '/api/guest/receipt', 'Guest', 'Read a guest receipt', 'Returns a receipt scoped to the current visitor.', 'Guest session', ['Query: id'], 'JSON visitor receipt.'),
                $this->endpoint('POST', '/api/guest/borrow', 'Guest', 'Submit a guest borrow request', 'Creates a visitor borrow request with verification photo.', 'Guest session', ['Body: book_id, verification_photo, csrf'], 'JSON pending request result.'),
                $this->endpoint('POST', '/api/guest/return', 'Guest', 'Submit a guest return request', 'Creates a return verification request for a released visitor loan.', 'Guest session', ['Body: barcode, photo_data, csrf'], 'JSON return request result.'),
                $this->endpoint('GET', '/api/guest/profile', 'Guest', 'Read guest profile', 'Returns the current visitor profile.', 'Guest session', [], 'JSON visitor profile.'),
                $this->endpoint('POST', '/api/guest/profile', 'Guest', 'Update guest profile', 'Updates editable visitor details after CSRF validation.', 'Guest session', ['Body: profile fields, csrf'], 'JSON profile update result.'),
                $this->endpoint('GET', '/api/guest/pass', 'Guest', 'Read guest visitor pass', 'Returns the current visitor pass data.', 'Guest session', [], 'JSON visitor pass.'),
                $this->endpoint('POST', '/api/auth/guest/register', 'Guest', 'Begin guest registration', 'Creates a pending guest visitor registration and sends an OTP.', 'Public', ['Body: visitor registration fields, photo_data, csrf'], 'JSON registration result.'),
                $this->endpoint('POST', '/api/auth/guest/otp', 'Guest', 'Verify guest registration OTP', 'Completes guest registration after OTP validation.', 'Guest registration session', ['Body: otp, csrf'], 'JSON success response with guest dashboard redirect.'),
                $this->endpoint('POST', '/api/auth/guest/otp/resend', 'Guest', 'Resend guest registration OTP', 'Sends a replacement OTP for a pending guest registration.', 'Guest registration session', ['Body: csrf'], 'JSON success response.'),

                $this->endpoint('GET', '/api/staff/dashboard', 'Staff', 'Read the staff dashboard', 'Returns staff metrics, pending borrower approvals, recent activity, and overview analytics.', 'Admin or librarian', [], 'JSON staff dashboard and overview payload.'),
                $this->endpoint('GET', '/api/staff/borrowers', 'Staff', 'List borrowers', 'Returns student and teacher accounts with active and overdue loan counts.', 'Admin or librarian', ['Query: search'], 'JSON borrower list.'),
                $this->endpoint('GET', '/api/staff/borrower', 'Staff', 'Read borrower details', 'Returns a borrower profile, history, and loan summary.', 'Admin or librarian', ['Query: id'], 'JSON borrower details and history.'),
                $this->endpoint('POST', '/api/staff/borrower/photo', 'Staff', 'Update borrower photo', 'Updates a borrower ID photo.', 'Admin or librarian', ['Body: user_id, photo_data, csrf'], 'JSON update result.'),
                $this->endpoint('POST', '/api/staff/notify', 'Staff', 'Notify a borrower', 'Sends a borrower notification through the selected channel.', 'Admin or librarian', ['Body: user_id, channel, csrf'], 'JSON notification result.'),
                $this->endpoint('GET', '/api/staff/overdue', 'Staff', 'List overdue loans', 'Returns overdue borrower loans and calculated fines.', 'Admin or librarian', [], 'JSON overdue list and total fine.'),
                $this->endpoint('GET', '/api/staff/reports', 'Staff', 'Generate a report dataset', 'Returns a structured report for borrowing, returns, overdue loans, or inventory.', 'Admin or librarian', ['Query: type, from, to'], 'JSON report metadata, headers, and rows.'),
                $this->endpoint('GET', '/api/staff/reports/export', 'Staff', 'Export a report', 'Downloads the selected report as CSV.', 'Admin or librarian', ['Query: type, from, to'], 'CSV report download.'),
                $this->endpoint('GET', '/api/staff/guest-requests', 'Staff', 'List guest requests', 'Returns pending guest borrow requests for staff review.', 'Admin or librarian', [], 'JSON guest request list.'),
                $this->endpoint('GET', '/api/admin/staff', 'Admin', 'List staff accounts', 'Returns staff accounts and borrower candidates for role management.', 'Admin only', ['Query: bsearch'], 'JSON staff and candidate lists.'),
                $this->endpoint('GET', '/api/staff/notifications', 'Staff', 'Read staff notifications', 'Returns pending approvals or other staff notifications by action.', 'Admin or librarian', ['Query: action'], 'JSON notification list and count.'),
                $this->endpoint('POST', '/api/staff/borrowing-action', 'Staff', 'Approve or reject a borrow request', 'Applies a staff decision to a pending borrower request.', 'Admin or librarian', ['Body: action, borrowing_id, csrf'], 'JSON decision result.'),
                $this->endpoint('POST', '/api/staff/guest-action', 'Staff', 'Approve or reject a guest request', 'Applies a staff decision to a pending guest request.', 'Admin or librarian', ['Body: action, id, notes, csrf'], 'JSON decision result.'),
                $this->endpoint('POST', '/api/admin/staff-action', 'Admin', 'Manage a staff account', 'Promotes, demotes, resets, or toggles a staff account.', 'Admin only', ['Body: action, user_id, role, password, csrf'], 'JSON account-management result.'),
                $this->endpoint('POST', '/api/staff/notifications/viewed', 'Staff', 'Mark a notification viewed', 'Marks a staff notification as read.', 'Admin or librarian', ['Body: notification_id, notification_type, csrf'], 'JSON update result.'),

                $this->endpoint('GET', '/api/admin/api-docs', 'Documentation', 'Read the API documentation catalog', 'Returns this Swagger-style endpoint catalog for the admin documentation page.', 'Admin only', [], 'JSON OpenAPI-style catalog payload.'),
            ],
        ));
    }

    /** @param list<string> $parameters */
    private function endpoint(
        string $method,
        string $path,
        string $tag,
        string $summary,
        string $description,
        string $auth,
        array $parameters,
        string $response,
    ): ApiEndpoint {
        return new ApiEndpoint($method, $path, $tag, $summary, $description, $auth, $parameters, $response);
    }
}
