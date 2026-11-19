<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithImpersonation;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithImpersonation;
}
