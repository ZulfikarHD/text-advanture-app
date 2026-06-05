<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Guards the project standards from S-1.1.2: store time in UTC, render time in
 * Asia/Jakarta (WIB), render money in Rupiah, and run on a MySQL-8-compatible
 * engine. These are display/storage invariants the whole engine relies on.
 */
class ProjectStandardsTest extends TestCase
{
    public function test_timestamps_are_stored_in_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_times_are_displayed_in_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.display_timezone'));
    }

    public function test_currency_is_rendered_in_rupiah(): void
    {
        $this->assertSame('IDR', config('app.currency'));
    }

    public function test_database_uses_a_mysql_compatible_engine(): void
    {
        $this->assertContains(config('database.default'), ['mariadb', 'mysql']);
    }

    public function test_display_standards_are_shared_with_the_frontend(): void
    {
        $response = $this->get(route('home'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('standards.timezone', 'Asia/Jakarta')
            ->where('standards.locale', 'id-ID')
            ->where('standards.currency', 'IDR'),
        );
    }
}
