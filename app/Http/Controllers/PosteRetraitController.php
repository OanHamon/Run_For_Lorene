<?php

namespace App\Http\Controllers;

use App\Models\Inscription;
use App\Models\PosteRetrait;
use App\Services\Retrait\Retraits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Poste de retrait des dossards pour les bénévoles, sans compte : le lien (jeton secret) créé
 * par un admin n'ouvre que l'écran de retrait de son événement, aux dates prévues, tant qu'il
 * n'est pas révoqué. Le bénévole donne son prénom à l'ouverture : chaque remise le garde.
 */
class PosteRetraitController extends Controller
{
    public function __construct(private Retraits $retraits) {}

    public function show(Request $request, string $jeton): View
    {
        $poste = $this->poste($jeton);

        if (! $poste) {
            return view('retrait.invalide');
        }

        if (! $this->benevole($request, $poste)) {
            return view('retrait.benevole', ['poste' => $poste, 'jeton' => $jeton]);
        }

        return view('retrait.poste', [
            'poste' => $poste,
            'jeton' => $jeton,
            'benevole' => $this->benevole($request, $poste),
            'ecran' => $this->ecran($request, $poste, $jeton),
        ]);
    }

    private function benevole(Request $request, PosteRetrait $poste): ?string
    {
        return $request->session()->get("retrait.benevole.{$poste->id_poste}");
    }

    public function identifier(Request $request, string $jeton): RedirectResponse|View
    {
        $poste = $this->poste($jeton) ?? abort(404);
        $prenom = $request->validate(['prenom' => ['required', 'string', 'max:60']], ['prenom.required' => 'Indique ton prénom.'])['prenom'];
        $request->session()->put("retrait.benevole.{$poste->id_poste}", trim($prenom));

        return redirect()->route('retrait.poste', $jeton);
    }

    public function scan(Request $request, string $jeton): RedirectResponse
    {
        $poste = $this->posteDuBenevole($request, $jeton);
        $resultat = $this->retraits->parBillet($poste->evenement, (string) $request->input('jeton'));

        return $resultat['inscription']
            ? redirect()->route('retrait.poste', $jeton)->with('retrait.trouve', $resultat['inscription']->id_inscription)
            : redirect()->route('retrait.poste', $jeton)->with('retrait.erreur', $resultat['erreur']);
    }

    public function remettre(Request $request, string $jeton, int $inscription): RedirectResponse
    {
        $poste = $this->posteDuBenevole($request, $jeton);
        $i = $this->retraits->trouver($poste->evenement, $inscription) ?? abort(404);

        return $this->retour($jeton, $i, $this->retraits->remettre($i, $this->nom($request, $poste), $poste));
    }

    public function annuler(Request $request, string $jeton, int $inscription): RedirectResponse
    {
        $poste = $this->posteDuBenevole($request, $jeton);
        $i = $this->retraits->trouver($poste->evenement, $inscription) ?? abort(404);

        return $this->retour($jeton, $i, $this->retraits->annuler($i, $this->nom($request, $poste), $poste));
    }

    private function poste(string $jeton): ?PosteRetrait
    {
        $poste = PosteRetrait::parJeton($jeton);

        return $poste?->utilisable() ? $poste : null;
    }

    private function posteDuBenevole(Request $request, string $jeton): PosteRetrait
    {
        $poste = $this->poste($jeton) ?? abort(403, 'Ce poste de retrait n\'est plus ouvert.');
        abort_unless($this->benevole($request, $poste), 403);

        return $poste;
    }

    private function nom(Request $request, PosteRetrait $poste): string
    {
        return $this->benevole($request, $poste).' (poste '.$poste->libelle.')';
    }

    private function retour(string $jeton, Inscription $i, array $resultat): RedirectResponse
    {
        return redirect()->route('retrait.poste', $jeton)
            ->with('retrait.trouve', $i->id_inscription)
            ->with($resultat['ok'] ? 'retrait.succes' : 'retrait.erreur', $resultat['message']);
    }

    private function ecran(Request $request, PosteRetrait $poste, string $jeton): array
    {
        return [
            'evenement' => $poste->evenement,
            'compteur' => $this->retraits->compteur($poste->evenement),
            'terme' => (string) $request->query('q'),
            'resultats' => $this->retraits->rechercher($poste->evenement, (string) $request->query('q')),
            'trouve' => session('retrait.trouve') ? $this->retraits->trouver($poste->evenement, (int) session('retrait.trouve')) : null,
            'routes' => [
                'recherche' => route('retrait.poste', $jeton),
                'scan' => route('retrait.scan', $jeton),
                'remettre' => fn (Inscription $i) => route('retrait.remettre', [$jeton, $i->id_inscription]),
                'annuler' => fn (Inscription $i) => route('retrait.annuler', [$jeton, $i->id_inscription]),
            ],
        ];
    }
}
