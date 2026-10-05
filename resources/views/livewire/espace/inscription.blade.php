<?php

use App\Models\Commande;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\QuestionnaireSante;
use App\Models\QuestionSante;
use App\Models\TexteLegal;
use App\Services\Inscriptions\Consentements;
use App\Services\Inscriptions\MettreAJourStatut;
use App\Services\Inscriptions\Questionnaires;
use App\Services\Inscriptions\SupprimerInscription;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

/*
 * Détail d'une inscription dans l'espace participant : timeline Informations personnelles →
 * Décharge et consentements → Santé → Paiement, modification des informations du participant,
 * et suppression tant que rien n'est payé.
 */
new #[Layout('components.espace-shell', ['title' => 'Mon inscription'])] class extends Component {
    #[Locked]
    public int $inscriptionId;

    public bool $edition = false;

    public string $prenom = '';

    public string $nom = '';

    public string $dateNaissance = '';

    public string $sexe = '';

    public string $telephone = '';

    public string $adresse = '';

    public string $codePostal = '';

    public string $ville = '';

    public string $urgenceNom = '';

    public string $urgenceTel = '';

    public bool $droitImage = false;

    /** @var array<int, bool> affirmations de la décharge cochées, par id_question */
    public array $reponsesDecharge = [];

    public bool $accepteDecharge = false;

    /** @var array<int, string> 'oui' | 'non' par id_question */
    public array $reponsesSante = [];

    public bool $attestationSante = false;

    public function mount(Inscription $inscription): void
    {
        $participant = $inscription->participant;

        // Visible par le participant lui-même ou par le compte responsable d'un participant géré.
        abort_unless(
            $participant && ($participant->id_user === Auth::id() || $participant->id_responsable === Auth::id()),
            404
        );

        $this->inscriptionId = $inscription->id_inscription;
        $this->remplir();
    }

    private function remplir(): void
    {
        $i = $this->inscription;
        $p = $i->participant;

        $this->prenom = $p->user->prenom;
        $this->nom = $p->user->nom;
        $this->dateNaissance = $p->date_naissance->format('Y-m-d');
        $this->sexe = (string) $p->sexe;
        $this->telephone = $this->telephoneLisible($p->user->telephone);
        $this->adresse = (string) $p->adresse;
        $this->codePostal = (string) $p->code_postal;
        $this->ville = (string) $p->ville;
        $this->urgenceNom = (string) $i->urgence_nom;
        $this->urgenceTel = $this->telephoneLisible($i->urgence_tel);
        $this->droitImage = app(Consentements::class)->dernierDroitImage($i) ?? false;
    }

    #[Computed]
    public function inscription(): Inscription
    {
        return Inscription::with('participant.user', 'tarif.course', 'evenement', 'commande.paiements', 'commande.don', 'commande.inscriptions')
            ->findOrFail($this->inscriptionId);
    }

    #[Computed]
    public function estMoi(): bool
    {
        return $this->inscription->id_participant === Auth::id();
    }

    /** Les infos restent modifiables jusqu'au jour de l'événement, sauf inscription annulée. */
    #[Computed]
    public function modifiable(): bool
    {
        return $this->inscription->statut !== Inscription::ANNULEE
            && today()->lte($this->inscription->evenement->date_evenement);
    }

    #[Computed]
    public function supprimable(): bool
    {
        return in_array($this->inscription->statut, [Inscription::BROUILLON, Inscription::EN_ATTENTE_PAIEMENT], true);
    }

    #[Computed]
    public function historique()
    {
        return app(Consentements::class)->historique($this->inscription);
    }

    #[Computed]
    public function questionnaireSante(): ?QuestionnaireSante
    {
        return app(Questionnaires::class)->dernier($this->inscription, QuestionSante::SANTE);
    }

    #[Computed]
    public function questionnaireDecharge(): ?QuestionnaireSante
    {
        return app(Questionnaires::class)->dernier($this->inscription, QuestionSante::DECHARGE);
    }

    #[Computed]
    public function questionsSante()
    {
        return app(Questionnaires::class)->questions(QuestionSante::SANTE);
    }

    #[Computed]
    public function questionsDecharge()
    {
        return app(Questionnaires::class)->questions(QuestionSante::DECHARGE);
    }

    /** Décharge pas encore faite, et inscription encore modifiable. */
    #[Computed]
    public function dechargeARemplir(): bool
    {
        return $this->modifiable && $this->inscription->dechargeManquante();
    }

    /** Questionnaire de santé demandé, pas encore rempli, et encore modifiable. */
    #[Computed]
    public function santeARemplir(): bool
    {
        return $this->modifiable
            && $this->inscription->tarif->course->questionnaire_requis
            && $this->questionnaireSante === null
            && $this->questionsSante->isNotEmpty();
    }

    #[Computed]
    public function paiementReussi(): ?Paiement
    {
        return $this->inscription->commande?->paiements->where('statut', Paiement::REUSSI)->sortByDesc('date_paiement')->first();
    }

    /** Étapes de la timeline : état 'fait' | 'en_cours' | 'a_faire' | 'non_requis'. */
    #[Computed]
    public function etapes(): array
    {
        $i = $this->inscription;
        $paye = in_array($i->statut, [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET], true);

        $sante = match (true) {
            ! $i->tarif->course->questionnaire_requis => 'non_requis',
            $i->questionnaireManquant() => $paye ? 'a_faire' : 'en_cours',
            default => 'fait',
        };

        $paiement = match (true) {
            $paye => 'fait',
            $i->statut === Inscription::EN_ATTENTE_PAIEMENT => 'en_cours',
            $i->statut === Inscription::ANNULEE => 'non_requis',
            default => 'a_faire',
        };

        return [
            'infos' => 'fait',
            'decharge' => $i->consentementsComplets() ? 'fait' : 'a_faire',
            'sante' => $sante,
            'paiement' => $paiement,
        ];
    }

    public function euros(float $montant): string
    {
        return number_format($montant, fmod($montant, 1.0) == 0.0 ? 0 : 2, ',', ' ').' €';
    }

    public function telephoneLisible(?string $tel): string
    {
        return $tel ? trim(chunk_split($tel, 2, ' ')) : '';
    }

    // ------------------------------------------------------------------

    public function modifier(): void
    {
        abort_unless($this->modifiable, 403);
        $this->remplir();
        $this->resetErrorBag();
        $this->edition = true;
    }

    public function annuler(): void
    {
        $this->remplir();
        $this->resetErrorBag();
        $this->edition = false;
    }

    public function enregistrer(): void
    {
        abort_unless($this->modifiable, 403);

        $this->telephone = $this->normaliserTelephone($this->telephone);
        $this->urgenceTel = $this->normaliserTelephone($this->urgenceTel);
        $this->codePostal = preg_replace('/\s+/', '', $this->codePostal);

        $telephone = ['nullable', 'regex:/^0[1-9]\d{8}$/'];

        $this->validate([
            'prenom' => 'required|string|max:100',
            'nom' => 'required|string|max:100',
            'dateNaissance' => 'required|date_format:Y-m-d|before:today|after:1900-01-01',
            'sexe' => 'nullable|in:femme,homme,autre',
            'telephone' => $telephone,
            'adresse' => 'nullable|string|max:255',
            'codePostal' => ['nullable', 'regex:/^\d{5}$/'],
            'ville' => 'nullable|string|max:100',
            'urgenceNom' => 'nullable|string|max:150|required_with:urgenceTel',
            'urgenceTel' => [...$telephone, 'required_with:urgenceNom'],
        ], [
            'telephone.regex' => 'Numéro français attendu, par exemple 06 12 34 56 78.',
            'urgenceTel.regex' => 'Numéro français attendu, par exemple 06 12 34 56 78.',
            'codePostal.regex' => 'Le code postal contient 5 chiffres.',
            'urgenceNom.required_with' => 'Indique le nom de la personne à prévenir.',
            'urgenceTel.required_with' => 'Indique le numéro de la personne à prévenir.',
            'dateNaissance.before' => 'La date de naissance doit être dans le passé.',
        ], [
            'prenom' => 'prénom',
            'dateNaissance' => 'date de naissance',
            'codePostal' => 'code postal',
        ]);

        $i = $this->inscription;
        $participant = $i->participant;
        $course = $i->tarif->course;

        // Une nouvelle date de naissance ne doit pas sortir le participant de la tranche d'âge de son épreuve.
        if ($this->dateNaissance !== $participant->date_naissance->format('Y-m-d')) {
            $age = (int) \Illuminate\Support\Carbon::parse($this->dateNaissance)->diffInYears($i->evenement->date_evenement);

            if (! $course->accepteAge($age)) {
                $this->addError('dateNaissance', "Avec cette date, {$this->prenom} aurait {$age} ans le jour J : ce n'est pas compatible avec {$course->nom}. Si c'est bien la bonne date, contacte l'association.");

                return;
            }
        }

        DB::transaction(function () use ($i, $participant) {
            $participant->user->forceFill([
                'prenom' => trim($this->prenom),
                'nom' => trim($this->nom),
                'telephone' => $this->telephone ?: null,
            ])->save();

            $participant->forceFill([
                'date_naissance' => $this->dateNaissance,
                'sexe' => $this->sexe ?: null,
                'adresse' => trim($this->adresse) ?: null,
                'code_postal' => $this->codePostal ?: null,
                'ville' => trim($this->ville) ?: null,
            ])->save();

            $i->forceFill([
                'urgence_nom' => trim($this->urgenceNom) ?: null,
                'urgence_tel' => $this->urgenceTel ?: null,
            ])->save();
        });

        unset($this->inscription);
        $this->edition = false;
        session()->flash('statut', 'Informations enregistrées.');
    }

    public function updatedDroitImage(bool $valeur): void
    {
        abort_unless($this->modifiable, 403);

        app(Consentements::class)->droitImage($this->inscription, $valeur, Auth::id(), request()->ip());

        unset($this->historique);
        session()->flash('statut', $valeur ? "Droit à l'image accordé." : "Droit à l'image retiré.");
    }

    public function enregistrerSante(): void
    {
        abort_unless($this->santeARemplir, 403);

        $regles = ['attestationSante' => 'accepted'];
        foreach ($this->questionsSante as $q) {
            $regles["reponsesSante.{$q->id_question}"] = 'required|in:'.implode(',', Questionnaires::REPONSES);
        }

        $this->validate($regles, [
            'reponsesSante.*.required' => 'Réponds par oui ou par non.',
            'reponsesSante.*.in' => 'Réponds par oui ou par non.',
            'attestationSante.accepted' => "Coche l'attestation pour valider le questionnaire.",
        ]);

        $this->apresDocument('Questionnaire de santé enregistré', function (Inscription $i) {
            app(Questionnaires::class)->enregistrer($i, QuestionSante::SANTE, $this->reponsesSante, $this->attestationSante, Auth::id());
        });

        $this->reset('reponsesSante', 'attestationSante');
    }

    public function enregistrerDecharge(): void
    {
        abort_unless($this->dechargeARemplir, 403);

        $regles = ['accepteDecharge' => 'accepted'];
        foreach ($this->questionsDecharge as $q) {
            $regles["reponsesDecharge.{$q->id_question}"] = 'accepted';
        }

        $this->validate($regles, [
            'reponsesDecharge.*.accepted' => Questionnaires::AFFIRMATION_REQUISE,
            'accepteDecharge.accepted' => "Coche « J'ai lu et j'approuve » pour valider la décharge.",
        ]);

        $this->apresDocument('Décharge enregistrée', function (Inscription $i) {
            app(Questionnaires::class)->remplirDecharge($i, $this->reponsesDecharge, Auth::id(), request()->ip());
        });

        $this->reset('reponsesDecharge', 'accepteDecharge');
    }

    /** Enregistre un document puis, si l'inscription payée l'attendait, la valide (avec son dossard). */
    private function apresDocument(string $message, callable $enregistrer): void
    {
        $i = $this->inscription;
        $avant = $i->statut;

        DB::transaction(function () use ($i, $enregistrer) {
            $enregistrer($i);

            if ($i->statut === Inscription::DOSSIER_INCOMPLET) {
                app(MettreAJourStatut::class)($i);
            }
        });

        unset($this->inscription, $this->questionnaireSante, $this->questionnaireDecharge, $this->santeARemplir, $this->dechargeARemplir, $this->etapes, $this->historique);

        $manquants = $this->inscription->documentsManquants();

        session()->flash('statut', match (true) {
            $avant === Inscription::DOSSIER_INCOMPLET && $this->inscription->statut === Inscription::VALIDEE => "{$message} : l'inscription est validée.",
            $manquants !== [] => "{$message}. Il reste ".implode(' et ', $manquants).' à remplir.',
            default => "{$message}.",
        });
    }

    public function supprimer()
    {
        abort_unless($this->supprimable, 403);

        try {
            $resultat = app(SupprimerInscription::class)($this->inscription);
        } catch (ValidationException $e) {
            $this->addError('suppression', $e->validator->errors()->first());

            return null;
        }

        $message = "L'inscription de {$this->prenom} est supprimée.";

        if ($resultat['autres'] > 0) {
            // Le don de la commande abandonnée est repris dans le tunnel pour les autres inscriptions.
            if ($resultat['don'] > 0) {
                session(['tunnel.don' => (string) $resultat['don']]);
            }
            $message .= $resultat['autres'] > 1
                ? " Les {$resultat['autres']} autres inscriptions de la même commande restent à payer : reprends-les depuis « Reprendre »."
                : " L'autre inscription de la même commande reste à payer : reprends-la depuis « Reprendre ».";
        }

        session()->flash('statut', $message);

        return $this->redirectRoute('espace');
    }

    private function normaliserTelephone(string $tel): string
    {
        $tel = preg_replace('/[\s.\-()]/', '', trim($tel));

        return preg_replace('/^(\+|00)33/', '0', $tel);
    }
}; ?>

@php
    $i = $this->inscription;
    $p = $i->participant;
    $course = $i->tarif->course;
    $etapes = $this->etapes;
    $faites = collect($etapes)->filter(fn ($e) => in_array($e, ['fait', 'non_requis'], true))->count();
    $badges = [
        Inscription::VALIDEE => 'bg-aqua-clair text-canard',
        Inscription::DOSSIER_INCOMPLET => 'bg-moutarde/20 text-encre',
        Inscription::EN_ATTENTE_PAIEMENT => 'bg-moutarde/20 text-encre',
    ];
    $libellesTextes = \App\Http\Controllers\TexteLegalController::LIBELLES;
    $etatLibelle = ['fait' => 'Fait', 'en_cours' => 'En attente', 'a_faire' => 'À faire', 'non_requis' => 'Non requis'];
    $etatTexte = [
        'fait' => 'text-canard',
        'en_cours' => 'text-[#8A6A0B]',
        'a_faire' => 'text-[#8A6A0B]',
        'non_requis' => 'text-gris-texte',
    ];
    $sexes = ['femme' => 'Femme', 'homme' => 'Homme', 'autre' => 'Autre'];
    $bouton = 'inline-flex items-center justify-center rounded-lg bg-canard px-4 py-2 text-sm font-medium text-white hover:bg-profond focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-canard disabled:opacity-40';
    $boutonSecondaire = 'inline-flex items-center justify-center rounded-lg border border-bordure bg-white px-4 py-2 text-sm font-medium text-encre hover:border-canard focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-canard';
    $champ = 'h-11 w-full rounded-lg border border-bordure bg-white px-3 text-encre placeholder:text-gris-texte/60 focus:border-canard focus:ring-2 focus:ring-canard/20 focus:outline-none';
    $label = 'mb-1.5 block text-sm font-medium text-encre';
    $dt = 'text-xs font-semibold tracking-wide text-gris-texte uppercase';
@endphp

<div class="max-w-6xl">
    <a href="{{ route('espace') }}" class="text-sm rfl-lien">← Tableau de bord</a>

    <div class="mt-4 mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-1 text-sm text-gris-texte">Espace participant / Inscription</p>
            <h1 class="font-display text-3xl font-bold text-encre">{{ $p->user->prenom }} {{ $p->user->nom }}</h1>
            <p class="mt-1 text-sm text-gris-texte">{{ $course->nom }} &middot; {{ $i->evenement->nom }} &middot; {{ $i->evenement->date_evenement->translatedFormat('l j F Y') }}</p>
        </div>
        <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $badges[$i->statut] ?? 'bg-bordure text-gris-texte' }}">{{ $i->statut === Inscription::BROUILLON ? 'Non terminée' : $i->libelleStatut() }}</span>
    </div>

    @if (session('statut'))
        <p class="mb-6 rfl-alerte-info text-sm text-canard" role="status">{{ session('statut') }}</p>
    @endif

    @if ($i->statut !== Inscription::ANNULEE && ($manquants = $i->documentsManquants()))
        <div class="mb-6 rounded-lg border-2 border-moutarde bg-moutarde/15 px-5 py-4" role="alert">
            <p class="font-display font-bold text-encre">
                {{ $this->estMoi ? 'Tu ne pourras pas' : "{$p->user->prenom} ne pourra pas" }} prendre le départ sans {{ implode(' et ', $manquants) }}.
            </p>
            <p class="mt-1 text-sm text-encre">
                À remplir ci-dessous avant le {{ $i->evenement->date_evenement->copy()->subDays(15)->translatedFormat('j F Y') }}.
                @if ($i->statut === Inscription::DOSSIER_INCOMPLET) L'inscription sera validée et le dossard attribué dès que tout sera rempli. @endif
            </p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
        {{-- Récapitulatif et action principale --}}
        <aside class="order-first space-y-4 lg:order-last lg:sticky lg:top-8">
            <div class="rounded-lg border border-bordure bg-white">
                @if ($i->dossard)
                    <div class="border-b border-bordure px-5 py-4 text-center">
                        <p class="{{ $dt }}">Dossard</p>
                        <p class="mt-1 font-display text-4xl font-bold tracking-tight text-canard"><span class="sr-only">Dossard n° </span>{{ $i->dossard }}</p>
                    </div>
                @endif
                <dl class="divide-y divide-bordure text-sm">
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-gris-texte">Épreuve</dt>
                        <dd class="text-right font-medium text-encre">{{ $course->nom }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-gris-texte">Départ</dt>
                        <dd class="font-medium text-encre">{{ \Illuminate\Support\Carbon::parse($course->heure_depart)->format('G\hi') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-gris-texte">Âge le jour J</dt>
                        <dd class="font-medium text-encre">{{ $p->ageAu($i->evenement->date_evenement) }} ans</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-gris-texte">Prix</dt>
                        <dd class="font-medium text-encre">{{ $i->statut === Inscription::BROUILLON ? '–' : $this->euros((float) $i->prix_paye) }}</dd>
                    </div>
                    @if ($i->id_commande)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <dt class="text-gris-texte">Commande</dt>
                            <dd class="font-medium text-encre">n° {{ $i->id_commande }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($etapes['paiement'] === 'en_cours')
                    <form method="POST" action="{{ route('inscription.payer', $i->commande) }}" class="border-t border-bordure p-4">
                        @csrf
                        <button type="submit" class="{{ $bouton }} w-full">Payer {{ $this->euros((float) $i->commande->montant_total) }}</button>
                    </form>
                @elseif ($i->statut === Inscription::BROUILLON)
                    <div class="border-t border-bordure p-4">
                        <a href="{{ route('inscription.tunnel') }}" class="{{ $bouton }} w-full">Reprendre l'inscription</a>
                    </div>
                @endif
            </div>
        </aside>

        {{-- Suivi --}}
        <div class="min-w-0 space-y-6">
            <section class="rounded-lg border border-bordure bg-white" aria-labelledby="titre-suivi">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5 pb-4">
                    <h2 id="titre-suivi" class="font-display font-bold text-encre">Suivi de l'inscription</h2>
                    <p class="text-sm text-gris-texte">{{ $faites }} étape{{ $faites > 1 ? 's' : '' }} sur 4</p>
                </div>
                <div class="mx-6 mb-2 h-1.5 overflow-hidden rounded-full bg-bordure" aria-hidden="true">
                    <div class="h-full rounded-full bg-canard" style="width: {{ $faites / 4 * 100 }}%"></div>
                </div>

                <ol>
                    {{-- 1. Informations personnelles --}}
                    <li class="relative border-t border-bordure px-6 py-5 first:border-t-0">
                        <span class="absolute top-11 -bottom-5 left-[35px] w-0.5 bg-bordure" aria-hidden="true"></span>
                        <div class="absolute top-5 left-6"><x-timeline-puce :etat="$etapes['infos']" /></div>

                        <div class="pl-10">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                    <h3 class="font-display font-bold text-encre">Informations personnelles</h3>
                                    <span class="shrink-0 text-sm font-medium {{ $etatTexte[$etapes['infos']] }}">{{ $etatLibelle[$etapes['infos']] }}</span>
                                </div>
                                @if ($this->modifiable && ! $edition)
                                    <button type="button" wire:click="modifier" class="{{ $boutonSecondaire }}">Modifier</button>
                                @endif
                            </div>

                            @if (! $edition)
                                <dl class="mt-4 grid grid-cols-1 gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
                                    <div><dt class="{{ $dt }}">Date de naissance</dt><dd class="mt-0.5 text-encre">{{ $p->date_naissance->translatedFormat('j F Y') }}</dd></div>
                                    <div><dt class="{{ $dt }}">Sexe</dt><dd class="mt-0.5 {{ $p->sexe ? 'text-encre' : 'text-gris-texte' }}">{{ $sexes[$p->sexe] ?? 'Non renseigné' }}</dd></div>
                                    <div><dt class="{{ $dt }}">Téléphone</dt><dd class="mt-0.5 {{ $p->user->telephone ? 'text-encre' : 'text-gris-texte' }}">{{ $this->telephoneLisible($p->user->telephone) ?: 'Non renseigné' }}</dd></div>
                                    @php($adresse = collect([$p->adresse, trim($p->code_postal.' '.$p->ville)])->filter()->join(', '))
                                    <div><dt class="{{ $dt }}">Adresse</dt><dd class="mt-0.5 {{ $adresse ? 'text-encre' : 'text-gris-texte' }}">{{ $adresse ?: 'Non renseignée' }}</dd></div>
                                    <div class="sm:col-span-2">
                                        <dt class="{{ $dt }}">Personne à prévenir en cas d'urgence</dt>
                                        <dd class="mt-0.5 {{ $i->urgence_nom ? 'text-encre' : 'text-gris-texte' }}">{{ $i->urgence_nom ? $i->urgence_nom.' · '.$this->telephoneLisible($i->urgence_tel) : 'Non renseignée' }}</dd>
                                    </div>
                                </dl>
                                @if (! $i->urgence_nom && $this->modifiable)
                                    <p class="mt-4 rfl-alerte py-3 text-sm text-encre">Pense à indiquer une personne à prévenir : c'est utile aux bénévoles le jour J. <button type="button" wire:click="modifier" class="rfl-lien">L'ajouter</button></p>
                                @endif
                            @else
                                <form wire:submit="enregistrer" class="mt-4" novalidate>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <label for="prenom" class="{{ $label }}">Prénom</label>
                                            <input wire:model="prenom" id="prenom" type="text" required class="{{ $champ }}">
                                            @error('prenom') <p class="rfl-erreur">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="nom" class="{{ $label }}">Nom</label>
                                            <input wire:model="nom" id="nom" type="text" required class="{{ $champ }}">
                                            @error('nom') <p class="rfl-erreur">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="dateNaissance" class="{{ $label }}">Date de naissance</label>
                                            <input wire:model="dateNaissance" id="dateNaissance" type="date" required min="1900-01-01" max="{{ now()->subDay()->toDateString() }}" class="{{ $champ }}">
                                            @error('dateNaissance') <p class="rfl-erreur">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="sexe" class="{{ $label }}">Sexe <span class="font-normal text-gris-texte">(facultatif)</span></label>
                                            <select wire:model="sexe" id="sexe" class="{{ $champ }}">
                                                <option value="">Non renseigné</option>
                                                @foreach ($sexes as $valeur => $libelle)
                                                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                                                @endforeach
                                            </select>
                                            @error('sexe') <p class="rfl-erreur">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label for="telephone" class="{{ $label }}">Téléphone <span class="font-normal text-gris-texte">(facultatif)</span></label>
                                            <input wire:model="telephone" id="telephone" type="tel" inputmode="tel" autocomplete="{{ $this->estMoi ? 'tel' : 'off' }}" placeholder="06 12 34 56 78" class="{{ $champ }}">
                                            @error('telephone') <p class="rfl-erreur">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label for="adresse" class="{{ $label }}">Adresse <span class="font-normal text-gris-texte">(facultatif)</span></label>
                                            <input wire:model="adresse" id="adresse" type="text" autocomplete="{{ $this->estMoi ? 'street-address' : 'off' }}" class="{{ $champ }}">
                                            @error('adresse') <p class="rfl-erreur">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="codePostal" class="{{ $label }}">Code postal</label>
                                            <input wire:model="codePostal" id="codePostal" type="text" inputmode="numeric" autocomplete="{{ $this->estMoi ? 'postal-code' : 'off' }}" maxlength="6" class="{{ $champ }}">
                                            @error('codePostal') <p class="rfl-erreur">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="ville" class="{{ $label }}">Ville</label>
                                            <input wire:model="ville" id="ville" type="text" autocomplete="{{ $this->estMoi ? 'address-level2' : 'off' }}" class="{{ $champ }}">
                                            @error('ville') <p class="rfl-erreur">{{ $message }}</p> @enderror
                                        </div>
                                    </div>

                                    <fieldset class="mt-6 border-t border-bordure pt-5">
                                        <legend class="sr-only">Personne à prévenir en cas d'urgence</legend>
                                        <p class="mb-3 text-sm font-semibold text-encre">Personne à prévenir en cas d'urgence</p>
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div>
                                                <label for="urgenceNom" class="{{ $label }}">Nom et prénom</label>
                                                <input wire:model="urgenceNom" id="urgenceNom" type="text" class="{{ $champ }}">
                                                @error('urgenceNom') <p class="rfl-erreur">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label for="urgenceTel" class="{{ $label }}">Téléphone</label>
                                                <input wire:model="urgenceTel" id="urgenceTel" type="tel" inputmode="tel" placeholder="06 12 34 56 78" class="{{ $champ }}">
                                                @error('urgenceTel') <p class="rfl-erreur">{{ $message }}</p> @enderror
                                            </div>
                                        </div>
                                    </fieldset>

                                    <div class="mt-6 flex items-center justify-end gap-3">
                                        <button type="button" wire:click="annuler" class="{{ $boutonSecondaire }}">Annuler</button>
                                        <button type="submit" class="{{ $bouton }}" wire:loading.attr="disabled">Enregistrer</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </li>

                    {{-- 2. Décharge et consentements --}}
                    <li class="relative border-t border-bordure px-6 py-5">
                        <span class="absolute top-11 -bottom-5 left-[35px] w-0.5 bg-bordure" aria-hidden="true"></span>
                        <div class="absolute top-5 left-6"><x-timeline-puce :etat="$etapes['decharge']" /></div>

                        <div class="pl-10">
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <h3 class="font-display font-bold text-encre">Décharge et consentements</h3>
                                <span class="shrink-0 text-sm font-medium {{ $etatTexte[$etapes['decharge']] }}">{{ $etatLibelle[$etapes['decharge']] }}</span>
                            </div>

                            <ul class="mt-3 divide-y divide-bordure text-sm">
                                @foreach (TexteLegal::OBLIGATOIRES as $type)
                                    @php($c = $this->historique->first(fn ($h) => $h->type_texte === $type && $h->accepte))
                                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2.5">
                                        <a href="{{ route('textes.show', $type) }}" target="_blank" rel="noopener" class="text-encre underline decoration-bordure underline-offset-4 hover:text-canard hover:decoration-canard">{{ $libellesTextes[$type] }}</a>
                                        <span class="{{ $c ? 'text-gris-texte' : 'font-medium text-[#8A6A0B]' }}">{{ $c ? 'Acceptée le '.\Illuminate\Support\Carbon::parse($c->date_depot)->translatedFormat('j F Y').' · v'.$c->version : 'À accepter' }}</span>
                                    </li>
                                @endforeach
                                <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2.5">
                                    <span class="text-encre">Questionnaire de décharge</span>
                                    <span class="{{ $this->questionnaireDecharge ? 'text-gris-texte' : 'font-medium text-[#8A6A0B]' }}">{{ $this->questionnaireDecharge ? 'Rempli le '.$this->questionnaireDecharge->document->date_depot->translatedFormat('j F Y') : 'À remplir' }}</span>
                                </li>
                            </ul>

                            <label class="mt-3 flex items-start gap-3 rounded-lg border border-bordure bg-creme/50 px-4 py-3 text-sm text-encre">
                                <input wire:model.live="droitImage" type="checkbox" @disabled(! $this->modifiable) class="mt-0.5 h-4 w-4 shrink-0">
                                <span>
                                    <span class="font-medium">Droit à l'image</span> : {{ $this->estMoi ? "j'autorise l'utilisation de mon image" : "j'autorise l'utilisation de l'image de {$p->user->prenom}" }} sur les photos et vidéos de l'événement.
                                    <span class="block text-gris-texte">Facultatif, modifiable à tout moment.</span>
                                </span>
                            </label>

                            @if (! $i->consentementsAcceptes() && $i->statut === Inscription::BROUILLON)
                                <a href="{{ route('inscription.tunnel') }}" class="mt-3 inline-block text-sm rfl-lien">Reprendre l'inscription pour accepter le règlement et le RGPD</a>
                            @endif

                            @if ($this->dechargeARemplir)
                                <form wire:submit="enregistrerDecharge" class="mt-3 rounded-lg border border-bordure px-4 py-2" novalidate>
                                    <p class="pt-2 font-medium text-encre">Décharge de responsabilité</p>
                                    <p class="text-sm text-gris-texte">{{ $this->estMoi ? '' : "En tant que responsable, tu remplis la décharge pour {$p->user->prenom}. " }}Toutes les cases sont obligatoires.</p>
                                    <x-questionnaire-decharge class="mt-3 pb-3" :questions="$this->questionsDecharge" :evenement="$i->evenement" :signataire="auth()->user()->nom.' '.auth()->user()->prenom" />

                                    <div class="flex justify-end border-t border-bordure py-3">
                                        <button type="submit" class="{{ $bouton }}" wire:loading.attr="disabled">Envoyer la décharge</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </li>

                    {{-- 3. Santé --}}
                    <li class="relative border-t border-bordure px-6 py-5">
                        <span class="absolute top-11 -bottom-5 left-[35px] w-0.5 bg-bordure" aria-hidden="true"></span>
                        <div class="absolute top-5 left-6"><x-timeline-puce :etat="$etapes['sante']" /></div>

                        <div class="pl-10">
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <h3 class="font-display font-bold text-encre">Questionnaire de santé</h3>
                                <span class="shrink-0 text-sm font-medium {{ $etatTexte[$etapes['sante']] }}">{{ $etatLibelle[$etapes['sante']] }}</span>
                            </div>
                            <p class="mt-2 text-sm text-gris-texte">
                                @switch($etapes['sante'])
                                    @case('non_requis') Aucun questionnaire n'est demandé pour {{ $course->nom }}. @break
                                    @case('fait') Questionnaire rempli le {{ $this->questionnaireSante?->document->date_depot->translatedFormat('j F Y') }}. Les réponses sont confidentielles. @break
                                    @default
                                        À remplir avant le {{ $i->evenement->date_evenement->copy()->subDays(15)->translatedFormat('j F Y') }}.
                                        @if ($i->statut === Inscription::DOSSIER_INCOMPLET) L'inscription sera validée dès qu'il sera rempli. @endif
                                @endswitch
                            </p>

                            @if ($this->santeARemplir)
                                <form wire:submit="enregistrerSante" class="mt-3 rounded-lg border border-bordure px-4 py-2" novalidate>
                                    <p class="pt-2 text-sm text-gris-texte">{{ $this->estMoi ? 'Réponds à chaque question.' : "Réponds à chaque question pour {$p->user->prenom}." }} Seules les personnes habilitées de l'association peuvent consulter les réponses.</p>
                                    <div class="divide-y divide-bordure">
                                        @foreach ($this->questionsSante as $q)
                                            <x-question-oui-non :question="$q" :numero="$loop->iteration" modele="reponsesSante" />
                                        @endforeach
                                    </div>

                                    <div class="border-t border-bordure py-3">
                                        <label class="flex items-start gap-3 text-sm text-encre">
                                            <input wire:model="attestationSante" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0">
                                            <span>{{ $this->estMoi ? "J'atteste avoir répondu avec exactitude à toutes les questions." : "J'atteste avoir répondu avec exactitude pour {$p->user->prenom}." }}</span>
                                        </label>
                                        @error('attestationSante') <p class="rfl-erreur">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="flex justify-end border-t border-bordure py-3">
                                        <button type="submit" class="{{ $bouton }}" wire:loading.attr="disabled">Envoyer le questionnaire</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </li>

                    {{-- 4. Paiement --}}
                    <li class="relative border-t border-bordure px-6 py-5">
                        <div class="absolute top-5 left-6"><x-timeline-puce :etat="$etapes['paiement']" /></div>

                        <div class="pl-10">
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <h3 class="font-display font-bold text-encre">Paiement</h3>
                                <span class="shrink-0 text-sm font-medium {{ $etatTexte[$etapes['paiement']] }}">{{ $etatLibelle[$etapes['paiement']] }}</span>
                            </div>
                            <p class="mt-2 text-sm text-gris-texte">
                                @if ($etapes['paiement'] === 'fait')
                                    @if ($i->commande?->statut === Commande::GRATUITE || (float) $i->prix_paye === 0.0)
                                        Inscription offerte, rien à régler.
                                    @else
                                        {{ $this->euros((float) $i->prix_paye) }} réglés{{ $this->paiementReussi?->date_paiement ? ' le '.$this->paiementReussi->date_paiement->translatedFormat('j F Y') : '' }}.
                                    @endif
                                @elseif ($etapes['paiement'] === 'en_cours')
                                    {{ $this->euros((float) $i->prix_paye) }} à régler : la place n'est garantie qu'une fois le paiement reçu.
                                    @if ($i->commande->inscriptions->count() > 1) Le paiement couvre toute la commande ({{ $this->euros((float) $i->commande->montant_total) }}). @endif
                                @elseif ($i->statut === Inscription::BROUILLON)
                                    L'inscription n'est pas encore finalisée : reprends-la pour passer au paiement.
                                @else
                                    Inscription annulée.
                                @endif
                            </p>
                        </div>
                    </li>
                </ol>
            </section>

            {{-- Suppression : seulement tant que rien n'est payé --}}
            @if ($this->supprimable)
                <section class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-bordure bg-white px-6 py-5" aria-labelledby="titre-suppression">
                    <div class="max-w-lg">
                        <h2 id="titre-suppression" class="font-display font-bold text-encre">Supprimer l'inscription</h2>
                        <p class="mt-1 text-sm text-gris-texte">
                            Rien n'a été payé : tu peux la supprimer.
                            @if ($i->statut === Inscription::EN_ATTENTE_PAIEMENT && $i->commande->inscriptions->count() > 1)
                                Elle fait partie d'une commande avec d'autres personnes : leur paiement sera à relancer.
                            @endif
                        </p>
                        @error('suppression') <p class="mt-3 rfl-alerte-erreur" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="supprimer" wire:confirm="Supprimer l'inscription de {{ $p->user->prenom }} ? Cette action est définitive." class="inline-flex items-center rounded-lg border border-rose-200 bg-white px-4 py-2 text-sm font-medium text-rose-700 hover:border-rose-300 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">Supprimer</button>
                </section>
            @elseif ($i->statut !== Inscription::ANNULEE)
                <p class="text-sm text-gris-texte">Besoin d'annuler cette inscription ? Elle est déjà réglée : écris à l'association à <a href="mailto:contact@effervescence-jeunes.fr" class="rfl-lien">contact@effervescence-jeunes.fr</a>, on regarde ta situation au cas par cas.</p>
            @endif
        </div>
    </div>
</div>
