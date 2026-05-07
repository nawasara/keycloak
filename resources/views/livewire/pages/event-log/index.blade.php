<div>
    {{-- Title + time-window hoisted into the section component (which
         owns the reactive state). Index is just a shell. --}}
    <x-nawasara-ui::page.container>
        @livewire('nawasara-keycloak.event-log.section.table')
    </x-nawasara-ui::page.container>
</div>
