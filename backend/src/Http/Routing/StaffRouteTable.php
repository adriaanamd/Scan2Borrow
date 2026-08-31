<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\StaffController;
use App\Http\Controllers\ApiDocumentationController;

final class StaffRouteTable
{
    /** @return list<Route> */
    public function routes(StaffController $controller, ?ApiDocumentationController $documentation = null): array
    {
        $routes = [
            Route::create('GET', '/api/staff/dashboard', [$controller, 'dashboard']),
            Route::create('GET', '/api/staff/borrowers', [$controller, 'borrowers']),
            Route::create('GET', '/api/staff/borrower', [$controller, 'borrowerDetails']),
            Route::create('POST', '/api/staff/borrower/photo', [$controller, 'updateBorrowerPhoto']),
            Route::create('POST', '/api/staff/notify', [$controller, 'notifyBorrower']),
            Route::create('GET', '/api/staff/overdue', [$controller, 'overdue']),
            Route::create('GET', '/api/staff/reports', [$controller, 'report']),
            Route::create('GET', '/api/staff/reports/export', [$controller, 'exportReport']),
            Route::create('GET', '/api/staff/guest-requests', [$controller, 'guestRequests']),
            Route::create('GET', '/api/admin/staff', [$controller, 'adminStaff']),
            Route::create('GET', '/api/admin/profile-change-requests', [$controller, 'profileChangeRequests']),
            Route::create('GET', '/api/staff/notifications', [$controller, 'notifications']),
            Route::create('POST', '/api/staff/borrowing-action', [$controller, 'borrowingAction']),
            Route::create('POST', '/api/staff/guest-action', [$controller, 'guestAction']),
            Route::create('POST', '/api/admin/staff-action', [$controller, 'adminAction']),
            Route::create('POST', '/api/admin/profile-change-request-action', [$controller, 'profileChangeRequestAction']),
            Route::create('POST', '/api/staff/notifications/viewed', [$controller, 'markNotificationViewed']),
        ];

        if ($documentation !== null) {
            $routes[] = Route::create('GET', '/api/admin/api-docs', [$documentation, 'index']);
        }

        return $routes;
    }
}
