<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Participant;
use App\Models\QuestionSante;
use App\Models\TexteLegal;
use App\Models\User;
use App\Services\Inscriptions\Consentements;
use App\Services\Inscriptions\Eligibilite;
use App\Services\Inscriptions\PasserCommande;
use App\Services\Inscriptions\Questionnaires;
use App\Services\Paiement\DemarrerPaiement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

/*
 * Tunnel d'inscription (écrans 02 à 09), en panneaux sans rechargement.
 *
 * Ordre : Compte → Participant → Épreuve → Documents → Don → Récap → Paiement.
 * Le compte et la date de naissance sont connus avant de proposer les épreuves, pour ne
 * montrer que celles ouvertes à l'âge du participant (âge au jour de l'événement).
 *
 * Tout est enregistré côté serveur à chaque « Continuer » : le panier est l'ensemble des
 * inscriptions en brouillon des participants dont le compte est responsable (lui-même et
 * ses participants gérés). Le don, qui appartient à la commande, est gardé en session.
 */
new #[Layout('components.tunnel-shell', ['title' => 'Inscription · Run For Lorène'])] class extends Component {
    public const ETAPES = [
        'compte' => [1, 'Compte'],
        'participant' => [2, 'Participant'],
        'epreuve' => [3, 'Épreuve'],
        'documents' => [4, 'Documents'],
        'don' => [5, 'Don'],
        'recap' => [6, 'Récapitulatif'],
        'attente' => [7, 'Paiement'],
    ];

    /** Champs de l'étape Documents, propres à chaque participant. */
    private const CHAMPS_DOCUMENTS = [
        'accepteReglement', 'accepteDecharge', 'accepteRgpd', 'accepteImage',
        'reponsesDecharge', 'reponsesSante', 'attestationSante', 'questionnaires',
    ];

    public string $etape = 'compte';

    /** 'avant' ou 'arriere' : sens de glissement du panneau. */
    public string $sens = 'avant';

    // Participant
    public string $choix = '';

    public string $prenom = '';

    public string $nom = '';

    public string $dateNaissance = '';

    // Épreuve
    public ?int $courseId = null;

    // Documents
    public bool $accepteReglement = false;

    public bool $accepteDecharge = false;

    public bool $accepteRgpd = false;

    public bool $accepteImage = false;

    /** @var array<int, bool> affirmations de la décharge cochées, par id_question */
    public array $reponsesDecharge = [];

    /** @var array<int, string> 'oui' | 'non' par id_question */
    public array $reponsesSante = [];

    public bool $attestationSante = false;

    /** Décharge et questionnaire de santé : 'maintenant' ou 'plus_tard' (depuis l'espace). */
    public string $questionnaires = '';

    // Don
    public string $don = '';

    public bool $recuFiscal = false;

    public bool $anonyme = false;

    // Résolus côté serveur, jamais modifiables depuis le navigateur.
    #[Locked]
    public ?int $participantId = null;

    #[Locked]
    public ?int $inscriptionId = null;

    public function mount(): void
    {
        $evenement = $this->evenement;

        if (! Auth::check()) {
            $this->etape = $evenement?->inscriptionsOuvertes() ? 'compte' : 'ferme';

            return;
        }

        if (! Auth::user()->hasVerifiedEmail()) {
            session(['url.intended' => route('inscription.tunnel')]);
            $this->redirectRoute('verification.notice');

            return;
        }

        if ($this->commandeEnAttente) {
            $this->etape = 'attente';

            return;
        }

        if (! $evenement?->inscriptionsOuvertes()) {
            $this->etape = 'ferme';

            return;
        }

        $this->don = (string) session('tunnel.don', '');
        $this->recuFiscal = (bool) session('tunnel.recu', false);
        $this->anonyme = (bool) session('tunnel.anonyme', false);

        if (request()->boolean('nouveau')) {
            $this->allerParticipant();

            return;
        }

        // Reprise : un brouillon sans ses consentements reprend aux documents, sinon récapitulatif.
        $aCompleter = $this->panier->first(fn (Inscription $i) => ! $i->consentementsAcceptes());

        if ($aCompleter) {
            $this->chargerInscription($aCompleter);
            $this->etape = 'documents';
        } elseif ($this->panier->isNotEmpty()) {
            $this->etape = session()->has('tunnel.don') ? 'recap' : 'don';
        } else {
            $this->allerParticipant();
        }
    }

    // ------------------------------------------------------------------
    // Données calculées
    // ------------------------------------------------------------------

    #[Computed]
    public function evenement(): ?Evenement
    {
        return Evenement::courant();
    }

    /** Commande en attente de paiement pour cet événement. */
    #[Computed]
    public function commandeEnAttente(): ?Commande
    {
        return Commande::where('id_user', Auth::id())
            ->where('statut', Commande::EN_COURS)
            ->whereHas('inscriptions', fn ($q) => $q->where('id_evenement', $this->evenement?->id_evenement))
            ->with('inscriptions.participant.user', 'inscriptions.tarif.course', 'don')
            ->latest('id_commande')
            ->first();
    }

    /** @return Collection<int, Participant> moi + participants gérés */
    private function mesParticipants(): Collection
    {
        $user = Auth::user();

        return Participant::with('user')
            ->where('id_user', $user->id_user)
            ->orWhere('id_responsable', $user->id_user)
            ->get();
    }

    /** @return Collection<int, Inscription> brouillons du panier */
    #[Computed]
    public function panier(): Collection
    {
        return Inscription::with('participant.user', 'tarif.course')
            ->where('id_evenement', $this->evenement->id_evenement)
            ->where('statut', Inscription::BROUILLON)
            ->whereIn('id_participant', $this->mesParticipants()->pluck('id_user'))
            ->orderBy('id_inscription')
            ->get();
    }

    /** Options de l'écran « Pour qui ? ». */
    #[Computed]
    public function choixParticipants(): Collection
    {
        $user = Auth::user();
        $eligibilite = app(Eligibilite::class);
        $participants = $this->mesParticipants()->keyBy('id_user');
        $options = collect();

        $moi = $participants->get($user->id_user);
        $options->push((object) [
            'cle' => 'moi',
            'libelle' => 'Moi-même',
            'detail' => trim($user->prenom.' '.$user->nom),
            'inscrit' => $moi && $eligibilite->dejaInscrit($moi, $this->evenement),
        ]);

        foreach ($participants->except($user->id_user)->sortBy('user.prenom') as $p) {
            $options->push((object) [
                'cle' => (string) $p->id_user,
                'libelle' => trim($p->user->prenom.' '.$p->user->nom),
                'detail' => $p->ageAu($this->evenement->date_evenement).' ans le jour J',
                'inscrit' => $eligibilite->dejaInscrit($p, $this->evenement),
            ]);
        }

        $options->push((object) [
            'cle' => 'nouveau',
            'libelle' => 'Une autre personne',
            'detail' => 'Un enfant, un proche… sans compte à créer',
            'inscrit' => false,
        ]);

        $dansPanier = $this->panier->pluck('id_participant')->map(fn ($id) => $id === $user->id_user ? 'moi' : (string) $id)->all();

        return $options->map(function ($o) use ($dansPanier) {
            $o->dansPanier = in_array($o->cle, $dansPanier, true);

            return $o;
        });
    }

    #[Computed]
    public function participant(): ?Participant
    {
        return $this->participantId ? Participant::with('user')->find($this->participantId) : null;
    }

    #[Computed]
    public function estMoi(): bool
    {
        return $this->participantId === Auth::id();
    }

    /** Épreuves ouvertes à l'âge du participant, avec tarif et places. */
    #[Computed]
    public function courses(): Collection
    {
        $participant = $this->participant;
        $age = $participant->ageAu($this->evenement->date_evenement);

        return $this->evenement->courses()->orderBy('heure_depart')->get()
            ->filter(fn (Course $c) => $c->accepteAge($age))
            ->map(function (Course $course) use ($age) {
                $restantes = $course->placesRestantes();
                $tarif = $course->tarifPour($age);

                return (object) [
                    'id' => $course->id_course,
                    'nom' => $course->nom,
                    'depart' => Carbon::parse($course->heure_depart)->format('G\hi'),
                    'distance' => $course->distance ? rtrim(rtrim(number_format((float) $course->distance, 2, ',', ''), '0'), ',').' km' : null,
                    'prix' => $tarif ? (float) $tarif->montant : null,
                    'complet' => $restantes !== null && $restantes <= 0,
                    'restantes' => $restantes,
                    'peuDePlaces' => $restantes !== null && $restantes > 0 && ($restantes <= 10 || $restantes / max($course->quota, 1) < 0.2),
                ];
            })
            ->values();
    }

    #[Computed]
    public function questionsDecharge(): Collection
    {
        return app(Questionnaires::class)->questions(QuestionSante::DECHARGE);
    }

    #[Computed]
    public function questionsSante(): Collection
    {
        return app(Questionnaires::class)->questions(QuestionSante::SANTE);
    }

    /** L'épreuve choisie demande le questionnaire de santé. */
    #[Computed]
    public function santeRequise(): bool
    {
        return (bool) $this->panier->firstWhere('id_inscription', $this->inscriptionId)?->tarif->course->questionnaire_requis
            && $this->questionsSante->isNotEmpty();
    }

    #[Computed]
    public function totalPanier(): float
    {
        return round($this->panier->sum(fn (Inscription $i) => (float) ($this->tarifActuel($i)?->montant ?? $i->tarif->montant)) + $this->montantDon(), 2);
    }

    /** Problèmes bloquants du panier (âge, places, tarif…), par inscription. */
    #[Computed]
    public function alertesPanier(): array
    {
        $eligibilite = app(Eligibilite::class);
        $alertes = [];

        foreach ($this->panier as $i) {
            $erreur = $eligibilite->dejaInscrit($i->participant, $this->evenement)
                ? 'Déjà inscrit(e) à cet événement.'
                : $eligibilite->erreur($i->participant, $i->tarif->course, $this->evenement);

            if (! $erreur && ! $i->consentementsAcceptes()) {
                $erreur = 'Consentements à compléter.';
            }

            if ($erreur) {
                $alertes[$i->id_inscription] = $erreur;
            }
        }

        return $alertes;
    }

    private function tarifActuel(Inscription $i)
    {
        return $i->tarif->course->tarifPour($i->participant->ageAu($this->evenement->date_evenement));
    }

    private function montantDon(): float
    {
        return is_numeric($this->don) ? round(max((float) $this->don, 0), 2) : 0.0;
    }

    public function euros(float $montant): string
    {
        return number_format($montant, fmod($montant, 1.0) == 0.0 ? 0 : 2, ',', ' ').' €';
    }

    // ------------------------------------------------------------------
    // Navigation
    // ------------------------------------------------------------------

    private function aller(string $etape, string $sens = 'avant'): void
    {
        $this->sens = $sens;
        $this->etape = $etape;
        $this->resetErrorBag();
    }

    public function retour(): void
    {
        match ($this->etape) {
            'epreuve' => $this->aller('participant', 'arriere'),
            'documents' => $this->aller('epreuve', 'arriere'),
            'don' => $this->inscriptionId ? $this->aller('documents', 'arriere') : $this->aller('recap', 'arriere'),
            'recap' => $this->aller('don', 'arriere'),
            'participant' => $this->panier->isNotEmpty() ? $this->aller('recap', 'arriere') : $this->redirectRoute('home'),
            default => null,
        };
    }

    private function allerParticipant(): void
    {
        $this->reset('choix', 'prenom', 'nom', 'dateNaissance', 'courseId', ...self::CHAMPS_DOCUMENTS);
        $this->participantId = null;
        $this->inscriptionId = null;
        $this->aller('participant');
    }

    public function ajouterParticipant(): void
    {
        $this->allerParticipant();
    }

    /** Charge une inscription du panier pour la modifier. */
    private function chargerInscription(Inscription $inscription): void
    {
        $participant = $inscription->participant;

        $this->participantId = $participant->id_user;
        $this->inscriptionId = $inscription->id_inscription;
        $this->choix = $participant->id_user === Auth::id() ? 'moi' : (string) $participant->id_user;
        $this->prenom = $participant->user->prenom;
        $this->nom = $participant->user->nom;
        $this->dateNaissance = $participant->date_naissance->format('Y-m-d');
        $this->courseId = $inscription->tarif->id_course;
        $this->chargerConsentements($inscription);
    }

    private function chargerConsentements(Inscription $inscription): void
    {
        $types = $inscription->typesConsentis();

        $this->accepteReglement = in_array('reglement', $types, true);
        $this->accepteDecharge = in_array('decharge', $types, true);
        $this->accepteRgpd = in_array('rgpd', $types, true);
        $this->accepteImage = app(Consentements::class)->dernierDroitImage($inscription) ?? false;

        $questionnaires = app(Questionnaires::class);
        $this->reponsesDecharge = array_map(fn ($r) => $r === 'oui', $questionnaires->reponses($inscription, QuestionSante::DECHARGE));
        $this->reponsesSante = $questionnaires->reponses($inscription, QuestionSante::SANTE);
        $this->attestationSante = (bool) $questionnaires->dernier($inscription, QuestionSante::SANTE)?->attestation;
        // Déjà commencés : on les montre tels quels ; sinon la question « maintenant ou plus tard » est reposée.
        $this->questionnaires = $this->reponsesDecharge || $this->reponsesSante ? 'maintenant' : '';
    }

    public function modifier(int $idInscription, string $etape): void
    {
        $inscription = $this->panier->firstWhere('id_inscription', $idInscription);

        if (! $inscription || ! in_array($etape, ['participant', 'epreuve', 'documents'], true)) {
            return;
        }

        $this->chargerInscription($inscription);
        $this->aller($etape, 'arriere');
    }

    public function modifierDon(): void
    {
        $this->inscriptionId = null;
        $this->aller('don', 'arriere');
    }

    public function retirer(int $idInscription): void
    {
        $inscription = $this->panier->firstWhere('id_inscription', $idInscription);

        // Brouillon jamais soumis : ses documents partent avec lui (clé étrangère en cascade).
        $inscription?->delete();

        unset($this->panier, $this->alertesPanier, $this->totalPanier, $this->choixParticipants);

        if ($this->panier->isEmpty()) {
            $this->allerParticipant();
        }
    }

    // ------------------------------------------------------------------
    // Étape Participant (écran 05)
    // ------------------------------------------------------------------

    public function choisirParticipant(string $cle): void
    {
        $this->resetErrorBag();
        $this->choix = $cle;
        $user = Auth::user();

        if ($cle === 'moi') {
            $this->prenom = $user->prenom;
            $this->nom = $user->nom;
            $this->dateNaissance = $user->participant?->date_naissance?->format('Y-m-d') ?? '';
        } elseif ($cle === 'nouveau') {
            // Le plus souvent un enfant : même nom de famille, à corriger au besoin.
            $this->prenom = '';
            $this->nom = $user->nom;
            $this->dateNaissance = '';
        } else {
            $p = $user->participantsGeres()->with('user')->find((int) $cle);
            if (! $p) {
                $this->choix = '';

                return;
            }
            $this->prenom = $p->user->prenom;
            $this->nom = $p->user->nom;
            $this->dateNaissance = $p->date_naissance->format('Y-m-d');
        }
    }

    public function participantSuivant(): void
    {
        $user = Auth::user();

        $this->validate([
            'choix' => 'required|string',
            'prenom' => 'required|string|max:100',
            'nom' => 'required|string|max:100',
            'dateNaissance' => 'required|date_format:Y-m-d|before:today|after:1900-01-01',
        ], [
            'choix.required' => 'Choisis qui tu inscris.',
            'dateNaissance.before' => 'La date de naissance doit être dans le passé.',
            'dateNaissance.after' => 'Vérifie la date de naissance.',
        ], [
            'dateNaissance' => 'date de naissance',
            'prenom' => 'prénom',
        ]);

        $prenom = trim($this->prenom);
        $nom = trim($this->nom);

        $participant = DB::transaction(function () use ($user, $prenom, $nom) {
            if ($this->choix === 'moi') {
                $user->fill(['prenom' => $prenom, 'nom' => $nom])->save();

                $moi = Participant::firstOrNew(['id_user' => $user->id_user]);
                $moi->date_naissance = $this->dateNaissance;
                $moi->pays ??= 'France';
                $moi->save();

                return $moi;
            }

            if ($this->choix === 'nouveau') {
                // Même prénom, nom et date de naissance qu'un participant déjà géré : c'est lui.
                $existant = $user->participantsGeres()
                    ->whereDate('date_naissance', $this->dateNaissance)
                    ->whereHas('user', fn ($q) => $q->where('prenom', $prenom)->where('nom', $nom))
                    ->first();

                if ($existant) {
                    return $existant;
                }

                $compte = User::create(['prenom' => $prenom, 'nom' => $nom]);

                return Participant::create([
                    'id_user' => $compte->id_user,
                    'date_naissance' => $this->dateNaissance,
                    'pays' => 'France',
                    'id_responsable' => $user->id_user,
                ]);
            }

            $gere = $user->participantsGeres()->find((int) $this->choix);
            abort_unless($gere, 403);

            $gere->user->fill(['prenom' => $prenom, 'nom' => $nom])->save();
            $gere->date_naissance = $this->dateNaissance;
            $gere->save();

            return $gere;
        });

        $participant->load('user');

        if (app(Eligibilite::class)->dejaInscrit($participant, $this->evenement)) {
            $this->addError('choix', "{$participant->user->prenom} est déjà inscrit(e) à Run For Lorène. Retrouve son inscription dans ton espace.");

            return;
        }

        $this->participantId = $participant->id_user;
        $this->choix = $participant->id_user === $user->id_user ? 'moi' : (string) $participant->id_user;

        // Brouillon existant pour ce participant : on reprend son épreuve et ses consentements.
        $brouillon = $this->panier->firstWhere('id_participant', $participant->id_user);
        if ($brouillon) {
            $this->inscriptionId = $brouillon->id_inscription;
            $this->courseId = $brouillon->tarif->id_course;
            $this->chargerConsentements($brouillon);
        } else {
            $this->inscriptionId = null;
            $this->courseId = null;
            $this->reset(...self::CHAMPS_DOCUMENTS);
        }

        unset($this->participant, $this->courses, $this->estMoi);
        $this->aller('epreuve');
    }

    // ------------------------------------------------------------------
    // Étape Épreuve (écran 02)
    // ------------------------------------------------------------------

    public function choisirCourse(int $id): void
    {
        $this->courseId = $id;
        $this->resetErrorBag('courseId');
    }

    public function epreuveSuivant(): void
    {
        abort_unless($this->participantId, 403);

        $this->validate(['courseId' => 'required|integer'], ['courseId.required' => 'Choisis une épreuve.']);

        $evenement = $this->evenement;
        $course = $evenement->courses()->find($this->courseId);
        $participant = $this->participant;

        if (! $course) {
            $this->addError('courseId', 'Choisis une épreuve dans la liste.');

            return;
        }

        $eligibilite = app(Eligibilite::class);

        if ($erreur = $eligibilite->erreur($participant, $course, $evenement)) {
            $this->addError('courseId', $erreur);

            return;
        }

        $tarif = $eligibilite->tarif($participant, $course, $evenement);

        $inscription = DB::transaction(function () use ($evenement, $participant, $tarif) {
            $inscription = Inscription::where('id_evenement', $evenement->id_evenement)
                ->where('id_participant', $participant->id_user)
                ->lockForUpdate()
                ->first();

            if ($inscription && ! in_array($inscription->statut, [Inscription::BROUILLON, Inscription::ANNULEE], true)) {
                return null;
            }

            $inscription ??= new Inscription([
                'id_evenement' => $evenement->id_evenement,
                'id_participant' => $participant->id_user,
            ]);

            $inscription->forceFill([
                'id_tarif' => $tarif->id_tarif,
                'statut' => Inscription::BROUILLON,
                'id_commande' => null,
                'prix_paye' => 0,
                'dossard' => null,
            ])->save();

            return $inscription;
        });

        if (! $inscription) {
            $this->addError('courseId', "{$participant->user->prenom} est déjà inscrit(e) à cet événement.");

            return;
        }

        $this->inscriptionId = $inscription->id_inscription;
        unset($this->panier, $this->totalPanier, $this->alertesPanier);

        $this->aller('documents');
    }

    // ------------------------------------------------------------------
    // Étape Documents et consentements (écran 06)
    // ------------------------------------------------------------------

    public function documentsSuivant(): void
    {
        $inscription = $this->inscriptionId ? $this->panier->firstWhere('id_inscription', $this->inscriptionId) : null;
        abort_unless($inscription, 403);

        $ouiNon = 'required|in:'.implode(',', Questionnaires::REPONSES);
        $regles = [
            'accepteReglement' => 'accepted',
            'accepteRgpd' => 'accepted',
            'questionnaires' => 'required|in:maintenant,plus_tard',
        ];

        // « Plus tard » : rien à valider ici, la décharge et la santé se rempliront dans l'espace.
        $maintenant = $this->questionnaires === 'maintenant';
        $remplirSante = $maintenant && $this->santeRequise;

        if ($maintenant) {
            $regles['accepteDecharge'] = 'accepted';
            foreach ($this->questionsDecharge as $q) {
                $regles["reponsesDecharge.{$q->id_question}"] = 'accepted';
            }
        }

        if ($remplirSante) {
            foreach ($this->questionsSante as $q) {
                $regles["reponsesSante.{$q->id_question}"] = $ouiNon;
            }
            $regles['attestationSante'] = 'accepted';
        }

        $this->validate($regles, [
            'accepteReglement.accepted' => 'Il faut accepter le règlement pour continuer.',
            'accepteRgpd.accepted' => 'Il faut accepter le traitement des données pour continuer.',
            'questionnaires.required' => 'Choisis de remplir la décharge et le questionnaire maintenant ou plus tard.',
            'questionnaires.in' => 'Choisis de remplir la décharge et le questionnaire maintenant ou plus tard.',
            'accepteDecharge.accepted' => "Coche « J'ai lu et j'approuve » pour valider la décharge, ou choisis « Plus tard ».",
            'reponsesDecharge.*.accepted' => Questionnaires::AFFIRMATION_REQUISE,
            'reponsesSante.*.required' => 'Réponds par oui ou par non.',
            'reponsesSante.*.in' => 'Réponds par oui ou par non.',
            'attestationSante.accepted' => "Coche l'attestation, ou choisis « Plus tard ».",
        ]);

        $consentements = app(Consentements::class);
        $textes = $consentements->textes($this->evenement);

        $manquants = collect(TexteLegal::OBLIGATOIRES)->reject(fn ($t) => $textes->has($t));
        if ($manquants->isNotEmpty()) {
            report(new RuntimeException('Textes légaux manquants : '.$manquants->implode(', ')));
            $this->addError('accepteReglement', "Un document légal n'est pas encore publié. Réessaie plus tard ou contacte l'association.");

            return;
        }

        DB::transaction(function () use ($inscription, $consentements, $maintenant, $remplirSante) {
            $questionnaires = app(Questionnaires::class);

            if ($maintenant) {
                $questionnaires->remplirDecharge($inscription, $this->reponsesDecharge, Auth::id(), request()->ip());
            }

            if ($remplirSante) {
                $questionnaires->enregistrer($inscription, QuestionSante::SANTE, $this->reponsesSante, $this->attestationSante, Auth::id());
            }

            $consentements->enregistrer($inscription, $this->accepteImage, Auth::id(), request()->ip());
        });

        unset($this->panier, $this->alertesPanier);

        $this->aller(session()->has('tunnel.don') ? 'recap' : 'don');
    }

    // ------------------------------------------------------------------
    // Étape Don (écran 07)
    // ------------------------------------------------------------------

    public function choisirDon(int $montant): void
    {
        $this->don = (string) $montant;
        $this->resetErrorBag('don');
    }

    public function donPasser(): void
    {
        $this->don = '';
        $this->recuFiscal = false;
        $this->anonyme = false;
        $this->enregistrerDon();
    }

    public function donSuivant(): void
    {
        $this->don = str_replace([',', ' ', '€'], ['.', '', ''], trim($this->don));

        $this->validate([
            'don' => ['nullable', 'numeric', 'min:0', 'max:10000', 'regex:/^\d+(\.\d{1,2})?$/'],
        ], [
            'don.numeric' => 'Indique un montant en euros, par exemple 15.',
            'don.regex' => 'Indique un montant en euros, par exemple 15 ou 15,50.',
            'don.max' => 'Pour un don de plus de 10 000 €, contacte directement l\'association.',
        ]);

        $this->enregistrerDon();
    }

    private function enregistrerDon(): void
    {
        if ($this->montantDon() <= 0) {
            $this->recuFiscal = false;
            $this->anonyme = false;
        }

        session([
            'tunnel.don' => $this->montantDon() > 0 ? (string) $this->montantDon() : '',
            'tunnel.recu' => $this->recuFiscal,
            'tunnel.anonyme' => $this->anonyme,
        ]);

        unset($this->totalPanier);
        $this->aller('recap');
    }

    // ------------------------------------------------------------------
    // Étape Récapitulatif (écran 08) → paiement (écran 09)
    // ------------------------------------------------------------------

    public function payer()
    {
        unset($this->panier, $this->alertesPanier);

        if ($this->alertesPanier) {
            $this->addError('commande', 'Corrige les points signalés avant de payer.');

            return null;
        }

        try {
            $commande = app(PasserCommande::class)(
                Auth::user(),
                $this->evenement,
                $this->panier,
                $this->montantDon(),
                $this->recuFiscal,
                $this->anonyme,
            );
        } catch (ValidationException $e) {
            unset($this->panier, $this->alertesPanier);
            $this->addError('commande', $e->validator->errors()->first());

            return null;
        }

        session()->forget(['tunnel.don', 'tunnel.recu', 'tunnel.anonyme']);

        if ($commande->statut === Commande::PAYEE) {
            return $this->redirectRoute('inscription.confirmation', $commande);
        }

        try {
            return $this->redirect(app(DemarrerPaiement::class)($commande));
        } catch (ValidationException $e) {
            session()->flash('erreur', $e->validator->errors()->first());
        } catch (\Throwable $e) {
            report($e);
            session()->flash('erreur', 'Le service de paiement ne répond pas. Réessaie dans un instant.');
        }

        return $this->redirectRoute('inscription.echec', $commande);
    }
}; ?>

<div>
    @php
        [$numero, $libelle] = $this::ETAPES[$etape] ?? [null, null];
    @endphp

    @if ($numero)
        <div class="mb-6">
            <p class="mb-2 text-sm text-gris-texte">Étape {{ $numero }} sur 7 &middot; {{ $libelle }}</p>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-bordure" role="progressbar" aria-valuemin="1" aria-valuemax="7" aria-valuenow="{{ $numero }}" aria-label="Progression de l'inscription">
                <div class="h-full rounded-full bg-canard transition-all duration-300" style="width: {{ round($numero / 7 * 100) }}%"></div>
            </div>
        </div>
    @endif

    <div wire:key="panneau-{{ $etape }}-{{ $participantId }}" class="{{ $sens === 'avant' ? 'rfl-glisse-avant' : 'rfl-glisse-arriere' }}">

    {{-- Inscriptions fermées --}}
    @if ($etape === 'ferme')
        <h1 class="font-display text-2xl font-bold text-encre">Inscriptions fermées</h1>
        @if (! $this->evenement)
            <p class="mt-3 text-sm text-gris-texte">Aucun événement n'est ouvert aux inscriptions pour le moment.</p>
        @elseif (now()->lt($this->evenement->ouverture))
            <p class="mt-3 text-sm text-gris-texte">Les inscriptions à {{ $this->evenement->nom }} ouvrent le {{ $this->evenement->ouverture->translatedFormat('j F Y \à G\hi') }}. À très vite !</p>
        @else
            <p class="mt-3 text-sm text-gris-texte">Les inscriptions à {{ $this->evenement->nom }} sont closes. Tu peux toujours soutenir la cause par un don.</p>
        @endif
        <div class="mt-8 flex items-center justify-between">
            <a href="{{ route('home') }}" class="rfl-lien">Retour à l'événement</a>
            @auth
                <a href="{{ route('espace') }}" class="rfl-btn">Mon espace</a>
            @endauth
        </div>
    @endif

    {{-- Compte (écrans 03-04) --}}
    @if ($etape === 'compte')
        <livewire:auth.identification :destination="route('inscription.tunnel')" />
    @endif

    {{-- Paiement en attente --}}
    @if ($etape === 'attente' && $this->commandeEnAttente)
        @php($commande = $this->commandeEnAttente)
        <h1 class="font-display text-2xl font-bold text-encre">Un paiement t'attend</h1>
        <p class="mt-1 mb-6 text-sm text-gris-texte">Ta commande est prête, il ne reste plus qu'à la régler. Tu peux aussi la modifier avant de payer.</p>

        <ul class="divide-y divide-bordure rounded-xl border border-bordure">
            @foreach ($commande->inscriptions as $i)
                <li class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                    <span><span class="font-medium text-encre">{{ $i->participant->user->prenom }} {{ $i->participant->user->nom }}</span><br><span class="text-gris-texte">{{ $i->tarif->course->nom }}</span></span>
                    <span class="font-medium text-encre">{{ $this->euros((float) $i->prix_paye) }}</span>
                </li>
            @endforeach
            @if ($commande->don)
                <li class="flex items-center justify-between px-4 py-3 text-sm">
                    <span class="text-encre">Don</span>
                    <span class="font-medium text-encre">{{ $this->euros((float) $commande->don->montant) }}</span>
                </li>
            @endif
        </ul>

        <div class="mt-4 flex items-center justify-between border-t border-bordure pt-4">
            <p class="font-display text-lg font-bold text-encre">Total</p>
            <p class="font-display text-lg font-bold text-encre">{{ $this->euros((float) $commande->montant_total) }}</p>
        </div>

        <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <form method="POST" action="{{ route('inscription.modifier', $commande) }}">
                @csrf
                <button type="submit" class="rfl-lien">Modifier ma commande</button>
            </form>
            <form method="POST" action="{{ route('inscription.payer', $commande) }}">
                @csrf
                <button type="submit" class="rfl-btn w-full sm:w-auto">Payer {{ $this->euros((float) $commande->montant_total) }}</button>
            </form>
        </div>
    @endif

    {{-- Participant (écran 05) --}}
    @if ($etape === 'participant')
        <form wire:submit="participantSuivant" novalidate>
            <h1 class="font-display text-2xl font-bold text-encre">Pour qui ?</h1>
            <p class="mt-1 mb-6 text-sm text-gris-texte">Tu peux inscrire plusieurs personnes dans la même commande : toi, tes enfants, un proche…</p>

            <fieldset>
                <legend class="sr-only">Participant</legend>
                <div class="space-y-2">
                    @foreach ($this->choixParticipants as $option)
                        <button type="button"
                            wire:click="choisirParticipant('{{ $option->cle }}')"
                            @disabled($option->inscrit)
                            aria-pressed="{{ $choix === $option->cle ? 'true' : 'false' }}"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border-2 px-4 py-3 text-left transition-colors focus-visible:outline-2 focus-visible:outline-canard {{ $option->inscrit ? 'cursor-not-allowed border-bordure opacity-50' : ($choix === $option->cle ? 'border-canard bg-aqua-clair' : 'border-bordure hover:border-canard/50') }}">
                            <span>
                                <span class="block font-medium text-encre">{{ $option->libelle }}</span>
                                <span class="block text-sm text-gris-texte">{{ $option->detail }}</span>
                            </span>
                            @if ($option->inscrit)
                                <span class="shrink-0 rounded-full bg-aqua-clair px-3 py-1 text-xs font-medium text-canard">Déjà inscrit(e)</span>
                            @elseif ($option->dansPanier)
                                <span class="shrink-0 rounded-full bg-moutarde/20 px-3 py-1 text-xs font-medium text-encre">Dans ta commande</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </fieldset>
            @error('choix') <p class="rfl-erreur">{{ $message }}</p> @enderror

            @if ($choix !== '')
                <div class="mt-6 border-t border-bordure pt-6">
                    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="prenom" class="rfl-label">Prénom</label>
                            <input wire:model="prenom" id="prenom" type="text" autocomplete="{{ $choix === 'moi' ? 'given-name' : 'off' }}" required class="rfl-champ">
                            @error('prenom') <p class="rfl-erreur">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="nom" class="rfl-label">Nom</label>
                            <input wire:model="nom" id="nom" type="text" autocomplete="{{ $choix === 'moi' ? 'family-name' : 'off' }}" required class="rfl-champ">
                            @error('nom') <p class="rfl-erreur">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <label for="dateNaissance" class="rfl-label">Date de naissance</label>
                    <input wire:model="dateNaissance" id="dateNaissance" type="date" required min="1900-01-01" max="{{ now()->subDay()->toDateString() }}" class="rfl-champ" aria-describedby="naissance-aide">
                    <p id="naissance-aide" class="mt-2 text-xs text-gris-texte">Les épreuves proposées dépendent de l'âge le jour de l'événement.</p>
                    @error('dateNaissance') <p class="rfl-erreur">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="mt-8 flex items-center justify-between">
                <button type="button" wire:click="retour" class="rfl-lien">{{ $this->panier->isNotEmpty() ? 'Voir ma commande' : 'Retour' }}</button>
                <button type="submit" class="rfl-btn" @disabled($choix === '') wire:loading.attr="disabled">Continuer</button>
            </div>
        </form>
    @endif

    {{-- Épreuve (écran 02) --}}
    @if ($etape === 'epreuve' && $this->participant)
        <h1 class="font-display text-2xl font-bold text-encre">{{ $this->estMoi ? 'Choisis ton épreuve' : 'Quelle épreuve pour '.$this->participant->user->prenom.' ?' }}</h1>
        <p class="mt-1 mb-6 text-sm text-gris-texte">{{ $this->evenement->nom }} &middot; {{ $this->evenement->date_evenement->translatedFormat('l j F Y') }}</p>

        @error('courseId') <p class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror

        @if ($this->courses->isEmpty())
            <p class="rounded-xl bg-moutarde/15 px-4 py-3 text-sm text-encre">Aucune épreuve n'est ouverte à l'âge de {{ $this->estMoi ? 'ce participant' : $this->participant->user->prenom }} ({{ $this->participant->ageAu($this->evenement->date_evenement) }} ans le jour J). Vérifie la date de naissance.</p>
        @else
            <div class="space-y-3" role="radiogroup" aria-label="Épreuves">
                @foreach ($this->courses as $course)
                    @php($indisponible = $course->complet || $course->prix === null)
                    <button type="button"
                        role="radio"
                        aria-checked="{{ $courseId === $course->id ? 'true' : 'false' }}"
                        wire:click="choisirCourse({{ $course->id }})"
                        @disabled($indisponible)
                        class="w-full rounded-2xl border-2 p-4 text-left transition-colors focus-visible:outline-2 focus-visible:outline-canard {{ $indisponible ? 'cursor-not-allowed border-bordure opacity-50' : ($courseId === $course->id ? 'border-canard bg-aqua-clair' : 'border-bordure hover:border-canard/50') }}">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-display font-bold text-encre">{{ $course->nom }}</p>
                                <p class="text-sm text-gris-texte">
                                    Départ {{ $course->depart }}@if ($course->distance) &middot; {{ $course->distance }}@endif
                                    &middot; {{ $course->prix === null ? 'Tarif indisponible' : $this->euros($course->prix) }}
                                </p>
                            </div>
                            @if ($course->complet)
                                <span class="shrink-0 rounded-full bg-bordure px-3 py-1 text-sm font-medium text-gris-texte">Complet</span>
                            @elseif ($course->peuDePlaces)
                                <span class="shrink-0 rounded-full bg-moutarde/20 px-3 py-1 text-sm font-medium text-encre">Plus que {{ $course->restantes }} {{ $course->restantes > 1 ? 'places' : 'place' }}</span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        @endif

        <div class="mt-8 flex items-center justify-between">
            <button type="button" wire:click="retour" class="rfl-lien">Retour</button>
            <button type="button" wire:click="epreuveSuivant" @disabled(! $courseId) wire:loading.attr="disabled" class="rfl-btn">Continuer</button>
        </div>
    @endif

    {{-- Documents et consentements (écran 06) --}}
    @if ($etape === 'documents' && $this->participant)
        @php($pour = $this->estMoi ? null : $this->participant->user->prenom)
        <h1 class="font-display text-2xl font-bold text-encre">{{ $pour ? "Les documents de {$pour}" : 'Tes documents' }}</h1>
        @php($documents = $this->santeRequise ? 'la décharge et le questionnaire de santé' : 'la décharge')
        <p class="mt-1 mb-6 text-sm text-gris-texte">
            Quelques cases à cocher, puis {{ $documents }}.
            @if ($pour) En tant que responsable, tu réponds pour {{ $pour }}. @endif
        </p>

        <div class="space-y-4">
            <div>
                <label class="flex items-start gap-3 text-sm text-encre">
                    <input wire:model.live="accepteReglement" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-bordure text-canard focus:ring-canard">
                    <span>J'ai lu et j'accepte le <a href="{{ route('textes.show', 'reglement') }}" target="_blank" rel="noopener" class="rfl-lien underline">règlement de l'événement</a> <span class="text-gris-texte">(obligatoire)</span></span>
                </label>
                @error('accepteReglement') <p class="rfl-erreur">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="flex items-start gap-3 text-sm text-encre">
                    <input wire:model.live="accepteRgpd" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-bordure text-canard focus:ring-canard">
                    <span>J'accepte le <a href="{{ route('textes.show', 'rgpd') }}" target="_blank" rel="noopener" class="rfl-lien underline">traitement des données personnelles</a> pour l'organisation de l'événement <span class="text-gris-texte">(obligatoire)</span></span>
                </label>
                @error('accepteRgpd') <p class="rfl-erreur">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="flex items-start gap-3 text-sm text-encre">
                    <input wire:model="accepteImage" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-bordure text-canard focus:ring-canard">
                    <span>J'autorise l'utilisation de {{ $pour ? "l'image de {$pour}" : 'mon image' }} sur les photos et vidéos de l'événement <span class="text-gris-texte">(facultatif)</span></span>
                </label>
            </div>
        </div>

        <p class="mt-6 rounded-xl bg-aqua-clair px-4 py-3 text-sm text-canard">Aucun certificat médical ni licence : Run For Lorène est un événement non compétitif.</p>

        {{-- Décharge et questionnaire de santé : maintenant ou plus tard --}}
        <section class="mt-8 border-t border-bordure pt-6" aria-labelledby="titre-questionnaires">
            <h2 id="titre-questionnaires" class="font-display text-lg font-bold text-encre">{{ ucfirst($documents) }}</h2>
            <p class="mt-1 text-sm text-gris-texte">
                Obligatoire{{ $this->santeRequise ? 's' : '' }} pour prendre le départ. Tu peux {{ $this->santeRequise ? 'les' : 'la' }} remplir maintenant ou plus tard depuis ton espace.
            </p>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2" role="radiogroup" aria-labelledby="titre-questionnaires">
                @foreach (['maintenant' => ['Maintenant', 'Je remplis tout de suite.'], 'plus_tard' => ['Plus tard', 'Depuis mon espace, après le paiement.']] as $valeur => [$titre, $detail])
                    <button type="button" role="radio" aria-checked="{{ $questionnaires === $valeur ? 'true' : 'false' }}"
                        wire:click="$set('questionnaires', '{{ $valeur }}')"
                        class="rounded-xl border-2 px-4 py-3 text-left transition-colors focus-visible:outline-2 focus-visible:outline-canard {{ $questionnaires === $valeur ? 'border-canard bg-aqua-clair' : 'border-bordure hover:border-canard/50' }}">
                        <span class="block font-medium text-encre">{{ $titre }}</span>
                        <span class="block text-sm text-gris-texte">{{ $detail }}</span>
                    </button>
                @endforeach
            </div>
            @error('questionnaires') <p class="rfl-erreur">{{ $message }}</p> @enderror

            @if ($questionnaires === 'plus_tard')
                <div class="mt-4 rounded-xl border-2 border-moutarde bg-moutarde/15 px-4 py-3 text-sm text-encre" role="note">
                    <p class="font-display font-bold">Sans {{ $documents }}, {{ $pour ? "{$pour} ne pourra pas" : 'tu ne pourras pas' }} prendre le départ.</p>
                    <p class="mt-1">L'inscription restera « dossier incomplet », sans dossard, jusqu'à ce qu'{{ $this->santeRequise ? 'ils soient remplis' : 'elle soit remplie' }} depuis ton espace, avant le {{ $this->evenement->date_evenement->copy()->subDays(15)->translatedFormat('j F Y') }}.</p>
                </div>
            @endif

            @if ($questionnaires === 'maintenant')
                {{-- Décharge de responsabilité --}}
                <section class="mt-4 rounded-2xl border border-bordure p-4 sm:p-5" aria-labelledby="titre-decharge">
                    <h3 id="titre-decharge" class="font-display font-bold text-encre">Décharge de responsabilité</h3>
                    <p class="mt-1 text-sm text-gris-texte">{{ $pour ? "En tant que responsable, tu remplis la décharge pour {$pour}. " : '' }}Toutes les cases sont obligatoires.</p>
                    <x-questionnaire-decharge class="mt-4" :questions="$this->questionsDecharge" :evenement="$this->evenement" :signataire="auth()->user()->nom.' '.auth()->user()->prenom" />
                </section>

                {{-- Questionnaire de santé --}}
                @if ($this->santeRequise)
                    <section class="mt-4 rounded-2xl border border-bordure p-4 sm:p-5" aria-labelledby="titre-sante">
                        <h3 id="titre-sante" class="font-display font-bold text-encre">Questionnaire de santé</h3>
                        <p class="mt-1 text-sm text-gris-texte">Tes réponses sont confidentielles : seules les personnes habilitées de l'association peuvent les consulter.</p>
                        <div class="mt-2 divide-y divide-bordure">
                            @foreach ($this->questionsSante as $q)
                                <x-question-oui-non :question="$q" :numero="$loop->iteration" modele="reponsesSante" />
                            @endforeach
                        </div>

                        <div class="mt-3 border-t border-bordure pt-4">
                            <label class="flex items-start gap-3 text-sm text-encre">
                                <input wire:model="attestationSante" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-bordure text-canard focus:ring-canard">
                                <span>{{ $pour ? "J'atteste avoir répondu avec exactitude pour {$pour}." : "J'atteste avoir répondu avec exactitude à toutes les questions." }}</span>
                            </label>
                            @error('attestationSante') <p class="rfl-erreur">{{ $message }}</p> @enderror
                        </div>
                    </section>
                @endif
            @endif
        </section>

        @if ($errors->hasAny(['reponsesDecharge.*', 'reponsesSante.*']))
            <p class="mt-6 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">Il manque des réponses ou des cases à cocher : elles sont signalées plus haut.</p>
        @endif

        <div class="mt-8 flex items-center justify-between">
            <button type="button" wire:click="retour" class="rfl-lien">Retour</button>
            <button type="button" wire:click="documentsSuivant" @disabled(! ($accepteReglement && $accepteRgpd && $questionnaires)) wire:loading.attr="disabled" class="rfl-btn">Continuer</button>
        </div>
    @endif

    {{-- Don (écran 07) --}}
    @if ($etape === 'don')
        <form wire:submit="donSuivant" novalidate>
            <h1 class="font-display text-2xl font-bold text-encre">Un coup de pouce en plus ?</h1>
            <p class="mt-1 mb-6 text-sm text-gris-texte">Facultatif. Chaque euro va à la cause de Run For Lorène : la santé mentale des jeunes.</p>

            <div class="flex flex-wrap gap-2">
                @foreach ([5, 10, 20, 50] as $montant)
                    <button type="button" wire:click="choisirDon({{ $montant }})" aria-pressed="{{ $don === (string) $montant ? 'true' : 'false' }}" class="rounded-full border px-5 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-canard {{ $don === (string) $montant ? 'border-canard bg-aqua-clair text-canard' : 'border-bordure text-encre hover:border-canard/50' }}">
                        {{ $montant }} €
                    </button>
                @endforeach
            </div>

            <label for="don" class="rfl-label mt-6">Autre montant (en euros)</label>
            <input wire:model.live.debounce.400ms="don" id="don" type="text" inputmode="decimal" placeholder="0" class="rfl-champ">
            @error('don') <p class="rfl-erreur">{{ $message }}</p> @enderror

            @if ($this->montantDon() > 0)
                <div class="mt-4 space-y-3">
                    <label class="flex items-start gap-3 text-sm text-encre">
                        <input wire:model="recuFiscal" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-bordure text-canard focus:ring-canard">
                        <span>Je souhaite un reçu fiscal <span class="text-gris-texte">(réduction d'impôt de 66 % du don)</span></span>
                    </label>
                    <label class="flex items-start gap-3 text-sm text-encre">
                        <input wire:model="anonyme" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-bordure text-canard focus:ring-canard">
                        <span>Rester anonyme dans les remerciements publics</span>
                    </label>
                </div>
            @endif

            <div class="mt-8 flex items-center justify-between">
                <button type="button" wire:click="retour" class="rfl-lien">Retour</button>
                <div class="flex items-center gap-4">
                    <button type="button" wire:click="donPasser" class="rfl-lien">Passer</button>
                    <button type="submit" class="rfl-btn" @disabled($this->montantDon() <= 0)>Ajouter {{ $this->euros($this->montantDon()) }}</button>
                </div>
            </div>
        </form>
    @endif

    {{-- Récapitulatif (écran 08) --}}
    @if ($etape === 'recap')
        <h1 class="font-display text-2xl font-bold text-encre">On vérifie ensemble ?</h1>

        @if (session('erreur'))
            <p class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ session('erreur') }}</p>
        @endif
        @error('commande') <p class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror

        <div class="mt-4 divide-y divide-bordure">
            @foreach ($this->panier as $i)
                <div class="py-4" wire:key="ligne-{{ $i->id_inscription }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-display font-bold text-encre">{{ $i->participant->user->prenom }} {{ $i->participant->user->nom }}</p>
                            <p class="text-sm text-gris-texte">{{ $i->tarif->course->nom }} &middot; {{ $i->participant->ageAu($this->evenement->date_evenement) }} ans le jour J</p>
                        </div>
                        <p class="shrink-0 font-display font-bold text-encre">{{ $this->euros((float) ($this->tarifActuel($i)?->montant ?? $i->tarif->montant)) }}</p>
                    </div>
                    <p class="mt-2 text-sm text-gris-texte">
                        {{ $i->consentementsAcceptes() ? 'Consentements acceptés' : 'Consentements à compléter' }}
                        @if (! $i->documentsManquants())
                            &middot; {{ $i->tarif->course->questionnaire_requis ? 'décharge et questionnaire de santé remplis' : 'décharge remplie' }}
                        @endif
                    </p>
                    @if ($manquants = $i->documentsManquants())
                        <p class="mt-2 rounded-lg bg-moutarde/15 px-3 py-2 text-sm text-encre">
                            <span class="font-medium">Pas de départ sans {{ implode(' et ', $manquants) }}</span> : à remplir depuis ton espace après le paiement.
                        </p>
                    @endif
                    @isset($this->alertesPanier[$i->id_inscription])
                        <p class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $this->alertesPanier[$i->id_inscription] }}</p>
                    @endisset
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <button type="button" wire:click="modifier({{ $i->id_inscription }}, 'participant')" class="rfl-lien">Participant</button>
                        <button type="button" wire:click="modifier({{ $i->id_inscription }}, 'epreuve')" class="rfl-lien">Épreuve</button>
                        <button type="button" wire:click="modifier({{ $i->id_inscription }}, 'documents')" class="rfl-lien">Documents</button>
                        <button type="button" wire:click="retirer({{ $i->id_inscription }})" wire:confirm="Retirer {{ $i->participant->user->prenom }} de la commande ?" class="font-medium text-rose-700 hover:underline">Retirer</button>
                    </div>
                </div>
            @endforeach

            <div class="flex items-start justify-between gap-3 py-4">
                <div>
                    <p class="text-sm text-gris-texte">Don</p>
                    <p class="font-display font-bold text-encre">{{ $this->montantDon() > 0 ? $this->euros($this->montantDon()) : 'Pas de don' }}</p>
                    @if ($this->montantDon() > 0 && $recuFiscal) <p class="text-sm text-gris-texte">Reçu fiscal demandé</p> @endif
                </div>
                <button type="button" wire:click="modifierDon" class="text-sm rfl-lien">Modifier</button>
            </div>
        </div>

        <button type="button" wire:click="ajouterParticipant" class="mt-2 w-full rounded-xl border-2 border-dashed border-bordure px-4 py-3 text-sm font-medium text-canard hover:border-canard/50">+ Inscrire une autre personne</button>

        <div class="mt-6 flex items-center justify-between border-t border-bordure pt-4">
            <p class="font-display text-lg font-bold text-encre">Total</p>
            <p class="font-display text-lg font-bold text-encre">{{ $this->euros($this->totalPanier) }}</p>
        </div>

        <div class="mt-8 flex items-center justify-between">
            <button type="button" wire:click="retour" class="rfl-lien">Retour</button>
            <button type="button" wire:click="payer" wire:loading.attr="disabled" @disabled($this->alertesPanier || $this->panier->isEmpty()) class="rfl-btn">
                <span wire:loading.remove wire:target="payer">{{ $this->totalPanier > 0 ? 'Payer '.$this->euros($this->totalPanier) : 'Valider mon inscription' }}</span>
                <span wire:loading wire:target="payer">Un instant…</span>
            </button>
        </div>
        @if ($this->totalPanier > 0)
            <p class="mt-4 text-center text-xs text-gris-texte">Paiement sécurisé par carte, Apple Pay ou Google Pay. Ta place est réservée 30 minutes pendant le paiement.</p>
        @endif
    @endif

    </div>
</div>
