@php use App\Support\Format; @endphp
<x-admin-shell title="Remboursements">
    <x-admin.titre titre="Remboursements" :sous-titre="$evenement?->nom" />

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">Aucun événement pour l'instant.</div>
    @else
        <p class="mb-4 text-sm text-gris-texte">Un remboursement se fait depuis la fiche de la commande (super admin). Ceux faits directement dans Stripe apparaissent aussi, sans auteur.</p>
        <div class="rfl-carte overflow-hidden">
            <div class="overflow-x-auto">
                <table class="rfl-tableau min-w-[640px]">
                    <thead><tr><th>Date</th><th>Commande</th><th>Payeur</th><th class="text-right">Montant</th><th>Motif</th><th>Par</th></tr></thead>
                    <tbody>
                        @forelse ($remboursements as $r)
                            <tr class="relative hover:bg-fond/60">
                                <td>{{ $r->date_remboursement?->format('d/m/Y') }}</td>
                                <td class="font-bold"><a href="{{ route('admin.commandes.show', $r->paiement->commande) }}" class="after:absolute after:inset-0">n° {{ $r->paiement->id_commande }}</a></td>
                                <td>{{ $r->paiement->commande->user?->name }}</td>
                                <td class="text-right">{{ Format::euros($r->montant) }}</td>
                                <td class="text-sm">{{ $r->motif ?: '–' }}</td>
                                <td class="text-sm text-gris-texte">{{ $r->admin?->name ?? 'Stripe' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-gris-texte">Aucun remboursement.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">{{ $remboursements->links() }}</div>
    @endif
</x-admin-shell>
