<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les seeders écrivent les PDF des textes légaux : jamais dans le vrai dossier storage.
        Storage::fake('local');
    }
}
