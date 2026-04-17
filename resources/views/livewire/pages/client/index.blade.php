<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Keycloak Clients</x-nawasara-ui::page.title>

        <x-slot name="actions">
            <x-nawasara-ui::page.actions>
                <x-nawasara-ui::button wire:click="$dispatch('openCreateClient')" color="success"
                    @click="$dispatch('open-modal', 'kc-client-form')"
                    permission="keycloak.client.manage">
                    <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                    Tambah Client
                </x-nawasara-ui::button>
            </x-nawasara-ui::page.actions>
        </x-slot>

        @livewire('nawasara-keycloak.client.section.table')
    </x-nawasara-ui::page.container>
</div>
