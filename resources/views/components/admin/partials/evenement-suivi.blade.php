{{-- Événement suivi dans tout le back-office (retenu en session). --}}
@if ($evenementsSuivis->isNotEmpty())
    <form method="POST" action="{{ route('admin.evenement-suivi') }}">
        @csrf
        <label for="{{ $id }}" class="sr-only">Événement suivi</label>
        <select id="{{ $id }}" name="evenement" onchange="this.form.submit()" class="w-full rounded-2xl border-0 bg-white px-4 py-2 text-sm text-vert-sombre focus:ring-2 focus:ring-vert-clair">
            @foreach ($evenementsSuivis as $e)
                <option value="{{ $e->id_evenement }}" @selected($e->id_evenement === $evenementSuivi?->id_evenement)>{{ $e->nom }} · {{ $e->date_evenement->translatedFormat('j M Y') }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="mt-1 text-xs underline">Changer d'événement</button></noscript>
    </form>
@endif
