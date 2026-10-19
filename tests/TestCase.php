<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Fortify;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create the application instance.
     *
     * Fortify::$registersRoutes is a static that our app's FortifyServiceProvider
     * sets to false on boot (via Fortify::ignoreRoutes()). Because the static
     * persists across application re-boots within the same PHP process, only the
     * first test in a run would register Fortify's auth routes (register, login,
     * etc.), causing "Route [register] not defined" in subsequent tests. Reset it
     * before each boot so every test sees a consistent set of routes.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        Fortify::$registersRoutes = true;

        // SystemSetting memoizes settings in a static to avoid per-request
        // queries; reset it BEFORE boot so each test sees the current DB.
        \App\Models\SystemSetting::flushResolved();

        $app = parent::createApplication();

        // Route the named "mailtrap" mailer to Laravel's in-memory array
        // transport so auth notifications never hit the network in tests.
        // MailtrapApiTest re-enables the real transport per test.
        // Use $app['config'] directly: config() would hit the previous app
        // instance mid-boot.
        $app['config']->set('mail.mailers.mailtrap', ['transport' => 'array']);

        return $app;
    }
}
