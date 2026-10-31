{{-- Recherche globale : nom, email, n° de dossard ou de commande. --}}
<form method="GET" action="{{ route('admin.recherche') }}" role="search">
    <label for="{{ $id }}" class="sr-only">Rechercher</label>
    <input id="{{ $id }}" name="q" type="search" value="{{ request()->routeIs('admin.recherche') ? request('q') : '' }}" placeholder="Nom, email, dossard…" class="w-full rounded-2xl border-0 bg-white/10 px-4 py-2 text-sm text-white placeholder:text-vert-clair/80 focus:bg-white focus:text-vert-sombre focus:ring-2 focus:ring-vert-clair">
</form>
