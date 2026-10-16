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

        return parent::createApplication();
    }
}
