@props(['questions', 'evenement', 'signataire', 'modele' => 'reponsesDecharge', 'approbation' => 'accepteDecharge'])

{{--
    Décharge de responsabilité, reprise du Google Form « Décharge Responsabilité Adulte » : deux
    blocs d'affirmations à cocher (toutes obligatoires), la signature (nom du signataire et date du
    jour), puis l'approbation finale, qui vaut acceptation de la décharge.
    Les affirmations sont liées à $modele.{id_question}, l'approbation à $approbation.
--}}
@php
    // Bloc de chaque affirmation, dans l'ordre des questions : les 3 premières suivent l'engagement.
    $blocs = [
        [
            'intro' => "Je prends connaissance des dispositions ci-dessus, et ne présentant pas de certificat médical de « non contre-indication à la pratique de l'athlétisme ou de la course à pied en compétition » datant de moins d'un an ou la photocopie d'une licence sportive en cours de validité, je m'engage sous ma propre responsabilité à participer à la « RUN FOR LORÈNE » et atteste ne présenter aucune contre-indication à la pratique du sport, renonçant par là-même à tout recours envers les organisateurs, l'association Effervescence Jeunes.",
            'titre' => 'Je certifie que :',
            'questions' => $questions->take(3),
        ],
        [
            'intro' => null,
            'titre' => 'Je coche les affirmations suivantes :',
            'questions' => $questions->slice(3),
        ],
    ];
@endphp
<div {{ $attributes }}>
    <div class="space-y-2 text-sm text-vert-sombre">
        <p>La présente décharge de responsabilité est établie dans le cadre de la <strong>« RUN FOR LORÈNE »</strong>, une manifestation sportive (course pédestre ou marche) non chronométrée, organisée le <strong>{{ $evenement->date_evenement->translatedFormat('l j F Y') }}</strong> à Nantes.</p>
        <p><strong>Important :</strong> conformément au règlement de l'événement, <strong>tout dossier incomplet entraîne l'impossibilité de participer</strong>.</p>
    </div>

    @foreach ($blocs as $index => $bloc)
        @continue($bloc['questions']->isEmpty())
        <fieldset class="mt-4 border-t border-bordure pt-4">
            @if ($bloc['intro'])
                <p class="font-bold text-vert-sombre">Engagement du participant</p>
                <p class="mt-1 text-sm text-gris-texte">{{ $bloc['intro'] }}</p>
            @endif
            <legend class="sr-only">{{ $bloc['titre'] }}</legend>
            <p class="mt-2 text-sm font-bold text-vert-sombre" aria-hidden="true">{{ $bloc['titre'] }}</p>
            <div class="mt-2 space-y-3">
                @foreach ($bloc['questions'] as $q)
                    @php($cle = "{$modele}.{$q->id_question}")
                    <div wire:key="{{ $cle }}">
                        <label class="flex items-start gap-3 text-sm text-vert-sombre">
                            <input wire:model="{{ $cle }}" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-bordure text-vert focus:ring-vert">
                            <span>{{ $q->libelle }}</span>
                        </label>
                        @error($cle) <p class="rfl-erreur">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </fieldset>
    @endforeach

    <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-1 border-t border-bordure pt-4 text-sm sm:grid-cols-2">
        <div><dt class="inline text-gris-texte">Nom - Prénom :</dt> <dd class="inline font-bold text-vert-sombre">{{ $signataire }}</dd></div>
        <div><dt class="inline text-gris-texte">Fait le :</dt> <dd class="inline font-bold text-vert-sombre">{{ today()->translatedFormat('j F Y') }}</dd></div>
    </dl>

    <div class="mt-4 border-t border-bordure pt-4">
        <label class="flex items-start gap-3 text-sm text-vert-sombre">
            <input wire:model="{{ $approbation }}" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-bordure text-vert focus:ring-vert">
            <span>J'ai lu et j'approuve l'ensemble de mes réponses à ce questionnaire.</span>
        </label>
        @error($approbation) <p class="rfl-erreur">{{ $message }}</p> @enderror
    </div>
</div>
