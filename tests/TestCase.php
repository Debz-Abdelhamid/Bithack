<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on compiled front-end assets: CI runs the
        // suite without a Vite build, and any full-page render (panel layout,
        // theme, echo.js render hook) would otherwise 500 on the missing
        // manifest.
        $this->withoutVite();
    }
}
