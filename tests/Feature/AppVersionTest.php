<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppVersionTest extends TestCase
{
    public function test_application_version_is_displayed_on_the_login_page(): void
    {
        config(['app.version' => '1.2.3']);

        $this->get(route('login'))
            ->assertOk()
            ->assertSeeText('TontineApp · v1.2.3');
    }
}
