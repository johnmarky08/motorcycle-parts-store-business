<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages render the Vite tags; tests do not need compiled assets.
        $this->withoutVite();

        // The Svelte pages live in resources/js/Pages (capital P); skip the file-existence lookup of assertInertia.
        config(['inertia.testing.ensure_pages_exist' => false]);
    }
}
