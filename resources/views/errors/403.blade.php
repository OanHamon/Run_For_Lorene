{{-- Laravel fournit ses propres 401, 402, 403 et 429, prioritaires sur 4xx : on les redirige vers le gabarit français. --}}
@include('errors::4xx')
