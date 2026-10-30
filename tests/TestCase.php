<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
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

    /** Un compte admin se connecte sur la garde « admin » (back-office), les autres sur « web ». */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($guard === null && $user instanceof User && $user->admin()->exists()) {
            $guard = 'admin';
        }

        return parent::actingAs($user, $guard);
    }
}
