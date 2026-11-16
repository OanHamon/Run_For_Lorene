<?php

use App\Models\CandidatureBenevole;
use App\Services\Benevoles\FichiersCandidature;
use App\Services\Inscriptions\Consentements;
use App\Support\Benevolat;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/*
 * Espace : ma candidature bénévole. Lecture seule une fois envoyée (décision du 16/11/2026) ;
 * la photo du badge et l'autorisation parentale se déposent ou se remplacent tant que la
 * candidature n'est pas close (non retenue, désistement).
 */
new #[Layout('components.espace-shell', ['title' => 'Bénévolat'])] class extends Component {
    use WithFileUploads;

    public $photo = null;

    public $autorisation = null;

    public function mount(): void
    {
        if (! $this->candidature) {
            $this->redirectRoute('benevoles');
        }
    }

    #[Computed]
    public function candidature(): ?CandidatureBenevole
    {
        return CandidatureBenevole::duCompte(Auth::user())?->load('evenement', 'course', 'historique');
    }

    #[Computed]
    public function texteAutorisation()
    {
        return $this->candidature ? app(Consentements::class)->textes($this->candidature->evenement)->get('autorisation_benevole') : null;
    }

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
        abort_unless($candidature && ! $candidature->close() && ($type === 'photo' || $candidature->autorisationRequise()), 403);

        app(FichiersCandidature::class)->deposer($candidature, $type, $this->{$type});
        $this->reset($type);
        unset($this->candidature);
        session()->flash('statut', $type === 'photo' ? 'Ta photo est enregistrée.' : 'L\'autorisation parentale est enregistrée.');
    }
}; ?>

@php
    $c = $this->candidature;
    $badges = [
        CandidatureBenevole::CONFIRMEE => 'bg-vert-clair text-vert',
        CandidatureBenevole::INFOS_DEMANDEES => 'bg-moutarde/20 text-vert-sombre',
        CandidatureBenevole::NON_RETENUE => 'bg-bordure text-gris-texte',
        CandidatureBenevole::DESISTEMENT => 'bg-bordure text-gris-texte',
    ];
@endphp

<div class="max-w-3xl">
    @if ($c)
        <h1 class="font-display text-3xl text-vert">Ma candidature bénévole</h1>
        <p class="mt-1 text-sm text-gris-texte">{{ $c->evenement->nom }} &middot; {{ $c->evenement->date_evenement->translatedFormat('l j F Y') }}</p>

        @if (session('statut'))
            <p class="mt-6 rfl-alerte-info text-sm text-vert" role="status">{{ session('statut') }}</p>
        @endif

        @if (! $c->envoyee())
            <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-bordure bg-white px-5 py-4">
                <div>
                    <p class="font-display text-vert">Candidature pas encore envoyée</p>
                    <p class="mt-1 text-sm text-gris-texte">Reprends-la là où tu t'étais arrêté(e).</p>
                </div>
                @if (Benevolat::ouvert())
                    <a href="{{ route('benevoles.candidature') }}" class="rfl-btn px-5 py-2.5 text-sm">Reprendre</a>
                @else
                    <p class="text-sm text-gris-texte">Les candidatures sont fermées.</p>
                @endif
            </div>
        @else
            <section class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-bordure">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-display text-xl text-vert">Où en est ma candidature</h2>
                    <span class="rounded-full px-3 py-1 text-sm font-bold {{ $badges[$c->statut] ?? 'bg-fond text-vert-sombre' }}">{{ $c->libelleStatut() }}</span>
                </div>
                <p class="mt-3 text-vert-sombre">
                    @switch($c->statut)
                        @case(CandidatureBenevole::CONFIRMEE)
                            Ta participation est confirmée. Merci !
                            @if ($c->mission_affectee) Ta mission : <strong>{{ Benevolat::mission($c->mission_affectee) }}</strong>. @endif
                            @break
                        @case(CandidatureBenevole::INFOS_DEMANDEES)
                            L'organisation t'a écrit pour te demander des précisions : regarde tes emails.
                            @break
                        @case(CandidatureBenevole::NON_RETENUE)
                            Merci pour ta proposition d'aide. Ta candidature n'a pas été retenue cette fois.
                            @break
                        @case(CandidatureBenevole::DESISTEMENT)
                            Ton désistement est bien noté.
                            @break
                        @default
                            Envoyée le {{ $c->envoyee_le->translatedFormat('j F Y') }}. L'organisation reviendra vers toi pour confirmer ou non ta participation, selon les besoins et tes disponibilités.
                    @endswitch
                </p>
                <p class="mt-3 text-sm text-gris-texte">Une question, un changement ? Écris à <a href="mailto:{{ Benevolat::CONTACT }}" class="rfl-lien">{{ Benevolat::CONTACT }}</a>.</p>
            </section>
        @endif

        @unless ($c->close())
            <section class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-bordure">
                <h2 class="font-display text-xl text-vert">Ma photo pour le badge</h2>
                <p class="mt-1 text-sm text-gris-texte">Un portrait de toi, de face (pas une pièce d'identité). JPG, PNG ou WebP, 5 Mo maximum.</p>
                <div class="mt-4 flex flex-wrap items-center gap-4">
                    @if ($c->photo)
                        <img src="{{ route('espace.benevole.fichier', 'photo') }}?v={{ $c->photo_le?->timestamp }}" alt="Ta photo de badge" class="h-24 w-24 rounded-2xl object-cover ring-1 ring-bordure">
                    @else
                        <span class="rounded-full bg-moutarde/20 px-3 py-1 text-xs font-bold text-vert-sombre">À déposer</span>
                    @endif
                    <label class="rfl-btn-secondaire cursor-pointer px-5 py-2 text-sm">
                        {{ $c->photo ? 'Changer de photo' : 'Choisir une photo' }}
                        <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                    </label>
                    <span wire:loading wire:target="photo" class="text-sm text-gris-texte">Envoi…</span>
                </div>
                @error('photo') <p class="rfl-erreur">{{ $message }}</p> @enderror
            </section>

            @if ($c->autorisationRequise())
                <section class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-bordure">
                    <h2 class="font-display text-xl text-vert">L'autorisation parentale</h2>
                    <p class="mt-1 text-sm text-gris-texte">Tu as moins de 16 ans : ton responsable légal signe l'autorisation, puis tu la déposes ici (PDF ou photo, 10 Mo maximum).</p>
                    @if ($this->texteAutorisation)
                        <a href="{{ route('textes.show', 'autorisation_benevole') }}" target="_blank" rel="noopener" class="mt-2 inline-block rfl-lien text-sm">Télécharger l'autorisation à signer (PDF)</a>
                    @endif
                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        @if ($c->autorisation_parentale)
                            <a href="{{ route('espace.benevole.fichier', 'autorisation') }}" target="_blank" class="rounded-full bg-vert-clair px-3 py-1 text-xs font-bold text-vert">Déposée le {{ $c->autorisation_le->format('d/m/Y') }}</a>
                        @else
                            <span class="rounded-full bg-moutarde/20 px-3 py-1 text-xs font-bold text-vert-sombre">À déposer</span>
                        @endif
                        <label class="rfl-btn-secondaire cursor-pointer px-5 py-2 text-sm">
                            {{ $c->autorisation_parentale ? 'Remplacer' : 'Déposer l\'autorisation signée' }}
                            <input type="file" wire:model="autorisation" accept="application/pdf,image/jpeg,image/png" class="sr-only">
                        </label>
                        <span wire:loading wire:target="autorisation" class="text-sm text-gris-texte">Envoi…</span>
                    </div>
                    @error('autorisation') <p class="rfl-erreur">{{ $message }}</p> @enderror
                </section>
            @endif
        @endunless

        @if ($c->envoyee())
            <section class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-bordure">
                <h2 class="font-display text-xl text-vert">Ce que tu nous as dit</h2>
                @include('partials.candidature-benevole', ['c' => $c, 'sensible' => true])
            </section>
        @endif
    @endif
</div>
