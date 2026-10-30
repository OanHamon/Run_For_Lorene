{{-- Page demandée avant la connexion, sauf une page de l'admin (elle a sa propre connexion). --}}
@php($demandee = session('url.intended'))
<x-tunnel-shell :title="'Connexion · Run For Lorène'">
    <livewire:auth.identification :destination="$demandee && ! Str::startsWith($demandee, url('admin')) ? $demandee : route('espace')" :lien-admin="true" />
</x-tunnel-shell>
