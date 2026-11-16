<?php

namespace App\Http\Controllers;

use App\Models\CandidatureBenevole;
use App\Services\Benevoles\FichiersCandidature;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Photo de badge et autorisation parentale : le bénévole ne voit que les siennes. */
class CandidatureFichierController extends Controller
{
    public function moi(Request $request, string $fichier, FichiersCandidature $fichiers): Response
    {
        $candidature = CandidatureBenevole::duCompte($request->user());
        abort_unless($candidature !== null, 404);

        return $fichiers->reponse($candidature, $fichier);
    }
}
