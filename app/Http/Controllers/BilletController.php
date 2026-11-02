<?php

namespace App\Http\Controllers;

use App\Models\Inscription;
use App\Support\Billet;
use Illuminate\View\View;

/**
 * Billet en plein écran, ouvert depuis l'email « inscription validée » ou l'espace, sans
 * connexion (lien signé) : pratique sur téléphone le jour J. Seulement pour une inscription
 * validée ; prénom, course et dossard, rien d'autre.
 */
class BilletController extends Controller
{
    public function __invoke(Inscription $inscription): View
    {
        $inscription->load('participant.user', 'tarif.course', 'evenement');

        return view('billet', [
            'inscription' => $inscription,
            'valide' => $inscription->statut === Inscription::VALIDEE,
            'qr' => $inscription->statut === Inscription::VALIDEE ? Billet::svg($inscription, 260) : null,
            'jeton' => $inscription->statut === Inscription::VALIDEE ? Billet::jeton($inscription) : null,
        ]);
    }
}
