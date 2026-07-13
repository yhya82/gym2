<?php

namespace Tests;

use Database\Seeders\ApplicationSettingSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Nearly every authenticated page reads ApplicationSetting::current()
     * (app/Http layout, sidebar, currency formatting) — RefreshDatabase
     * migrates the schema but never seeds it, so without this every such
     * page 404s via ModelNotFoundException. Seeded here rather than pulling
     * in the full DatabaseSeeder (admin user + demo plans), which is
     * business data individual tests should create for themselves.
     */
    protected $seeder = ApplicationSettingSeeder::class;
}
