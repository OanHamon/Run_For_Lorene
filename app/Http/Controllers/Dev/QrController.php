<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Support\Billet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Banc d'essai des QR codes des billets, en local uniquement : quelques vrais billets de la base,
 * des codes piégés (signature fausse, inscription annulée, code étranger) et un lecteur (caméra ou
 * photo) qui montre ce que le serveur comprend du code lu. Rien n'est modifié en base.
 */
class QrController extends Controller
{
    public function index(): View
    {
        abort_unless(app()->isLocal(), 404);

        $evenement = Evenement::courant();
        $requete = Inscription::with('participant.user', 'tarif.course')
            ->when($evenement, fn ($q) => $q->where('id_evenement', $evenement->id_evenement));

        $billets = (clone $requete)->where('statut', Inscription::VALIDEE)->whereNotNull('dossard')
            ->orderBy('id_inscription')->limit(6)->get()
            ->map(fn (Inscription $i) => [
                'titre' => $i->participant->user->prenom.' '.$i->participant->user->nom,
                'detail' => $i->tarif->course->nom.' · dossard '.$i->dossard.($i->date_retrait ? ' · déjà retiré' : ''),
                'jeton' => Billet::jeton($i),
                'qr' => Billet::svg($i, 200),
                'lien' => Billet::url($i),
            ]);

        $pieges = collect([
            ['titre' => 'Signature fausse', 'detail' => 'Format RFL correct, signature inventée : doit être refusé.', 'jeton' => 'RFL-1-'.str_repeat('0', 20)],
            ['titre' => 'Code étranger', 'detail' => 'Un lien quelconque : le poste de retrait l\'ignore.', 'jeton' => 'https://example.org'],
        ]);
        if ($annulee = (clone $requete)->where('statut', Inscription::ANNULEE)->first()) {
            $pieges->prepend(['titre' => 'Inscription annulée', 'detail' => 'Billet authentique, mais l\'inscription est annulée.', 'jeton' => Billet::jeton($annulee)]);
        }

        return view('dev.qr', [
            'evenement' => $evenement,
            'billets' => $billets,
            'pieges' => $pieges->map(fn (array $p) => $p + ['qr' => Billet::qr($p['jeton'], 200)]),
        ]);
    }

    /** Ce que le serveur comprend d'un code lu : la même lecture que le poste de retrait. */
    public function verifier(Request $request): JsonResponse
    {
        abort_unless(app()->isLocal(), 404);

        $code = (string) $request->validate(['code' => ['required', 'string', 'max:2000']])['code'];

        if (! str_starts_with($code, 'RFL-')) {
            return response()->json(['ok' => false, 'message' => 'Ce n\'est pas un billet Run For Lorène : le poste de retrait ne réagit pas.', 'code' => $code]);
        }

        $inscription = Billet::lire($code)?->load('participant.user', 'tarif.course', 'evenement');
        if (! $inscription) {
            return response()->json(['ok' => false, 'message' => 'Billet non reconnu : signature fausse ou inscription introuvable.', 'code' => $code]);
        }

        $courant = Evenement::courant();
        $valide = $inscription->statut === Inscription::VALIDEE;

        return response()->json([
            'ok' => $valide && $courant?->id_evenement === $inscription->id_evenement,
            'message' => match (true) {
                ! $valide => 'Billet authentique, mais inscription '.mb_strtolower(Inscription::LIBELLES[$inscription->statut] ?? $inscription->statut).'.',
                $courant?->id_evenement !== $inscription->id_evenement => 'Billet authentique, mais pour un autre événement.',
                (bool) $inscription->date_retrait => 'Billet reconnu, dossard déjà retiré le '.$inscription->date_retrait->format('d/m').'.',
                default => 'Billet reconnu : le poste de retrait afficherait cette fiche.',
            },
            'code' => $code,
            'fiche' => [
                'nom' => $inscription->participant->user->prenom.' '.$inscription->participant->user->nom,
                'course' => $inscription->tarif->course->nom,
                'dossard' => $inscription->dossard,
                'evenement' => $inscription->evenement->nom,
            ],
        ]);
    }
}
