@php
    use App\Models\QuestionSante;
    use App\Support\Telephone;

    $document = $questionnaire->document;
    $participant = $inscription->participant;
    $nbOui = $questionnaire->reponses->where('reponse', 'oui')->count();
@endphp
<x-admin-shell title="Questionnaire de santé">
    <p class="mb-2 text-sm">
        <a href="{{ route('admin.questionnaires.index') }}" class="rfl-lien">Questionnaires de santé</a>
        · <a href="{{ route('admin.inscriptions.show', $inscription) }}" class="rfl-lien">Inscription de {{ $participant->user->prenom }}</a>
    </p>
    <x-admin.titre :titre="'Questionnaire de santé · '.$participant->user->name"
        :sous-titre="$participant->ageAu($inscription->evenement->date_evenement).' ans le jour J · '.$inscription->tarif->course->nom.' · rempli le '.$document->date_depot->format('d/m/Y à H:i').' par '.$document->deposant->name">
        @if ($document->date_validation)
            <span class="rfl-badge bg-vert-clair px-3 py-1 text-sm text-vert">Vérifié le {{ $document->date_validation->format('d/m/Y') }} par {{ $document->validateur?->name }}</span>
        @else
            <form method="POST" action="{{ route('admin.questionnaires.verifier', $document) }}">
                @csrf
                <button type="submit" class="rfl-btn px-5 py-2.5 text-sm">Marquer comme vérifié</button>
            </form>
        @endif
    </x-admin.titre>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="{{ $nbOui > 0 ? 'rfl-alerte' : 'rfl-alerte-info' }}">
                <p class="font-display text-vert">{{ $nbOui > 0 ? "{$nbOui} réponse(s) « oui »" : 'Aucune réponse « oui »' }}</p>
                <p class="mt-1 text-sm text-gris-texte">
                    {{ $nbOui > 0 ? 'La famille a été invitée à consulter un médecin. Le questionnaire ne bloque pas l\'inscription.' : 'Rien à signaler.' }}
                    Attestation finale : {{ $questionnaire->attestation ? 'cochée' : 'non cochée' }}.
                </p>
            </div>

            @foreach ($reponses as $rubrique => $lignes)
                <section class="rfl-carte overflow-hidden">
                    <h2 class="px-6 py-4 font-display text-vert">
                        {{ $rubrique === 'parents' && $mineur ? QuestionSante::RUBRIQUE_PARENTS_MINEUR : (QuestionSante::RUBRIQUES[$rubrique] ?? 'Questions') }}
                    </h2>
                    <table class="rfl-tableau">
                        <tbody class="border-t border-bordure">
                            @foreach ($lignes as $r)
                                <tr @class(['bg-moutarde/10' => $r->reponse === 'oui'])>
                                    <td>{{ $r->question->libelle }}</td>
                                    <td class="w-20 text-right font-bold {{ $r->reponse === 'oui' ? 'text-vert-sombre' : 'text-gris-texte' }}">{{ $r->reponse === 'oui' ? 'Oui' : 'Non' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach
            <p class="text-xs text-gris-texte">Questionnaire version {{ $questionnaire->reponses->first()?->question->version_questionnaire }}. Réponses chiffrées en base, conservées jusqu'à l'édition suivante.</p>
        </div>

        <aside class="rfl-carte self-start p-6">
            <h2 class="mb-3 font-display text-vert">Contacter la famille</h2>
            @if ($titulaire)
                <p class="font-bold">{{ $titulaire->name }}</p>
                @if ($titulaire->email)
                    <p class="mt-1 text-sm"><a href="mailto:{{ $titulaire->email }}" class="rfl-lien">{{ $titulaire->email }}</a></p>
                @endif
                @if ($titulaire->telephone)
                    <p class="mt-1 text-sm"><a href="tel:{{ $titulaire->telephone }}" class="rfl-lien">{{ Telephone::lisible($titulaire->telephone) }}</a></p>
                @endif
            @endif
            @if ($inscription->urgence_nom || $inscription->urgence_tel)
                <p class="mt-4 text-sm text-gris-texte">Contact d'urgence : {{ $inscription->urgence_nom }} {{ Telephone::lisible($inscription->urgence_tel) }}</p>
            @endif
        </aside>
    </div>
</x-admin-shell>
