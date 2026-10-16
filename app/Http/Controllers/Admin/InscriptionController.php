<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\JournalAdmin;
use App\Models\QuestionSante;
use App\Notifications\RelanceInscription;
use App\Services\Admin\AnnulerInscription;
use App\Services\Admin\Journal;
use App\Services\Inscriptions\AttribuerDossard;
use App\Services\Inscriptions\Consentements;
use App\Services\Inscriptions\Questionnaires;
use App\Support\Csv;
use App\Support\EvenementAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suivi des inscriptions (lot 6) : liste filtrée, fiche, dossard, annulation, relance,
 * exports. Lecture dès le niveau 1, actions au niveau 2.
 */
class InscriptionController extends Controller
{
    public function __construct(private Journal $journal) {}

    public function index(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);

        return view('admin.inscriptions.index', [
            'evenement' => $evenement,
            'evenements' => Evenement::orderByDesc('date_evenement')->get(),
            'courses' => $evenement ? $evenement->courses()->orderBy('heure_depart')->get() : collect(),
            'inscriptions' => $evenement
                ? $this->requete($request, $evenement)->paginate(50)->withQueryString()
                : null,
        ]);
    }

    public function export(Request $request): Response
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $lignes = function () use ($request, $evenement) {
            foreach ($this->requete($request, $evenement)->lazy() as $i) {
                $p = $i->participant;
                $ligne = [
                    $i->id_inscription,
                    $p->user->nom,
                    $p->user->prenom,
                    $p->date_naissance->format('d/m/Y'),
                    $p->ageAu($evenement->date_evenement),
                    $p->sexe,
                    $p->user->email ?? $p->email_contact,
                    $p->user->telephone,
                    $p->responsable?->name,
                    $p->responsable?->email,
                    $i->tarif->course->nom,
                    $i->libelleStatut(),
                    $i->dossard,
                    (float) $i->prix_paye,
                    $i->declinaisons->map(fn ($d) => "{$d->declinaison->optionEvenement->libelle} {$d->declinaison->libelle}")->join(', '),
                    implode(', ', $i->documentsManquants()),
                    $i->urgence_nom,
                    $i->urgence_tel,
                    $i->commande?->date_commande?->format('d/m/Y H:i'),
                ];

                yield $this->sansOptions($ligne);
            }
        };

        return Csv::telecharger("inscrits-{$evenement->id_evenement}-".now()->format('Ymd'), $this->sansOptions([
            'N°', 'Nom', 'Prénom', 'Naissance', 'Âge le jour J', 'Sexe', 'Email', 'Téléphone', 'Responsable', 'Email du responsable',
            'Course', 'Statut', 'Dossard', 'Prix payé', 'Options', 'Documents manquants', 'Contact d\'urgence', 'Tél. d\'urgence', 'Commande',
        ]), $lignes());
    }

    /** Retire la colonne « Options » (15e) de l'export tant que les options sont masquées. */
    private function sansOptions(array $ligne): array
    {
        if (! config('rfl.options')) {
            array_splice($ligne, 14, 1);
        }

        return $ligne;
    }

    /** Liste des dossards attribués, par course puis numéro (retrait des dossards, chronométrage du jour J). */
    public function exportDossards(Request $request): Response
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $inscriptions = Inscription::with('participant.user', 'tarif.course')
            ->where('id_evenement', $evenement->id_evenement)
            ->where('statut', Inscription::VALIDEE)
            ->whereNotNull('dossard')
            ->orderBy('dossard')
            ->lazy();

        $lignes = function () use ($inscriptions, $evenement) {
            foreach ($inscriptions as $i) {
                yield [
                    $i->dossard,
                    $i->participant->user->nom,
                    $i->participant->user->prenom,
                    $i->participant->sexe,
                    $i->participant->ageAu($evenement->date_evenement),
                    $i->tarif->course->nom,
                ];
            }
        };

        return Csv::telecharger("dossards-{$evenement->id_evenement}-".now()->format('Ymd'), [
            'Dossard', 'Nom', 'Prénom', 'Sexe', 'Âge le jour J', 'Course',
        ], $lignes());
    }

    public function show(Inscription $inscription, Consentements $consentements, Questionnaires $questionnaires): View
    {
        $inscription->load('participant.user', 'participant.responsable', 'tarif.course', 'evenement', 'commande.paiements.remboursements', 'commande.don', 'commande.user', 'declinaisons.declinaison.optionEvenement');

        return view('admin.inscriptions.show', [
            'i' => $inscription,
            'historique' => $consentements->historique($inscription),
            'sante' => $questionnaires->dernier($inscription, QuestionSante::SANTE),
            'decharge' => $questionnaires->dernier($inscription, QuestionSante::DECHARGE),
            'journal' => JournalAdmin::with('auteur')->where('cible_type', 'inscription')->where('cible_id', $inscription->id_inscription)->orderByDesc('id_journal')->get(),
            'titulaire' => $inscription->titulaire(),
        ]);
    }

    /** Dossard saisi à la main : dans la plage de la course, unique pour l'événement. */
    public function dossard(Request $request, Inscription $inscription): RedirectResponse
    {
        abort_unless($inscription->statut === Inscription::VALIDEE, 403, 'Seule une inscription validée porte un dossard.');

        $course = $inscription->tarif->course;
        $regles = ['required', 'integer', 'min:1', 'max:65535',
            Rule::unique('inscriptions', 'dossard')->where('id_evenement', $inscription->id_evenement)->ignore($inscription->id_inscription, 'id_inscription'),
        ];

        if ($course->dossard_debut !== null) {
            $regles[] = "between:{$course->dossard_debut},{$course->dossard_fin}";
        }

        $donnees = $request->validate(['dossard' => $regles], [
            'dossard.unique' => 'Ce dossard est déjà porté par une autre inscription de l\'événement.',
            'dossard.between' => "Le dossard doit être dans la plage de la course ({$course->dossard_debut}–{$course->dossard_fin}).",
        ]);

        $avant = $inscription->dossard;
        $inscription->forceFill(['dossard' => (int) $donnees['dossard']])->save();
        $this->journal->noter('inscription.dossard', $inscription, ['avant' => $avant, 'après' => $inscription->dossard]);

        return back()->with('statut', "Dossard n° {$inscription->dossard} enregistré.");
    }

    /** Premier numéro libre de la plage, pour une inscription validée restée sans dossard (plage pleine). */
    public function attribuerDossard(Inscription $inscription, AttribuerDossard $attribuer): RedirectResponse
    {
        abort_unless($inscription->statut === Inscription::VALIDEE && $inscription->dossard === null, 403);

        $numero = $attribuer($inscription);

        if ($numero === null) {
            return back()->with('erreur', 'La plage de dossards de la course est pleine ou absente : élargis-la dans le paramétrage de la course.');
        }

        $this->journal->noter('inscription.dossard', $inscription, ['avant' => null, 'après' => $numero, 'mode' => 'automatique']);

        return back()->with('statut', "Dossard n° {$numero} attribué.");
    }

    public function annuler(Inscription $inscription, AnnulerInscription $annuler): RedirectResponse
    {
        $statut = $inscription->libelleStatut();

        try {
            $resultat = $annuler($inscription);
        } catch (ValidationException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        $this->journal->noter('inscription.annulation', $inscription, ['statut précédent' => $statut, 'dossard libéré' => $resultat['dossard']]);

        $message = 'Inscription annulée.';
        if ($resultat['autres'] > 0) {
            $message .= " Les {$resultat['autres']} autre(s) inscription(s) de la commande redeviennent des brouillons à régler.";
        }
        if (in_array($statut, [Inscription::LIBELLES[Inscription::VALIDEE], Inscription::LIBELLES[Inscription::DOSSIER_INCOMPLET]], true)) {
            $message .= ' Pense au remboursement éventuel, depuis la commande.';
        }

        return back()->with('statut', $message);
    }

    public function relancer(Inscription $inscription): RedirectResponse
    {
        abort_unless(in_array($inscription->statut, [Inscription::EN_ATTENTE_PAIEMENT, Inscription::DOSSIER_INCOMPLET], true), 403);

        $titulaire = $inscription->titulaire();

        if (! $titulaire?->email) {
            return back()->with('erreur', 'Aucune adresse email pour cette inscription.');
        }

        // Une relance par inscription et par heure : évite les doubles clics et l'acharnement.
        $cle = "relance-inscription:{$inscription->id_inscription}";
        if (RateLimiter::tooManyAttempts($cle, 1)) {
            return back()->with('erreur', 'Une relance vient déjà de partir pour cette inscription. Réessaie dans une heure.');
        }
        RateLimiter::hit($cle, 3600);

        $titulaire->notify(new RelanceInscription($inscription));
        $this->journal->noter('inscription.relance', $inscription, ['statut' => $inscription->libelleStatut(), 'destinataire' => $titulaire->email]);

        return back()->with('statut', "Relance envoyée à {$titulaire->email}.");
    }

    /** Requête de la liste, avec la recherche et les filtres (course, statut, mineur, documents manquants). */
    private function requete(Request $request, Evenement $evenement): Builder
    {
        $statut = $request->query('statut');

        return Inscription::with('participant.user', 'participant.responsable', 'tarif.course', 'commande', 'evenement', 'declinaisons.declinaison.optionEvenement')
            ->where('inscriptions.id_evenement', $evenement->id_evenement)
            ->when(
                array_key_exists((string) $statut, Inscription::LIBELLES),
                fn ($q) => $q->where('statut', $statut),
                fn ($q) => $q->where('statut', '!=', Inscription::BROUILLON),
            )
            ->when($request->filled('course'), fn ($q) => $q->whereIn('id_tarif', Course::whereKey($request->integer('course'))->firstOrNew()->tarifs()->select('id_tarif')))
            ->when($request->boolean('mineur'), fn ($q) => $q->mineurs($evenement))
            ->when($request->boolean('documents'), fn ($q) => $q->avecDocumentsManquants($evenement))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = trim((string) $request->query('q'));
                $q->where(function ($r) use ($terme) {
                    if (ctype_digit($terme)) {
                        $r->where('dossard', (int) $terme)->orWhere('id_inscription', (int) $terme);
                    }
                    $r->orWhereHas('participant.user', fn ($u) => $u->where('nom', 'like', "%{$terme}%")
                        ->orWhere('prenom', 'like', "%{$terme}%")
                        ->orWhere('email', 'like', "%{$terme}%")
                        ->orWhereRaw("concat(prenom, ' ', nom) like ?", ["%{$terme}%"]))
                        ->orWhereHas('participant.responsable', fn ($u) => $u->where('email', 'like', "%{$terme}%"));
                });
            })
            ->orderByDesc('id_inscription');
    }
}
