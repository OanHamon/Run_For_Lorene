@php
    use App\Models\Inscription;
    use App\Support\Format;

    $lienInscriptions = fn (array $filtres = []) => Route::has('admin.inscriptions.index') ? route('admin.inscriptions.index', $filtres) : null;
@endphp
<x-admin-shell title="Tableau de bord">
    <x-admin.titre titre="Tableau de bord" :sous-titre="$evenement ? $evenement->nom.' · '.$evenement->date_evenement->translatedFormat('j F Y') : null">
    </x-admin.titre>

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">
            Aucun événement pour l'instant.
            @if (Route::has('admin.evenements.create') && auth()->user()->peutAdmin(3))
                <a href="{{ route('admin.evenements.create') }}" class="rfl-lien">Créer le premier événement</a>
            @endif
        </div>
    @else
        {{-- Ce qui attend une action : le détail est sur « À traiter ». --}}
        @if ($aTraiter)
            <a href="{{ route('admin.a-traiter') }}" class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-vert p-5 text-white hover:bg-vert/90" data-test="a-traiter">
                <div>
                    <p class="font-display text-lg">À traiter</p>
                    <p class="mt-1 text-sm text-vert-clair">
                        @foreach ($aTraiter as $g){{ $g['titre'] }} ({{ $g['nombre'] }})@if (! $loop->last) · @endif @endforeach
                    </p>
                </div>
                <span class="rounded-full bg-moutarde px-4 py-2 font-bold text-vert-sombre">Voir la liste</span>
            </a>
        @endif

        {{-- L'essentiel : inscrits par statut et recettes encaissées --}}
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
            @foreach ([
                [Inscription::VALIDEE, 'Validées', 'inscriptions avec dossard'],
                [Inscription::DOSSIER_INCOMPLET, 'Dossiers incomplets', 'payées, documents manquants'],
            ] as [$statut, $libelle, $aide])
                @php($lien = $lienInscriptions(['statut' => $statut]))
                <a @if ($lien) href="{{ $lien }}" @endif class="rfl-carte block p-4 {{ $lien ? 'hover:bg-fond/60' : '' }}">
                    <p class="text-sm text-gris-texte">{{ $libelle }}</p>
                    <p class="mt-1 font-display text-2xl text-vert" data-test="inscrits-{{ $statut }}">{{ $inscrits[$statut] }}</p>
                    <p class="text-xs text-gris-texte">{{ $aide }}</p>
                </a>
            @endforeach

            {{-- Le total en moutarde, posé sur le vert (charte) --}}
            <div class="rounded-3xl bg-vert p-4 text-white">
                <p class="text-sm text-vert-clair">Recettes encaissées</p>
                <p class="mt-1 font-display text-2xl text-moutarde" data-test="recettes-total">{{ Format::euros($recettes['total']) }}</p>
                @if ($recettes['dons'] > 0)
                    <p class="text-xs text-vert-clair" data-test="recettes-dons">dont {{ Format::euros($recettes['dons']) }} de dons</p>
                @endif
            </div>
        </div>

        {{-- Remplissage par course --}}
        <div class="rfl-carte overflow-hidden">
            <h2 class="px-6 py-4 font-display text-vert">Inscrits par course</h2>
            <div class="overflow-x-auto">
                <table class="rfl-tableau min-w-[480px]">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th class="text-right">Inscrits</th>
                            <th class="w-1/2">Remplissage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($courses as $c)
                            <tr>
                                <td class="font-bold">
                                    @php($lien = $lienInscriptions(['course' => $c->course->id_course]))
                                    @if ($lien)<a href="{{ $lien }}" class="hover:underline">{{ $c->course->nom }}</a>@else{{ $c->course->nom }}@endif
                                </td>
                                <td class="text-right" data-test="course-{{ $c->course->id_course }}-reglees">{{ $c->reglees }}</td>
                                <td>
                                    @if ($c->quota)
                                        <div class="flex items-center gap-3">
                                            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-fond ring-1 ring-bordure" role="meter" aria-valuemin="0" aria-valuemax="{{ $c->quota }}" aria-valuenow="{{ $c->places_prises }}" aria-label="Remplissage {{ $c->course->nom }}">
                                                <div class="h-full rounded-full {{ $c->remplissage >= 100 ? 'bg-vert' : 'bg-vert-doux' }}" style="width: {{ $c->remplissage }}%"></div>
                                            </div>
                                            <span class="w-20 shrink-0 text-right text-xs text-gris-texte">{{ $c->places_prises }} / {{ $c->quota }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-gris-texte">sans limite de places</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-gris-texte">Aucune course.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-admin-shell>
