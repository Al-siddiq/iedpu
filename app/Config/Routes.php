<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// P1-WP03: public, read-only visual previews. These routes confer no access
// to member, branch, committee or developer operations.
$routes->get('preview/member', 'Home::index');
$routes->get('preview/branch', 'Home::index');
$routes->get('preview/committee', 'Home::index');
$routes->get('preview/technical', 'Home::index');

// Read-only, source-derived 2015 Constitution reader. Public preview only;
// authentication and member-only visibility are deferred to WP02 policy.
$routes->get('constitution/2015', 'Constitution::index');
$routes->get('constitution/2015/(:segment)', 'Constitution::show/$1');

// P1-WP02: All API routes are explicitly declared; auto-routing is disabled.
// Do not mount Shield's default login/register routes until P2 has configured
// authentication, invitation-only account creation, and role-scoped access.
$routes->group('api/v1', ['namespace' => 'App\\Controllers\\Api\\V1'], static function (RouteCollection $routes): void {
    $routes->get('health', 'SystemController::health');
    $routes->get('bootstrap', 'SystemController::bootstrap');

    // P2+ functions are deliberately unavailable. No temporary public CRUD,
    // membership enrolment, administrator or developer privilege is exposed.
    foreach ([
        'registration',
        'members',
        'verification',
        'branches',
        'offices',
        'administration',
        'technical',
        'finance',
        'payments',
        'official-ids',
        'announcements',
    ] as $prefix) {
        $routes->add($prefix, 'UnavailableController::disabled');
        $routes->add($prefix . '/(:any)', 'UnavailableController::disabled');
    }

    // Unknown API paths always produce a JSON 404, never a web-page fallback.
    $routes->add('(:any)', 'UnavailableController::notFound');
});
