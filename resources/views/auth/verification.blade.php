<x-tunnel-shell :title="'Vérifie ton email · Run For Lorène'">
    <livewire:auth.identification :destination="session('url.intended', route('espace'))" mode="code" />
</x-tunnel-shell>
