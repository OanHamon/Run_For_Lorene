<?php

namespace App\Http\Controllers;

use App\Services\Reveil;
use Illuminate\Http\JsonResponse;

/**
 * Adresse /reveil/{clé}, appelée chaque minute par un service externe (cron-job.org) : lance
 * les tâches planifiées et vide la file des emails (App\Services\Reveil). Sans clé configurée
 * (REVEIL_CLE) ou avec une mauvaise clé : 404, comme une page qui n'existe pas.
 */
class ReveilController extends Controller
{
    public function __invoke(string $cle, Reveil $reveil): JsonResponse
    {
        $attendue = (string) config('rfl.reveil_cle');
        abort_if($attendue === '' || ! hash_equals($attendue, $cle), 404);

        return response()->json(['ok' => true] + $reveil(), 200, ['Cache-Control' => 'no-store']);
    }
}
