<x-filament::page>
    <x-filament::modal id="result-modal" width="lg" visible="true">
        <x-slot name="header">
            <h2 class="text-lg font-bold">Résultat du Test</h2>
        </x-slot>

        <p>{{ $result }}</p>

        <x-slot name="footer">
            <x-filament::button x-on:click="$dispatch('close', 'result-modal')">Fermer</x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament::page>