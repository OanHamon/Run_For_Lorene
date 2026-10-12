@php
    use App\Http\Controllers\Admin\CommandeController;
    use App\Models\Paiement;
    use App\Support\Format;

    $peutRembourser = auth()->user()->peutAdmin(3);
    $statutsPaiement = [Paiement::EN_ATTENTE => 'En attente', Paiement::REUSSI => 'Réussi', Paiement::ECHOUE => 'Échoué ou abandonné'];
    $lienStripe = fn (Paiement $p) => $p->prestataire === 'stripe'
        ? 'https://dashboard.stripe.com/'.($stripeTest ? 'test/' : '').'search?query='.urlencode($p->reference)
        : null;
@endphp
<x-admin-shell :title="'Commande n° '.$commande->id_commande">
    <p class="mb-2 text-sm"><a href="{{ route('admin.commandes.index') }}" class="rfl-lien">Commandes et paiements</a></p>
    <x-admin.titre :titre="'Commande n° '.$commande->id_commande"
        :sous-titre="'Passée le '.$commande->date_commande->format('d/m/Y à H:i').' par '.$commande->user->name.' ('.$commande->user->email.')'">
        <span class="rfl-badge bg-vert-clair px-3 py-1 text-sm text-vert">{{ CommandeController::LIBELLES[$commande->statut] ?? $commande->statut }}</span>
    </x-admin.titre>

    @error('montant')
        <p class="mb-6 rfl-alerte-erreur" role="alert">{{ $message }}</p>
    @enderror

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rfl-carte overflow-hidden">
            <h2 class="px-6 py-4 font-display text-vert">Contenu</h2>
            <table class="rfl-tableau">
                <tbody class="border-t border-bordure">
                    @foreach ($commande->inscriptions as $i)
                        <tr>
                            <td>
                                <a href="{{ route('admin.inscriptions.show', $i) }}" class="rfl-lien">{{ $i->participant->user->name }}</a>
                                <span class="block text-xs text-gris-texte">{{ $i->tarif->course->nom }} · {{ $i->libelleStatut() }}</span>
                            </td>
                            <td class="text-right">{{ Format::euros($i->prix_paye) }}</td>
                        </tr>
                        @foreach ($i->declinaisons as $d)
                            <tr>
                                <td class="pl-10 text-gris-texte">{{ $d->declinaison->optionEvenement->libelle }} {{ $d->declinaison->libelle }} × {{ $d->quantite }}</td>
                                <td class="text-right text-gris-texte">{{ Format::euros($d->prix_unitaire * $d->quantite) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    @if ($commande->don)
                        <tr>
                            <td>Don {{ $commande->don->anonyme ? '(anonyme)' : '' }}{{ $commande->don->recu_fiscal_demande ? ' · reçu fiscal demandé' : '' }}</td>
                            <td class="text-right">{{ Format::euros($commande->don->montant) }}</td>
                        </tr>
                    @endif
                    <tr class="font-bold">
                        <td>Total (figé à la commande)</td>
                        <td class="text-right">{{ Format::euros($commande->montant_total) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="space-y-4">
            @forelse ($commande->paiements->sortByDesc('id_paiement') as $p)
                @php($remboursable = $p->remboursable())
                <div class="rfl-carte p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-vert">Paiement du {{ $p->created_at->format('d/m/Y H:i') }}</h2>
                            <p class="mt-1 text-sm text-gris-texte">
                                {{ $statutsPaiement[$p->statut] ?? $p->statut }} · {{ Format::euros($p->montant) }}
                                @if ($p->frais !== null) · frais {{ Format::euros($p->frais) }} @endif
                                · {{ $p->prestataire }}
                            </p>
                            <p class="mt-1 break-all text-xs text-gris-texte">{{ $p->reference }}</p>
                        </div>
                        @if ($lien = $lienStripe($p))
                            <a href="{{ $lien }}" target="_blank" rel="noopener" class="rfl-lien text-sm">Voir dans Stripe</a>
                        @endif
                    </div>

                    @if ($p->remboursements->isNotEmpty())
                        <ul class="mt-4 space-y-1 border-t border-bordure pt-3 text-sm">
                            @foreach ($p->remboursements as $r)
                                <li>
                                    Remboursé <strong>{{ Format::euros($r->montant) }}</strong> le {{ $r->date_remboursement->format('d/m/Y') }}
                                    <span class="text-gris-texte">{{ $r->admin ? 'par '.$r->admin->name : 'depuis Stripe' }}{{ $r->motif ? ' · '.$r->motif : '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($peutRembourser && $remboursable > 0)
                        <details class="mt-4 border-t border-bordure pt-3">
                            <summary class="cursor-pointer rfl-lien text-sm">Rembourser (jusqu'à {{ Format::euros($remboursable) }})</summary>
                            <form method="POST" action="{{ route('admin.paiements.rembourser', $p) }}" class="mt-4 grid gap-3 sm:grid-cols-[8rem_1fr_auto] sm:items-end"
                                onsubmit="return confirm('Rembourser ce montant ? Le remboursement part immédiatement vers la carte du payeur.')">
                                @csrf
                                <div>
                                    <label for="montant-{{ $p->id_paiement }}" class="rfl-label">Montant (€)</label>
                                    <input id="montant-{{ $p->id_paiement }}" name="montant" type="number" step="0.01" min="0.01" max="{{ $remboursable }}" value="{{ number_format($remboursable, 2, '.', '') }}" required class="rfl-champ-compact">
                                </div>
                                <div>
                                    <label for="motif-{{ $p->id_paiement }}" class="rfl-label">Motif</label>
                                    <input id="motif-{{ $p->id_paiement }}" name="motif" maxlength="255" class="rfl-champ-compact" placeholder="Annulation, erreur de course…">
                                </div>
                                <button type="submit" class="rfl-btn px-5 py-2.5 text-sm">Rembourser</button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <div class="rfl-carte p-6 text-sm text-gris-texte">Aucune tentative de paiement{{ $commande->statut === 'gratuite' ? ' : commande gratuite.' : '.' }}</div>
            @endforelse
        </section>
    </div>
</x-admin-shell>
