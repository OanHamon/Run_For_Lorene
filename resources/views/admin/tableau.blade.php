@php
    use App\Models\Inscription;
    use App\Support\Format;

    $lienInscriptions = fn (array $filtres = []) => Route::has('admin.inscriptions.index') ? route('admin.inscriptions.index', $filtres) : null;
    $alertesDossards = $courses->filter(fn ($c) => $c->dossards['sans_dossard'] > 0 || ($c->dossards['libres'] !== null && $c->dossards['libres'] === 0));
@endphp
<x-admin-shell title="Tableau de bord">
    <x-admin.titre titre="Tableau de bord" :sous-titre="$evenement ? $evenement->nom.' · '.$evenement->date_evenement->translatedFormat('j F Y') : null">
        @if ($evenement)<x-admin.filtre-evenement :evenement="$evenement" :evenements="$evenements" />@endif
    </x-admin.titre>

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">
            Aucun événement pour l'instant.
            @if (Route::has('admin.evenements.create') && auth()->user()->peutAdmin(3))
                <a href="{{ route('admin.evenements.create') }}" class="rfl-lien">Créer le premier événement</a>
            @endif
        </div>
    @else
        @foreach ($alertesDossards as $c)
            <div class="mb-4 rfl-alerte" role="alert">
                <p class="font-display text-vert">Dossards : {{ $c->course->nom }}</p>
                <p class="mt-1 text-sm text-gris-texte">
                    @if ($c->dossards['plage'] === null)
                        Aucune plage de dossards définie :
                    @else
                        Plage {{ $c->course->dossard_debut }}–{{ $c->course->dossard_fin }} pleine.
                    @endif
                    @if ($c->dossards['sans_dossard'] > 0)
                        {{ $c->dossards['sans_dossard'] }} inscription(s) validée(s) sans dossard. Élargis la plage, puis attribue les numéros depuis la fiche de chaque inscription.
                    @else
                        Les prochaines inscriptions validées n'auront pas de dossard. Élargis la plage dans les réglages de la course.
                    @endif
                </p>
            </div>
        @endforeach

        {{-- L'essentiel : inscrits par statut et recettes encaissées --}}
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                [Inscription::VALIDEE, 'Validées', 'inscriptions avec dossard'],
                [Inscription::DOSSIER_INCOMPLET, 'Dossiers incomplets', 'payées, documents manquants'],
                [Inscription::EN_ATTENTE_PAIEMENT, 'En attente de paiement', 'commandes non réglées'],
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
