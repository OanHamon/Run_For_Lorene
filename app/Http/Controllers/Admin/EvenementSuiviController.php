<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sélecteur d'événement de la barre latérale : retient l'événement suivi en session (lu par
 * EvenementAdmin::choisi) et revient sur la page d'où l'on vient, sans son ancien filtre.
 */
class EvenementSuiviController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $evenement = Evenement::findOrFail($request->integer('evenement'));
        $request->session()->put('admin.evenement', $evenement->id_evenement);

        // Retour sur la page de l'admin d'où vient la demande (pas de redirection ouverte).
        $precedente = url()->previous();
        if (! Str::startsWith($precedente, url('admin'))) {
            return redirect()->route('admin.tableau');
        }

        $parties = parse_url($precedente);
        parse_str($parties['query'] ?? '', $requete);
        unset($requete['evenement'], $requete['page']);

        return redirect()->to(url($parties['path'] ?? '/admin').($requete ? '?'.http_build_query($requete) : ''));
    }
}
