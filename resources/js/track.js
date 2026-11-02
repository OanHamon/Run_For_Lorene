/**
 * Point d'entrée TRACK : monte les empreintes de la page (`<x-track.empreinte>`).
 */
import { monterEmpreintes } from './track/empreinte';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => monterEmpreintes());
} else {
    monterEmpreintes();
}

// Navigation Livewire (wire:navigate) : la nouvelle page peut contenir des empreintes.
document.addEventListener('livewire:navigated', () => monterEmpreintes());

window.TRACK = { monterEmpreintes };
