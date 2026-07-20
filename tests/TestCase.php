<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Panel views use @vite; tests should not depend on a built bundle
        // (public/build is git-ignored and produced by `npm run build`).
        $this->withoutVite();
    }
}

