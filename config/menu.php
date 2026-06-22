<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Role-default favourites
    |--------------------------------------------------------------------------
    |
    | Menu keys (the frontend NAV_ZONES titleKeys) that are pinned into a fresh
    | user's Favourites group by default, per persona role. A user can opt out by
    | un-starring — that stores an explicit `is_favorite = false` preference which
    | wins over these defaults. Keys not present anywhere here simply start unpinned.
    |
    */

    'default_favorites' => [
        'employee' => [
            'nav.dashboard',
            'nav.onlineAttendance',
            'nav.applyLeave',
            'nav.myRequests',
        ],
        'supervisor' => [
            'nav.dashboard',
            'nav.approvalInbox',
            'nav.teamAttendance',
        ],
        'manager' => [
            'nav.dashboard',
            'nav.approvalInbox',
            'nav.attendanceReport',
        ],
        'hr' => [
            'nav.dashboard',
            'nav.employees',
            'nav.attendanceReport',
            'nav.leaveReport',
        ],
        'administrator' => [
            'nav.dashboard',
            'nav.employees',
            'nav.users',
            'nav.menu',
        ],
    ],

];
