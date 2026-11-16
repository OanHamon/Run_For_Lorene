<?php

use App\Models\CandidatureBenevole;
use App\Models\CandidatureStatut;
use App\Models\Course;
use App\Models\Evenement;
use App\Notifications\CandidatureRecue;
use App\Services\Benevoles\FichiersCandidature;
use App\Services\Inscriptions\Consentements;
use App\Support\Benevolat;
use App\Support\Telephone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/*
 * Candidature bénévole (brief complémentaire), en panneaux comme le tunnel d'inscription :
 * Toi → Missions → Disponibilités → Pratique → Photo et envoi.
 *
 * Chaque « Continuer » enregistre un brouillon côté serveur : on reprend où on s'était arrêté.
 * Une fois envoyée, la candidature n'est plus modifiable (décision du 16/11/2026) : seuls la
 * photo du badge et l'autorisation parentale se déposent encore depuis l'espace.
 * Pas d'âge minimum ; moins de 16 ans le jour de l'événement : autorisation parentale signée.
 */
new #[Layout('components.tunnel-shell', ['title' => 'Candidature bénévole · Run For Lorène'])] class extends Component {
    use WithFileUploads;

    public const ETAPES = [
        'toi' => [1, 'Toi'],
        'missions' => [2, 'Missions'],
        'dispos' => [3, 'Disponibilités'],
        'pratique' => [4, 'Pratique'],
        'envoi' => [5, 'Photo et envoi'],
    ];

    #[Locked]
    public string $etape = 'toi';

    public string $sens = 'avant';

    #[Locked]
    public ?int $candidatureId = null;

    // Toi
    public string $telephone = '';

    public string $age = '';

    public string $responsableNom = '';

    public string $responsableTel = '';

    public string $urgenceNom = '';

    public string $urgenceTel = '';

    public string $urgenceLien = '';

    // Missions
    /** @var list<string> codes, dans l'ordre où ils ont été choisis */
    public array $preferees = [];

    /** @var list<string> */
    public array $impossibles = [];

    /** @var list<string> */
    public array $competences = [];

    public string $experience = '';

    public string $competencesDetail = '';

    public string $commentaire = '';

    // Disponibilités
    public bool $touteJournee = false;

    /** @var list<string> */
    public array $tranches = [];

    /** @var array<string, array{etat: string, debut: string, fin: string}> */
    public array $periodes = [];

    public string $vehicule = '';

    public string $transport = '';

    public string $transportDetail = '';

    public string $binome = '';

    public string $binomeNom = '';

    public string $court = '';

    public string $courseId = '';

    // Pratique
    public string $taille = '';

    public string $repas = '';

    public string $allergies = '';

    public string $allergiesDetail = '';

    public string $soiree = '';

    // Envoi
    public string $droitImage = '';

    public $photo = null;

    public $autorisation = null;

    public function mount(): void
    {
        foreach (Benevolat::PERIODES as $cle => $p) {
            $this->periodes[$cle] = ['etat' => '', 'debut' => '', 'fin' => ''];
        }

        $this->telephone = Telephone::lisible(Auth::user()->telephone);

        if (! $this->evenement || ! Benevolat::ouvert()) {
            $this->etape = 'ferme';

            return;
        }

        $candidature = CandidatureBenevole::duCompte(Auth::user());

        if ($candidature?->envoyee()) {
            $this->redirectRoute('espace.benevole');

            return;
        }

        if ($candidature) {
            $this->charger($candidature);
            $this->etape = array_search((int) $candidature->etape, array_map(fn ($e) => $e[0], $this::ETAPES), true) ?: 'toi';
        }
    }

    #[Computed]
    public function evenement(): ?Evenement
    {
        return Evenement::courant();
    }

    #[Computed]
    public function candidature(): ?CandidatureBenevole
    {
        return $this->candidatureId ? CandidatureBenevole::where('id_user', Auth::id())->find($this->candidatureId) : null;
    }

    /** @return Collection<int, Course> */
    #[Computed]
    public function courses(): Collection
    {
        return $this->evenement?->courses()->orderBy('heure_depart')->get() ?? collect();
    }

    #[Computed]
    public function texteDroitImage()
    {
        return $this->evenement ? app(Consentements::class)->textes($this->evenement)->get('droit_image') : null;
    }

    #[Computed]
    public function texteAutorisation()
    {
        return $this->evenement ? app(Consentements::class)->textes($this->evenement)->get('autorisation_benevole') : null;
    }

    // ------------------------------------------------------------------ Navigation

    public function retour(): void
    {
        $ordre = array_keys($this::ETAPES);
        $i = array_search($this->etape, $ordre, true);

        if ($i === false || $i === 0) {
            $this->redirectRoute('benevoles');

            return;
        }

        $this->aller($ordre[$i - 1], 'arriere');
    }

    private function aller(string $etape, string $sens = 'avant'): void
    {
        $this->sens = $sens;
        $this->etape = $etape;
        $this->resetErrorBag();
    }

    /** Enregistre le brouillon (créé au premier panneau) et note le panneau atteint. */
    private function sauver(array $attributs, string $suivante): void
    {
        if (! $this->evenement || ! Benevolat::ouvert()) {
            $this->aller('ferme');

            return;
        }

        $candidature = $this->candidature ?? CandidatureBenevole::firstOrCreate(
            ['id_user' => Auth::id(), 'id_evenement' => $this->evenement->id_evenement],
            ['statut' => CandidatureBenevole::BROUILLON],
        );

        // Envoyée entre-temps (autre onglet) : plus rien ne se modifie ici.
        if ($candidature->envoyee()) {
            $this->redirectRoute('espace.benevole');

            return;
        }

        $candidature->fill($attributs);
        $candidature->etape = max((int) $candidature->etape, $this::ETAPES[$suivante][0]);
        $candidature->save();

        $this->candidatureId = $candidature->id_candidature;
        unset($this->candidature);
        $this->aller($suivante);
    }

    // ------------------------------------------------------------------ Panneaux

    public function toiSuivant(): void
    {
        $this->telephone = Telephone::normaliser($this->telephone);
        $this->responsableTel = Telephone::normaliser($this->responsableTel);
        $this->urgenceTel = Telephone::normaliser($this->urgenceTel);
        $mineur = in_array($this->age, ['moins_16', '16_17'], true);

        $this->validate([
            'telephone' => ['required', ...array_slice(Telephone::REGLE, 1)],
            'age' => 'required|in:'.implode(',', array_keys(Benevolat::AGES)),
            'responsableNom' => [$mineur ? 'required' : 'nullable', 'string', 'max:150'],
            'responsableTel' => [$mineur ? 'required' : 'nullable', ...array_slice(Telephone::REGLE, 1)],
            'urgenceNom' => 'required|string|max:150',
            'urgenceTel' => ['required', ...array_slice(Telephone::REGLE, 1)],
            'urgenceLien' => 'nullable|string|max:100',
        ], [
            'telephone.regex' => Telephone::MESSAGE,
            'responsableTel.regex' => Telephone::MESSAGE,
            'urgenceTel.regex' => Telephone::MESSAGE,
            'age.required' => 'Indique ton âge le jour de l\'événement.',
        ], [
            'telephone' => 'téléphone',
            'responsableNom' => 'nom du responsable légal',
            'responsableTel' => 'téléphone du responsable légal',
            'urgenceNom' => 'nom du contact d\'urgence',
            'urgenceTel' => 'téléphone du contact d\'urgence',
        ]);

        if (! Auth::user()->telephone) {
            Auth::user()->forceFill(['telephone' => $this->telephone])->save();
        }

        $this->sauver([
            'telephone' => $this->telephone,
            'age' => $this->age,
            'responsable_nom' => $mineur ? trim($this->responsableNom) : null,
            'responsable_tel' => $mineur ? $this->responsableTel : null,
            'urgence_nom' => trim($this->urgenceNom),
            'urgence_tel' => $this->urgenceTel,
            'urgence_lien' => trim($this->urgenceLien) ?: null,
        ], 'missions');
    }

    /** Coche ou décoche une mission préférée : l'ordre des clics donne la priorité. */
    public function basculerMission(string $code): void
    {
        if (! isset(Benevolat::MISSIONS[$code])) {
            return;
        }

        if (in_array($code, $this->preferees, true)) {
            $this->preferees = array_values(array_diff($this->preferees, [$code]));
        } else {
            $this->preferees[] = $code;
            $this->impossibles = array_values(array_diff($this->impossibles, [$code]));
        }
    }

    public function missionsSuivant(): void
    {
        $codes = implode(',', array_keys(Benevolat::MISSIONS));
        $this->preferees = array_values(array_unique($this->preferees));
        $this->impossibles = array_values(array_unique(array_diff($this->impossibles, $this->preferees)));
        $communication = in_array('communication', $this->preferees, true);

        $this->validate([
            'preferees' => 'required|array|min:1',
            'preferees.*' => 'in:'.$codes,
            'impossibles' => 'array',
            'impossibles.*' => 'in:'.$codes,
            'competences' => 'array',
            'competences.*' => 'in:'.implode(',', array_keys(Benevolat::COMPETENCES)),
            'experience' => 'nullable|string|max:1000',
            'competencesDetail' => 'nullable|string|max:1000',
            'commentaire' => 'nullable|string|max:1000',
        ], [
            'preferees.required' => 'Choisis au moins une mission.',
            'preferees.min' => 'Choisis au moins une mission.',
        ]);

        $this->sauver([
            'missions_preferees' => $this->preferees,
            'missions_impossibles' => $this->impossibles,
            'competences' => $communication ? array_values($this->competences) : [],
            'experience' => trim($this->experience) ?: null,
            'competences_detail' => trim($this->competencesDetail) ?: null,
            'commentaire' => trim($this->commentaire) ?: null,
        ], 'dispos');
    }

    public function disposSuivant(): void
    {
        $regles = [
            'tranches' => 'array',
            'tranches.*' => 'in:'.implode(',', array_keys(Benevolat::TRANCHES)),
            'vehicule' => 'required|in:oui,non',
            'transport' => [$this->vehicule === 'oui' ? 'required' : 'nullable', 'in:oui,non'],
            'transportDetail' => 'nullable|string|max:500',
            'binome' => 'required|in:oui,non',
            'binomeNom' => [$this->binome === 'oui' ? 'required' : 'nullable', 'string', 'max:150'],
            'court' => 'required|in:oui,non',
            'courseId' => ['nullable', 'in:'.$this->courses->pluck('id_course')->join(',')],
        ];
        $attributs = ['binomeNom' => 'nom de ton binôme'];

        foreach (Benevolat::PERIODES as $cle => $p) {
            $regles["periodes.{$cle}.etat"] = 'required|in:'.implode(',', array_keys(Benevolat::ETATS_PERIODE));
            $regles["periodes.{$cle}.debut"] = 'nullable|date_format:H:i';
            $regles["periodes.{$cle}.fin"] = 'nullable|date_format:H:i';
            $attributs["periodes.{$cle}.etat"] = mb_strtolower($p['libelle']);
        }

        $this->validate($regles, [
            'vehicule.required' => 'Dis-nous si tu as un véhicule.',
            'transport.required' => 'Dis-nous si tu acceptes d\'aider au transport du matériel.',
            'binome.required' => 'Dis-nous si tu souhaites être en binôme.',
            'court.required' => 'Dis-nous si tu cours aussi.',
            'periodes.*.etat.required' => 'Choisis une réponse pour :attribute.',
        ], $attributs);

        if (! $this->touteJournee && $this->tranches === []) {
            $this->addError('tranches', 'Choisis « toute la journée » ou au moins un créneau.');

            return;
        }

        $periodes = [];
        foreach (Benevolat::PERIODES as $cle => $p) {
            $etat = $this->periodes[$cle]['etat'];
            $periodes[$cle] = [
                'etat' => $etat,
                'debut' => $etat === 'disponible' ? ($this->periodes[$cle]['debut'] ?: null) : null,
                'fin' => $etat === 'disponible' ? ($this->periodes[$cle]['fin'] ?: null) : null,
            ];
        }

        $this->sauver([
            'disponibilites' => [
                'toute_journee' => $this->touteJournee,
                'tranches' => $this->touteJournee ? [] : array_values(array_intersect(array_keys(Benevolat::TRANCHES), $this->tranches)),
                'periodes' => $periodes,
            ],
            'vehicule' => $this->vehicule === 'oui',
            'transport' => $this->vehicule === 'oui' ? $this->transport === 'oui' : null,
            'transport_detail' => $this->vehicule === 'oui' && $this->transport === 'oui' ? (trim($this->transportDetail) ?: null) : null,
            'binome' => $this->binome === 'oui' ? trim($this->binomeNom) : null,
            'court' => $this->court === 'oui',
            'id_course' => $this->court === 'oui' && $this->courseId !== '' ? (int) $this->courseId : null,
        ], 'pratique');
    }

    public function pratiqueSuivant(): void
    {
        $this->validate([
            'taille' => 'required|in:'.implode(',', Benevolat::TAILLES),
            'repas' => 'required|in:oui,non',
            'allergies' => [$this->repas === 'oui' ? 'required' : 'nullable', 'in:aucune,oui'],
            'allergiesDetail' => [$this->repas === 'oui' && $this->allergies === 'oui' ? 'required' : 'nullable', 'string', 'max:500'],
            'soiree' => 'required|in:'.implode(',', array_keys(Benevolat::SOIREE)),
        ], [
            'taille.required' => 'Choisis ta taille de tee-shirt.',
            'repas.required' => 'Dis-nous si tu as besoin d\'un repas.',
            'allergies.required' => 'Dis-nous si tu as des allergies ou des besoins alimentaires.',
            'allergiesDetail.required' => 'Précise tes allergies ou besoins alimentaires.',
            'soiree.required' => 'Choisis une réponse.',
        ]);

        $this->sauver([
            'taille_tshirt' => $this->taille,
            'repas' => $this->repas === 'oui',
            'allergies' => $this->repas === 'oui' ? ($this->allergies === 'oui' ? trim($this->allergiesDetail) : 'Aucune') : null,
            'soiree' => $this->soiree,
        ], 'envoi');
    }

    // ------------------------------------------------------------------ Fichiers (déposés dès le choix)

    public function updatedPhoto(): void
    {
        $this->deposer('photo', FichiersCandidature::PHOTO_REGLES);
    }

    public function updatedAutorisation(): void
    {
        $this->deposer('autorisation', FichiersCandidature::AUTORISATION_REGLES);
    }

    private function deposer(string $type, array $regles): void
    {
        $this->validate([$type => $regles], [
            'photo.image' => 'Choisis une photo (JPG, PNG ou WebP).',
            'photo.mimes' => 'Choisis une photo (JPG, PNG ou WebP).',
            'photo.max' => 'La photo dépasse 5 Mo.',
            'autorisation.mimes' => 'Dépose un PDF ou une photo (JPG, PNG).',
            'autorisation.max' => 'Le fichier dépasse 10 Mo.',
        ]);

        $candidature = $this->candidature;

        if (! $candidature || $candidature->envoyee()) {
            $this->reset($type);

            return;
        }

        app(FichiersCandidature::class)->deposer($candidature, $type, $this->{$type});
        $this->reset($type);
        unset($this->candidature);
    }

    // ------------------------------------------------------------------ Envoi

    public function envoyer(): void
    {
        $this->validate(['droitImage' => 'required|in:oui,non'], [
            'droitImage.required' => 'Réponds à la question du droit à l\'image.',
        ]);

        $candidature = $this->candidature;

        if (! $candidature || ! $this->evenement || ! Benevolat::ouvert()) {
            $this->aller('ferme');

            return;
        }

        // Tous les panneaux doivent avoir été validés (pas de saut d'étape depuis le navigateur).
        if ((int) $candidature->etape < $this::ETAPES['envoi'][0]) {
            $this->aller('toi');

            return;
        }

        $envoyee = DB::transaction(function () use ($candidature) {
            $candidature = CandidatureBenevole::lockForUpdate()->find($candidature->id_candidature);

            if ($candidature->envoyee()) {
                return null;
            }

            $candidature->fill([
                'statut' => CandidatureBenevole::RECUE,
                'envoyee_le' => now(),
                'droit_image' => $this->droitImage === 'oui',
                'droit_image_le' => now(),
                'id_texte_droit_image' => $this->texteDroitImage?->id_texte,
            ])->save();

            CandidatureStatut::create([
                'id_candidature' => $candidature->id_candidature,
                'statut' => CandidatureBenevole::RECUE,
                'notifie' => true,
            ]);

            return $candidature;
        });

        if ($envoyee) {
            Auth::user()->notify(new CandidatureRecue($envoyee));
            session()->flash('statut', 'Merci pour ces informations et pour ta proposition d\'aide. Ta candidature a bien été enregistrée. L\'organisation reviendra vers toi pour confirmer ou non ta participation, selon les besoins et tes disponibilités.');
        }

        $this->redirectRoute('espace.benevole');
    }

    // ------------------------------------------------------------------

    private function charger(CandidatureBenevole $c): void
    {
        $this->candidatureId = $c->id_candidature;
        $oui = fn (?bool $v) => $v === null ? '' : ($v ? 'oui' : 'non');

        $this->telephone = Telephone::lisible($c->telephone) ?: $this->telephone;
        $this->age = (string) $c->age;
        $this->responsableNom = (string) $c->responsable_nom;
        $this->responsableTel = Telephone::lisible($c->responsable_tel);
        $this->urgenceNom = (string) $c->urgence_nom;
        $this->urgenceTel = Telephone::lisible($c->urgence_tel);
        $this->urgenceLien = (string) $c->urgence_lien;

        $this->preferees = $c->missions_preferees ?? [];
        $this->impossibles = $c->missions_impossibles ?? [];
        $this->competences = $c->competences ?? [];
        $this->experience = (string) $c->experience;
        $this->competencesDetail = (string) $c->competences_detail;
        $this->commentaire = (string) $c->commentaire;

        $dispos = $c->disponibilites ?? [];
        $this->touteJournee = (bool) ($dispos['toute_journee'] ?? false);
        $this->tranches = $dispos['tranches'] ?? [];
        foreach (Benevolat::PERIODES as $cle => $p) {
            $this->periodes[$cle] = [
                'etat' => (string) ($dispos['periodes'][$cle]['etat'] ?? ''),
                'debut' => (string) ($dispos['periodes'][$cle]['debut'] ?? ''),
                'fin' => (string) ($dispos['periodes'][$cle]['fin'] ?? ''),
            ];
        }
        $this->vehicule = $oui($c->vehicule);
        $this->transport = $oui($c->transport);
        $this->transportDetail = (string) $c->transport_detail;
        $this->binome = $c->etape >= 4 ? ($c->binome ? 'oui' : 'non') : '';
        $this->binomeNom = (string) $c->binome;
        $this->court = $oui($c->court);
        $this->courseId = (string) $c->id_course;

        $this->taille = (string) $c->taille_tshirt;
        $this->repas = $oui($c->repas);
        $this->allergies = $c->allergies === null ? '' : ($c->allergies === 'Aucune' ? 'aucune' : 'oui');
        $this->allergiesDetail = $c->allergies !== 'Aucune' ? (string) $c->allergies : '';
        $this->soiree = (string) $c->soiree;
    }
}; ?>

@php
    $date = fn (int $decalage) => $this->evenement?->date_evenement->copy()->addDays($decalage)->translatedFormat('l j F');
    $choix = 'flex cursor-pointer items-center gap-3 rounded-2xl border-2 px-4 py-3 transition-colors has-[:checked]:border-vert has-[:checked]:bg-vert-clair has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-vert';
    $c = $this->candidature;
@endphp

<div>
    @if (isset($this::ETAPES[$etape]))
        <div class="mb-6">
            <p class="mb-2 text-sm text-gris-texte">Étape {{ $this::ETAPES[$etape][0] }} sur {{ count($this::ETAPES) }} &middot; {{ $this::ETAPES[$etape][1] }}</p>
            <div class="h-2 w-full overflow-hidden rounded-full bg-vert-clair/70">
                <div class="h-full rounded-full bg-vert transition-all duration-300" style="width: {{ round($this::ETAPES[$etape][0] / count($this::ETAPES) * 100) }}%"></div>
            </div>
        </div>
    @endif

    <div wire:key="panneau-{{ $etape }}" class="{{ $sens === 'avant' ? 'rfl-glisse-avant' : 'rfl-glisse-arriere' }}"
        x-data x-init="
            if (! window.rflPanneauVu) { window.rflPanneauVu = true; return; }
            window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            $nextTick(() => {
                const titre = $el.querySelector('h1, h2');
                if (titre) { titre.setAttribute('tabindex', '-1'); titre.classList.add('outline-none'); titre.focus({ preventScroll: true }); }
            });
        ">

    {{-- Candidatures fermées --}}
    @if ($etape === 'ferme')
        <h1 class="font-display text-2xl text-vert">Candidatures fermées</h1>
        <p class="mt-3 text-sm text-gris-texte">Les candidatures bénévoles ne sont pas ouvertes pour le moment. Reviens bientôt !</p>
        <div class="mt-8 flex items-center justify-between">
            <a href="{{ route('benevoles') }}" class="rfl-lien">Retour</a>
            <a href="{{ route('espace') }}" class="rfl-btn">Mon espace</a>
        </div>
    @endif

    {{-- 1. Toi --}}
    @if ($etape === 'toi')
        <form wire:submit="toiSuivant" novalidate>
            <h1 class="font-display text-2xl text-vert">Faisons connaissance</h1>
            <p class="mt-1 mb-6 text-sm text-gris-texte">{{ auth()->user()->prenom }} {{ auth()->user()->nom }} &middot; {{ auth()->user()->email }}. Pas le bon nom ? <a href="{{ route('espace.compte') }}" class="rfl-lien">Modifie-le dans ton compte</a>.</p>

            <label for="telephone" class="rfl-label">Ton téléphone</label>
            <input wire:model="telephone" id="telephone" type="tel" inputmode="tel" autocomplete="tel" placeholder="06 12 34 56 78" required class="rfl-champ">
            @error('telephone') <p class="rfl-erreur">{{ $message }}</p> @enderror

            <fieldset class="mt-6">
                <legend class="rfl-label">Ton âge le jour de l'événement</legend>
                <div class="mt-2 space-y-2">
                    @foreach (Benevolat::AGES as $valeur => $libelle)
                        <label class="{{ $choix }} border-bordure">
                            <input type="radio" wire:model.live="age" value="{{ $valeur }}" class="accent-vert">
                            <span class="text-vert-sombre">{{ $libelle }}</span>
                        </label>
                    @endforeach
                </div>
                @error('age') <p class="rfl-erreur">{{ $message }}</p> @enderror
            </fieldset>

            @if (in_array($age, ['moins_16', '16_17'], true))
                <div class="mt-6 rounded-2xl bg-fond p-4">
                    <p class="font-display text-vert">Ton responsable légal</p>
                    @if ($age === 'moins_16')
                        <p class="mt-1 text-sm text-gris-texte">Comme tu as moins de 16 ans, il nous faudra aussi son autorisation signée : tu la déposes à la dernière étape.</p>
                    @endif
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="responsableNom" class="rfl-label">Nom et prénom</label>
                            <input wire:model="responsableNom" id="responsableNom" type="text" autocomplete="off" class="rfl-champ">
                            @error('responsableNom') <p class="rfl-erreur">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="responsableTel" class="rfl-label">Téléphone</label>
                            <input wire:model="responsableTel" id="responsableTel" type="tel" inputmode="tel" autocomplete="off" class="rfl-champ">
                            @error('responsableTel') <p class="rfl-erreur">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-6 border-t border-bordure pt-6">
                <p class="font-display text-vert">Une personne à prévenir en cas d'urgence</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="urgenceNom" class="rfl-label">Nom et prénom</label>
                        <input wire:model="urgenceNom" id="urgenceNom" type="text" autocomplete="off" class="rfl-champ">
                        @error('urgenceNom') <p class="rfl-erreur">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="urgenceTel" class="rfl-label">Téléphone</label>
                        <input wire:model="urgenceTel" id="urgenceTel" type="tel" inputmode="tel" autocomplete="off" class="rfl-champ">
                        @error('urgenceTel') <p class="rfl-erreur">{{ $message }}</p> @enderror
                    </div>
                </div>
                <label for="urgenceLien" class="mt-4 rfl-label">Son lien avec toi <span class="font-normal text-gris-texte">(facultatif)</span></label>
                <input wire:model="urgenceLien" id="urgenceLien" type="text" placeholder="Parent, ami(e), conjoint(e)…" class="rfl-champ">
            </div>

            <div class="mt-8 flex items-center justify-between">
                <button type="button" wire:click="retour" class="rfl-lien">Retour</button>
                <button type="submit" class="rfl-btn" wire:loading.attr="disabled">Continuer</button>
            </div>
        </form>
    @endif

    {{-- 2. Missions --}}
    @if ($etape === 'missions')
        <form wire:submit="missionsSuivant" novalidate>
            <h1 class="font-display text-2xl text-vert">Tes missions préférées</h1>
            <p class="mt-1 mb-6 text-sm text-gris-texte">Choisis-les dans l'ordre de tes préférences : la première choisie est ta préférée. C'est un souhait, l'organisation confirme ensuite selon les besoins.</p>

            <div class="space-y-2" role="group" aria-label="Missions préférées">
                @foreach (Benevolat::MISSIONS as $code => $mission)
                    @php($rang = array_search($code, $preferees, true))
                    <button type="button" wire:click="basculerMission('{{ $code }}')" aria-pressed="{{ $rang !== false ? 'true' : 'false' }}"
                        class="flex w-full items-start gap-3 rounded-2xl border-2 px-4 py-3 text-left transition-colors focus-visible:outline-2 focus-visible:outline-vert {{ $rang !== false ? 'border-vert bg-vert-clair' : 'border-bordure hover:border-vert/50' }}">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full font-display text-sm {{ $rang !== false ? 'bg-vert text-white' : 'ring-2 ring-bordure text-transparent' }}">{{ $rang !== false ? $rang + 1 : '·' }}</span>
                        <span>
                            <span class="block font-bold text-vert-sombre">{{ $mission['libelle'] }}</span>
                            <span class="block text-sm text-gris-texte">{{ $mission['description'] }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
            @error('preferees') <p class="rfl-erreur">{{ $message }}</p> @enderror

            @if (in_array('communication', $preferees, true))
                <fieldset class="mt-6 rounded-2xl bg-fond p-4">
                    <legend class="sr-only">Compétences en communication</legend>
                    <p class="font-display text-vert">En communication, tu sais faire…</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        @foreach (Benevolat::COMPETENCES as $valeur => $libelle)
                            <label class="{{ $choix }} border-bordure bg-white">
                                <input type="checkbox" wire:model="competences" value="{{ $valeur }}" class="accent-vert">
                                <span class="text-vert-sombre">{{ $libelle }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <details class="mt-6 rounded-2xl border border-bordure p-4" @if ($impossibles) open @endif>
                <summary class="cursor-pointer font-display text-vert">Des missions que tu ne peux pas faire ?</summary>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach (Benevolat::MISSIONS as $code => $mission)
                        @continue(in_array($code, $preferees, true))
                        <label class="{{ $choix }} border-bordure">
                            <input type="checkbox" wire:model="impossibles" value="{{ $code }}" class="accent-vert">
                            <span class="text-sm text-vert-sombre">{{ $mission['libelle'] }}</span>
                        </label>
                    @endforeach
                </div>
            </details>

            <label for="experience" class="mt-6 rfl-label">Une expérience utile ? <span class="font-normal text-gris-texte">(facultatif)</span></label>
            <textarea wire:model="experience" id="experience" rows="2" placeholder="Bénévolat, secourisme, animation, restauration…" class="rfl-champ"></textarea>

            <label for="competencesDetail" class="mt-4 rfl-label">Des compétences particulières ? <span class="font-normal text-gris-texte">(facultatif)</span></label>
            <textarea wire:model="competencesDetail" id="competencesDetail" rows="2" class="rfl-champ"></textarea>

            <label for="commentaire" class="mt-4 rfl-label">Un mot à ajouter ? <span class="font-normal text-gris-texte">(facultatif)</span></label>
            <textarea wire:model="commentaire" id="commentaire" rows="2" class="rfl-champ"></textarea>

            <div class="mt-8 flex items-center justify-between">
                <button type="button" wire:click="retour" class="rfl-lien">Retour</button>
                <button type="submit" class="rfl-btn" wire:loading.attr="disabled">Continuer</button>
            </div>
        </form>
    @endif

    {{-- 3. Disponibilités --}}
    @if ($etape === 'dispos')
        <form wire:submit="disposSuivant" novalidate>
            <h1 class="font-display text-2xl text-vert">Tes disponibilités</h1>
            <p class="mt-1 mb-6 text-sm text-gris-texte">Le jour J{{ $date(0) ? ', '.$date(0) : '' }}. Les créneaux restent à confirmer par l'organisation.</p>

            <fieldset>
                <legend class="rfl-label">Le jour J, tu es disponible…</legend>
                <label class="mt-2 {{ $choix }} border-bordure">
                    <input type="checkbox" wire:model.live="touteJournee" class="accent-vert">
                    <span class="font-bold text-vert-sombre">Toute la journée</span>
                </label>
                @unless ($touteJournee)
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach (Benevolat::TRANCHES as $valeur => $libelle)
                            <label class="{{ $choix }} border-bordure">
                                <input type="checkbox" wire:model="tranches" value="{{ $valeur }}" class="accent-vert">
                                <span class="text-vert-sombre">{{ $libelle }}</span>
                            </label>
                        @endforeach
                    </div>
                @endunless
                @error('tranches') <p class="rfl-erreur">{{ $message }}</p> @enderror
            </fieldset>

            <div class="mt-6 border-t border-bordure pt-6">
                <p class="font-display text-vert">Installation et rangement</p>
                <p class="mt-1 text-sm text-gris-texte">Un coup de main avant et après l'événement, à part des missions du jour.</p>
                @foreach (Benevolat::PERIODES as $cle => $p)
                    <fieldset class="mt-4">
                        <legend class="rfl-label">{{ $p['libelle'] }}{{ $date($p['decalage']) ? ' ('.$date($p['decalage']).')' : '' }} <span class="font-normal text-gris-texte">: {{ $p['detail'] }}</span></legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach (Benevolat::ETATS_PERIODE as $valeur => $libelle)
                                <label class="{{ $choix }} border-bordure py-2">
                                    <input type="radio" wire:model.live="periodes.{{ $cle }}.etat" value="{{ $valeur }}" class="accent-vert">
                                    <span class="text-sm text-vert-sombre">{{ $libelle }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if ($periodes[$cle]['etat'] === 'disponible')
                            <div class="mt-2 flex items-center gap-2 text-sm text-vert-sombre">
                                <label for="debut-{{ $cle }}">De</label>
                                <input wire:model="periodes.{{ $cle }}.debut" id="debut-{{ $cle }}" type="time" class="rfl-champ w-32">
                                <label for="fin-{{ $cle }}">à</label>
                                <input wire:model="periodes.{{ $cle }}.fin" id="fin-{{ $cle }}" type="time" class="rfl-champ w-32">
                            </div>
                        @endif
                        @error("periodes.{$cle}.etat") <p class="rfl-erreur">{{ $message }}</p> @enderror
                    </fieldset>
                @endforeach
            </div>

            <div class="mt-6 border-t border-bordure pt-6">
                <x-oui-non name="vehicule" question="As-tu un véhicule ?" />
                @if ($vehicule === 'oui')
                    <div class="mt-4">
                        <x-oui-non name="transport" question="Acceptes-tu d'aider au transport du matériel ?" />
                    </div>
                    @if ($transport === 'oui')
                        <label for="transportDetail" class="mt-3 rfl-label">Quand, et que peux-tu transporter ? <span class="font-normal text-gris-texte">(facultatif)</span></label>
                        <textarea wire:model="transportDetail" id="transportDetail" rows="2" placeholder="Créneaux, type de véhicule, place disponible…" class="rfl-champ"></textarea>
                        <p class="mt-1 text-xs text-gris-texte">C'est une disponibilité, pas encore une mission de conduite : l'organisation te confirmera.</p>
                    @endif
                @endif
            </div>

            <div class="mt-6 border-t border-bordure pt-6">
                <x-oui-non name="binome" question="Souhaites-tu être en binôme avec quelqu'un ?" />
                @if ($binome === 'oui')
                    <label for="binomeNom" class="mt-3 rfl-label">Avec qui ?</label>
                    <input wire:model="binomeNom" id="binomeNom" type="text" autocomplete="off" class="rfl-champ">
                    <p class="mt-1 text-xs text-gris-texte">C'est un souhait : il dépend des besoins et des disponibilités de vous deux.</p>
                    @error('binomeNom') <p class="rfl-erreur">{{ $message }}</p> @enderror
                @endif
            </div>

            <div class="mt-6 border-t border-bordure pt-6">
                <x-oui-non name="court" question="Participes-tu aussi à la course ?" />
                @if ($court === 'oui')
                    @if ($this->courses->isNotEmpty())
                        <label for="courseId" class="mt-3 rfl-label">Quelle épreuve ?</label>
                        <select wire:model="courseId" id="courseId" class="rfl-champ">
                            <option value="">Je ne sais pas encore</option>
                            @foreach ($this->courses as $course)
                                <option value="{{ $course->id_course }}">{{ $course->nom }}{{ $course->heure_depart ? ' · départ '.\Illuminate\Support\Carbon::parse($course->heure_depart)->format('G\hi') : '' }}</option>
                            @endforeach
                        </select>
                    @endif
                    <p class="mt-2 text-xs text-gris-texte">On en tient compte pour ne pas te donner une mission au même moment. L'inscription à la course reste à faire à part.</p>
                @endif
            </div>

            <div class="mt-8 flex items-center justify-between">
                <button type="button" wire:click="retour" class="rfl-lien">Retour</button>
                <button type="submit" class="rfl-btn" wire:loading.attr="disabled">Continuer</button>
            </div>
        </form>
    @endif

    {{-- 4. Pratique --}}
    @if ($etape === 'pratique')
        <form wire:submit="pratiqueSuivant" novalidate>
            <h1 class="font-display text-2xl text-vert">Côté pratique</h1>
            <p class="mt-1 mb-6 text-sm text-gris-texte">Chaque bénévole reçoit un tee-shirt officiel, un badge à son nom et un livret qui explique les missions.</p>

            <fieldset>
                <legend class="rfl-label">Ta taille de tee-shirt</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (Benevolat::TAILLES as $t)
                        <label class="{{ $choix }} min-w-14 justify-center border-bordure py-2">
                            <input type="radio" wire:model="taille" value="{{ $t }}" class="sr-only">
                            <span class="font-bold text-vert-sombre">{{ $t }}</span>
                        </label>
                    @endforeach
                </div>
                @error('taille') <p class="rfl-erreur">{{ $message }}</p> @enderror
            </fieldset>

            <div class="mt-6 border-t border-bordure pt-6">
                <x-oui-non name="repas" question="As-tu besoin d'un repas sur place ?" />
                @if ($repas === 'oui')
                    <fieldset class="mt-4">
                        <legend class="rfl-label">Des allergies ou des besoins alimentaires ?</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <label class="{{ $choix }} border-bordure py-2"><input type="radio" wire:model.live="allergies" value="aucune" class="accent-vert"><span class="text-vert-sombre">Aucun</span></label>
                            <label class="{{ $choix }} border-bordure py-2"><input type="radio" wire:model.live="allergies" value="oui" class="accent-vert"><span class="text-vert-sombre">Oui</span></label>
                        </div>
                        @error('allergies') <p class="rfl-erreur">{{ $message }}</p> @enderror
                        @if ($allergies === 'oui')
                            <label for="allergiesDetail" class="mt-3 rfl-label">Précise</label>
                            <textarea wire:model="allergiesDetail" id="allergiesDetail" rows="2" class="rfl-champ"></textarea>
                            @error('allergiesDetail') <p class="rfl-erreur">{{ $message }}</p> @enderror
                        @endif
                        <p class="mt-2 text-xs text-gris-texte">Ces informations servent à préparer les repas. Les repas proposés seront confirmés par l'organisation.</p>
                    </fieldset>
                @endif
            </div>

            <div class="mt-6 border-t border-bordure pt-6">
                <p class="font-display text-vert">Les briefings</p>
                <p class="mt-1 text-sm text-vert-sombre">Plusieurs temps de présentation sont prévus avant l'événement pour découvrir l'organisation et les missions. Dates et modalités : informations à venir, on te les envoie par email.</p>
            </div>

            <fieldset class="mt-6 border-t border-bordure pt-6">
                <legend class="rfl-label">Une soirée entre bénévoles après l'événement, ça te dirait ?</legend>
                <p class="text-xs text-gris-texte">Elle n'est pas encore confirmée : on te demande juste ton envie.</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (Benevolat::SOIREE as $valeur => $libelle)
                        <label class="{{ $choix }} border-bordure py-2"><input type="radio" wire:model="soiree" value="{{ $valeur }}" class="accent-vert"><span class="text-vert-sombre">{{ $libelle }}</span></label>
                    @endforeach
                </div>
                @error('soiree') <p class="rfl-erreur">{{ $message }}</p> @enderror
            </fieldset>

            <div class="mt-8 flex items-center justify-between">
                <button type="button" wire:click="retour" class="rfl-lien">Retour</button>
                <button type="submit" class="rfl-btn" wire:loading.attr="disabled">Continuer</button>
            </div>
        </form>
    @endif

    {{-- 5. Photo et envoi --}}
    @if ($etape === 'envoi' && $c)
        <form wire:submit="envoyer" novalidate>
            <h1 class="font-display text-2xl text-vert">Dernière étape</h1>
            <p class="mt-1 mb-6 text-sm text-gris-texte">Une fois envoyée, ta candidature ne se modifie plus : relis-la avant d'envoyer.</p>

            <div class="rounded-2xl bg-fond p-4">
                <p class="font-display text-vert">Ta photo pour le badge</p>
                <p class="mt-1 text-sm text-gris-texte">Un portrait de toi, de face (pas une pièce d'identité). JPG, PNG ou WebP, 5 Mo maximum. Tu peux aussi l'ajouter plus tard depuis ton espace.</p>
                <div class="mt-3 flex items-center gap-4">
                    @if ($c->photo)
                        <img src="{{ route('espace.benevole.fichier', 'photo') }}?v={{ $c->photo_le?->timestamp }}" alt="Ta photo de badge" class="h-20 w-20 rounded-2xl object-cover ring-1 ring-bordure">
                    @endif
                    <label class="rfl-btn-secondaire cursor-pointer px-5 py-2 text-sm">
                        {{ $c->photo ? 'Changer de photo' : 'Choisir une photo' }}
                        <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                    </label>
                    <span wire:loading wire:target="photo" class="text-sm text-gris-texte">Envoi…</span>
                </div>
                @error('photo') <p class="rfl-erreur">{{ $message }}</p> @enderror
            </div>

            @if ($c->autorisationRequise())
                <div class="mt-4 rounded-2xl bg-fond p-4">
                    <p class="font-display text-vert">L'autorisation parentale</p>
                    <p class="mt-1 text-sm text-gris-texte">
                        Tu as moins de 16 ans : ton responsable légal signe l'autorisation, puis tu la déposes ici (PDF ou photo, 10 Mo maximum). Tu peux aussi la déposer plus tard depuis ton espace.
                    </p>
                    @if ($this->texteAutorisation)
                        <a href="{{ route('textes.show', 'autorisation_benevole') }}" target="_blank" rel="noopener" class="mt-2 inline-block rfl-lien text-sm">Télécharger l'autorisation à signer (PDF)</a>
                    @else
                        <p class="mt-2 text-sm text-gris-texte">Le modèle à signer arrive bientôt : on te préviendra.</p>
                    @endif
                    <div class="mt-3 flex items-center gap-4">
                        @if ($c->autorisation_parentale)
                            <span class="rounded-full bg-vert-clair px-3 py-1 text-xs font-bold text-vert">Déposée le {{ $c->autorisation_le->format('d/m/Y') }}</span>
                        @endif
                        <label class="rfl-btn-secondaire cursor-pointer px-5 py-2 text-sm">
                            {{ $c->autorisation_parentale ? 'Remplacer' : 'Déposer l\'autorisation signée' }}
                            <input type="file" wire:model="autorisation" accept="application/pdf,image/jpeg,image/png" class="sr-only">
                        </label>
                        <span wire:loading wire:target="autorisation" class="text-sm text-gris-texte">Envoi…</span>
                    </div>
                    @error('autorisation') <p class="rfl-erreur">{{ $message }}</p> @enderror
                </div>
            @endif

            <fieldset class="mt-6 border-t border-bordure pt-6">
                <legend class="font-display text-vert">Droit à l'image</legend>
                <p class="mt-1 text-sm text-vert-sombre">
                    Pendant l'événement, des photos et des vidéos sont prises pour raconter la journée. Acceptes-tu d'y apparaître ?
                    Ta réponse est indépendante de la photo du badge.
                    @if ($this->texteDroitImage)
                        <a href="{{ route('textes.show', 'droit_image') }}" target="_blank" rel="noopener" class="rfl-lien">Lire l'autorisation (PDF)</a>
                    @endif
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <label class="{{ $choix }} border-bordure py-2"><input type="radio" wire:model="droitImage" value="oui" class="accent-vert"><span class="text-vert-sombre">Oui, j'accepte</span></label>
                    <label class="{{ $choix }} border-bordure py-2"><input type="radio" wire:model="droitImage" value="non" class="accent-vert"><span class="text-vert-sombre">Non, je refuse</span></label>
                </div>
                @error('droitImage') <p class="rfl-erreur">{{ $message }}</p> @enderror
            </fieldset>

            <div class="mt-6 border-t border-bordure pt-6">
                <p class="font-display text-vert">Récapitulatif</p>
                <dl class="mt-3 space-y-3 text-sm">
                    <div><dt class="text-gris-texte">Missions préférées</dt><dd class="text-vert-sombre">{{ collect($c->missions_preferees)->map(fn ($m, $i) => ($i + 1).'. '.Benevolat::mission($m))->join(' · ') }}</dd></div>
                    @if ($c->missions_impossibles)
                        <div><dt class="text-gris-texte">Impossibles</dt><dd class="text-vert-sombre">{{ collect($c->missions_impossibles)->map(fn ($m) => Benevolat::mission($m))->join(', ') }}</dd></div>
                    @endif
                    <div><dt class="text-gris-texte">Jour J</dt><dd class="text-vert-sombre">{{ ($c->disponibilites['toute_journee'] ?? false) ? 'Toute la journée' : collect($c->disponibilites['tranches'] ?? [])->map(fn ($t) => Benevolat::TRANCHES[$t] ?? $t)->join(', ') }}</dd></div>
                    @foreach (Benevolat::PERIODES as $cle => $p)
                        @php($per = $c->disponibilites['periodes'][$cle] ?? null)
                        <div><dt class="text-gris-texte">{{ $p['libelle'] }}</dt><dd class="text-vert-sombre">{{ Benevolat::ETATS_PERIODE[$per['etat'] ?? ''] ?? '–' }}{{ ($per['debut'] ?? null) ? ', de '.$per['debut'] : '' }}{{ ($per['fin'] ?? null) ? ' à '.$per['fin'] : '' }}</dd></div>
                    @endforeach
                    <div><dt class="text-gris-texte">Tee-shirt</dt><dd class="text-vert-sombre">{{ $c->taille_tshirt }}</dd></div>
                    <div><dt class="text-gris-texte">Contact d'urgence</dt><dd class="text-vert-sombre">{{ $c->urgence_nom }}, {{ \App\Support\Telephone::lisible($c->urgence_tel) }}</dd></div>
                </dl>
            </div>

            <div class="mt-8 flex items-center justify-between">
                <button type="button" wire:click="retour" class="rfl-lien">Retour</button>
                <button type="submit" class="rfl-btn" wire:loading.attr="disabled" wire:target="envoyer,photo,autorisation">Envoyer ma candidature</button>
            </div>
        </form>
    @endif

    </div>
</div>
