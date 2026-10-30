<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoLoginFillTest extends TestCase
{
    public function test_login_page_contains_local_demo_autofill(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('demoFill')
            ->assertSee('demo@ontrack.local')
            ->assertSee('DemoPass123!');
    }
}