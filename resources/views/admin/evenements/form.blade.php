<x-admin-shell title="Nouvel événement">
    <p class="mb-2 text-sm"><a href="{{ route('admin.evenements.index') }}" class="rfl-lien">Événements</a></p>
    <x-admin.titre titre="Nouvel événement" sous-titre="Les courses, options et textes légaux s'ajoutent ensuite." />

    <form method="POST" action="{{ route('admin.evenements.store') }}" class="rfl-carte max-w-3xl p-6">
        @csrf
        @include('admin.evenements._champs')
        <div class="mt-6 flex justify-end">
            <button type="submit" class="rfl-btn px-6 py-3">Créer l'événement</button>
        </div>
    </form>
</x-admin-shell>
