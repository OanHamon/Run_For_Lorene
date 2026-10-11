@php
    $peutModifier = auth()->user()->peutAdmin(3);
    $nouvelle = ! $option->exists;
    $vendues ??= collect();
@endphp
<x-admin-shell :title="$nouvelle ? 'Nouvelle option' : $option->libelle">
    <p class="mb-2 text-sm">
        <a href="{{ route('admin.evenements.show', [$evenement, 'onglet' => 'options']) }}" class="rfl-lien">{{ $evenement->nom }}</a>
    </p>
    <x-admin.titre :titre="$nouvelle ? 'Nouvelle option' : $option->libelle" sous-titre="Une option (le t-shirt) se décline en tailles, chacune avec son stock.">
        @if (! $nouvelle && $peutModifier && $vendues->isEmpty())
            <form method="POST" action="{{ route('admin.options.destroy', $option) }}" onsubmit="return confirm('Supprimer cette option et ses tailles ?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rfl-btn-secondaire px-4 py-2 text-sm">Supprimer l'option</button>
            </form>
        @endif
    </x-admin.titre>

    <div class="grid gap-6 xl:grid-cols-5">
        <form method="POST" action="{{ $nouvelle ? route('admin.options.store', $evenement) : route('admin.options.update', $option) }}" class="rfl-carte self-start p-6 xl:col-span-2">
            @csrf
            @unless ($nouvelle) @method('PUT') @endunless
            <fieldset @disabled(! $peutModifier) class="space-y-4">
                <legend class="mb-4 font-display text-vert">Option</legend>
                <x-admin.champ name="libelle" label="Libellé" :value="$option->libelle" required maxlength="100" />
                <x-admin.champ name="prix" label="Prix (€)" type="number" step="0.01" min="0" :value="$option->prix" required aide="0 : offert avec l'inscription." />
            </fieldset>
            @if ($peutModifier)
                <button type="submit" class="mt-6 rfl-btn w-full px-6 py-3">{{ $nouvelle ? "Créer l'option" : 'Enregistrer' }}</button>
            @endif
        </form>

        @unless ($nouvelle)
            <div class="rfl-carte overflow-hidden xl:col-span-3">
                <h2 class="px-6 py-4 font-display text-vert">Tailles</h2>
                <p class="-mt-2 px-6 pb-4 text-xs text-gris-texte">Stock vide : illimité. Stock 0 : épuisée, grisée dans le tunnel.</p>
                <table class="rfl-tableau">
                    <thead><tr><th>Taille</th><th>Stock</th><th class="text-right">Vendues</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($option->declinaisons as $d)
                            @php($sac = "declinaison{$d->id_declinaison}")
                            <tr>
                                <td colspan="2">
                                    <form id="taille-{{ $d->id_declinaison }}" method="POST" action="{{ route('admin.declinaisons.update', $d) }}" class="grid grid-cols-2 gap-3">
                                        @csrf
                                        @method('PUT')
                                        <fieldset @disabled(! $peutModifier) class="contents">
                                            <x-admin.champ name="libelle" :label="'Taille'" :value="$d->libelle" :sac="$sac" required maxlength="50" class="[&_label]:sr-only" />
                                            <x-admin.champ name="stock" :label="'Stock'" type="number" min="0" :value="$d->stock" :sac="$sac" class="[&_label]:sr-only" />
                                        </fieldset>
                                    </form>
                                </td>
                                <td class="text-right font-bold">{{ (int) ($vendues[$d->id_declinaison] ?? 0) }}</td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($peutModifier)
                                        <button type="submit" form="taille-{{ $d->id_declinaison }}" class="rfl-lien text-sm">Enregistrer</button>
                                        @unless (isset($vendues[$d->id_declinaison]))
                                            <form method="POST" action="{{ route('admin.declinaisons.destroy', $d) }}" class="ml-3 inline" onsubmit="return confirm('Supprimer la taille {{ $d->libelle }} ?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-gris-texte underline">Supprimer</button>
                                            </form>
                                        @endunless
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($peutModifier)
                    <form method="POST" action="{{ route('admin.declinaisons.store', $option) }}" class="flex flex-wrap items-end gap-3 border-t border-bordure px-6 py-4">
                        @csrf
                        <x-admin.champ name="libelle" label="Nouvelle taille" sac="declinaison" required maxlength="50" class="w-32" />
                        <x-admin.champ name="stock" label="Stock" type="number" min="0" sac="declinaison" class="w-28" />
                        <button type="submit" class="rfl-btn px-5 py-2.5 text-sm">Ajouter</button>
                    </form>
                @endif
            </div>
        @endunless
    </div>
</x-admin-shell>
