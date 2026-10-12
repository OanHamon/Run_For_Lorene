@php
    use App\Models\Inscription;
    use App\Support\Format;

    $lienInscriptions = fn (array $filtres = []) => Route::has('admin.inscriptions.index') ? route('admin.inscriptions.index', $filtres) : null;
    $avecOptions = config('rfl.options');
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

        {{-- Inscrits --}}
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                [Inscription::VALIDEE, 'Validées', 'inscriptions avec dossard'],
                [Inscription::DOSSIER_INCOMPLET, 'Dossiers incomplets', 'payées, documents manquants'],
                [Inscription::EN_ATTENTE_PAIEMENT, 'En attente de paiement', 'commandes non réglées'],
                [Inscription::ANNULEE, 'Annulées', 'par un admin'],
            ] as [$statut, $libelle, $aide])
                @php($lien = $lienInscriptions(['statut' => $statut]))
                <a @if ($lien) href="{{ $lien }}" @endif class="rfl-carte block p-4 {{ $lien ? 'hover:bg-fond/60' : '' }}">
                    <p class="text-sm text-gris-texte">{{ $libelle }}</p>
                    <p class="mt-1 font-display text-2xl text-vert" data-test="inscrits-{{ $statut }}">{{ $inscrits[$statut] }}</p>
                    <p class="text-xs text-gris-texte">{{ $aide }}</p>
                </a>
            @endforeach
        </div>

        {{-- Recettes : le total en moutarde, posé sur le vert (charte) --}}
        <div class="mb-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded-3xl bg-vert p-6 text-white">
                <p class="text-sm text-vert-clair">Recettes encaissées</p>
                <p class="mt-1 font-display text-4xl text-moutarde" data-test="recettes-total">{{ Format::euros($recettes['total']) }}</p>
                <p class="mt-3 text-sm text-vert-clair">
                    @if ($recettes['rembourse'] > 0)Remboursé : {{ Format::euros($recettes['rembourse']) }} · @endif
                    Frais Stripe : {{ Format::euros($recettes['frais']) }}
                </p>
            </div>
            <div class="rfl-carte grid {{ $avecOptions ? 'grid-cols-3' : 'grid-cols-2' }} gap-4 p-6 lg:col-span-2">
                @foreach (array_filter(['inscriptions' => 'Inscriptions', 'options' => $avecOptions ? 'Options' : null, 'dons' => 'Dons']) as $cle => $libelle)
                    <div>
                        <p class="text-sm text-gris-texte">{{ $libelle }}</p>
                        <p class="mt-1 font-display text-xl text-vert" data-test="recettes-{{ $cle }}">{{ Format::euros($recettes[$cle]) }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid gap-6 {{ $avecOptions ? 'xl:grid-cols-3' : '' }}">
            {{-- Remplissage par course --}}
            <div class="rfl-carte overflow-hidden {{ $avecOptions ? 'xl:col-span-2' : '' }}">
                <h2 class="px-6 py-4 font-display text-vert">Inscrits par course</h2>
                <div class="overflow-x-auto">
                    <table class="rfl-tableau min-w-[560px]">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th class="text-right">Réglées</th>
                                <th class="text-right">En attente</th>
                                <th class="w-1/3">Remplissage</th>
                                <th class="text-right">Dossards libres</th>
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
                                    <td class="text-right">{{ $c->en_attente }}</td>
                                    <td>
                                        @if ($c->quota)
                                            <div class="flex items-center gap-3">
                                                <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-fond ring-1 ring-bordure" role="meter" aria-valuemin="0" aria-valuemax="{{ $c->quota }}" aria-valuenow="{{ $c->places_prises }}" aria-label="Remplissage {{ $c->course->nom }}">
                                                    <div class="h-full rounded-full {{ $c->remplissage >= 100 ? 'bg-vert' : 'bg-vert-doux' }}" style="width: {{ $c->remplissage }}%"></div>
                                                </div>
                                                <span class="w-20 shrink-0 text-right text-xs text-gris-texte">{{ $c->places_prises }} / {{ $c->quota }}</span>
                                            </div>
                                        @else
                                            <span class="text-xs text-gris-texte">{{ $c->places_prises }} · sans limite</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $c->dossards['libres'] ?? '–' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-gris-texte">Aucune course.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($avecOptions)
            {{-- T-shirts --}}
            <div class="rfl-carte overflow-hidden">
                <h2 class="px-6 py-4 font-display text-vert">Options vendues</h2>
                <table class="rfl-tableau">
                    <thead>
                        <tr><th>Taille</th><th class="text-right">Vendus</th><th class="text-right">Stock restant</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($tshirts as $t)
                            <tr>
                                <td>{{ $t->option }} {{ $t->taille }}</td>
                                <td class="text-right font-bold" data-test="tshirt-{{ $t->taille }}">{{ $t->vendus }}</td>
                                <td class="text-right {{ $t->restant === 0 ? 'font-bold text-vert' : '' }}">{{ $t->restant === null ? 'illimité' : ($t->restant === 0 ? 'épuisé' : "{$t->restant} / {$t->stock}") }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-gris-texte">Aucune option.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    @endif
</x-admin-shell>
