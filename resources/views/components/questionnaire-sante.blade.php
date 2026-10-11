@props(['questions', 'mineur' => false, 'pour' => null, 'modele' => 'reponsesSante', 'attestation' => 'attestationSante'])

{{--
    Questionnaire de santé en diapos (rubriques du Google Form). Les réponses restent liées à
    $modele.{id} et l'attestation à $attestation ; la validation se fait côté serveur à l'envoi.
    « Suivant » vérifie seulement que la diapo est complète. Une erreur serveur ramène à sa diapo.
    Slot « actions » : boutons affichés sur la dernière diapo (envoi depuis l'espace).
--}}
@php
    $parRubrique = $questions->toBase()->groupBy(fn ($q) => $q->rubrique ?? '');
    $rubriquesConnues = $parRubrique->keys()->diff(array_keys(\App\Models\QuestionSante::RUBRIQUES))->isEmpty();

    // Questions sans rubrique connue (autre version) : une seule diapo, sans titres.
    $diapos = $rubriquesConnues
        ? collect(\App\Models\QuestionSante::DIAPOS)
            ->map(fn ($rubriques) => array_values(array_filter($rubriques, fn ($r) => $parRubrique->has($r))))
            ->filter()
            ->values()
        : collect([['']]);

    $numeros = $questions->values()->mapWithKeys(fn ($q, $i) => [$q->id_question => $i + 1]);
    $titre = fn (string $rubrique) => match (true) {
        $rubrique === 'parents' && $mineur && $pour => "Questions à remplir par les parents de {$pour}",
        $rubrique === 'parents' && $mineur => \App\Models\QuestionSante::RUBRIQUE_PARENTS_MINEUR,
        default => \App\Models\QuestionSante::RUBRIQUES[$rubrique] ?? null,
    };

    // Première diapo portant une erreur de validation, pour y revenir après l'envoi.
    $diapoErreur = null;
    foreach ($diapos as $index => $rubriques) {
        $cles = $parRubrique->only($rubriques)->flatten()->map(fn ($q) => "{$modele}.{$q->id_question}");
        if ($index === $diapos->count() - 1) {
            $cles->push($attestation);
        }
        if ($errors->hasAny($cles->all())) {
            $diapoErreur = $index;
            break;
        }
    }
    $total = $diapos->count();
@endphp

<div x-data="{
        diapo: 0,
        manque: false,
        complete() {
            return [...this.$refs['diapo' + this.diapo].querySelectorAll('[role=radiogroup]')]
                .every(groupe => groupe.querySelector('input:checked'));
        },
        aller(index) {
            this.manque = false;
            this.diapo = index;
            this.$nextTick(() => this.$refs.haut.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
        },
        suivant() {
            if (! this.complete()) { this.manque = true; return; }
            this.aller(this.diapo + 1);
        },
    }" x-ref="haut" class="scroll-mt-6">
    @if ($diapoErreur !== null)
        <span wire:key="sante-saut-{{ uniqid() }}" x-init="aller({{ $diapoErreur }})" hidden></span>
    @endif

    @if ($total > 1)
        <div class="mt-3 flex items-center gap-3" aria-live="polite">
            <p class="shrink-0 text-xs font-bold text-gris-texte">Partie <span x-text="diapo + 1">1</span> sur {{ $total }}</p>
            <div class="flex flex-1 gap-1.5" aria-hidden="true">
                @for ($i = 0; $i < $total; $i++)
                    <span class="h-1.5 flex-1 rounded-full transition-colors" :class="diapo >= {{ $i }} ? 'bg-vert' : 'bg-bordure'"></span>
                @endfor
            </div>
        </div>
    @endif

    @foreach ($diapos as $index => $rubriques)
        <div x-ref="diapo{{ $index }}" x-show="diapo === {{ $index }}" @if ($index > 0) x-cloak @endif wire:key="sante-diapo-{{ $index }}">
            @foreach ($rubriques as $rubrique)
                @if ($titre($rubrique))
                    <h4 class="mt-4 font-display text-sm text-vert">{{ $titre($rubrique) }}</h4>
                @endif
                @if ($rubrique === 'parents' && $mineur && ! $pour)
                    <p class="mt-1 text-sm text-gris-texte">Demande à tes parents de répondre à ces questions.</p>
                @endif
                <div class="mt-1 divide-y divide-bordure">
                    @foreach ($parRubrique->get($rubrique, collect()) as $q)
                        <x-question-oui-non :question="$q" :numero="$numeros[$q->id_question]" :modele="$modele" />
                    @endforeach
                </div>
            @endforeach

            @if ($loop->last)
                <div class="mt-4 rounded-2xl border-2 border-moutarde bg-moutarde/15 px-4 py-3 text-sm text-vert-sombre" role="note">
                    @if ($pour)
                        Si tu as répondu OUI à une ou plusieurs questions, {{ $pour }} doit consulter un médecin pour qu'il l'examine et voie si {{ $pour }} peut faire le Run For Lorène. Au moment de la visite, donne-lui ce questionnaire rempli.
                    @else
                        Si tu as répondu OUI à une ou plusieurs questions, tu dois consulter un médecin pour qu'il t'examine et voie avec toi si tu peux faire le Run For Lorène. Au moment de la visite, donne-lui ce questionnaire rempli.
                    @endif
                </div>

                <div class="mt-4 border-t border-bordure pt-4">
                    <label class="flex items-start gap-3 text-sm text-vert-sombre">
                        <input wire:model="{{ $attestation }}" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-bordure text-vert focus:ring-vert">
                        <span>{{ $pour ? "J'ai lu et j'approuve l'ensemble des réponses données pour {$pour} à ce questionnaire." : "J'ai lu et j'approuve l'ensemble de mes réponses à ce questionnaire." }}</span>
                    </label>
                    @error($attestation) <p class="rfl-erreur">{{ $message }}</p> @enderror
                </div>
            @endif

            <p x-show="manque" x-cloak class="rfl-erreur" role="alert">Réponds à toutes les questions de cette partie pour continuer.</p>

            @if ($total > 1 || isset($actions))
                <div class="mt-4 flex items-center justify-between gap-3 border-t border-bordure pt-4">
                    @if ($index > 0)
                        <button type="button" x-on:click="aller({{ $index - 1 }})" class="rfl-lien text-sm">Partie précédente</button>
                    @else
                        <span></span>
                    @endif

                    @if (! $loop->last)
                        <button type="button" x-on:click="suivant()" class="rfl-btn-secondaire px-5 py-2.5 text-sm">Partie suivante</button>
                    @else
                        {{ $actions ?? '' }}
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</div>
