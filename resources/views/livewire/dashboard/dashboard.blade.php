<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div class="max-w-7xl mx-auto px-4">
    
    <div class="grid md:grid-cols-4 gap-6 md:grid-cols-1">
        <x-stat
            title="Messages"
            value="44"
            icon="o-envelope"
            tooltip="Hello"
            color="text-primary" />
        
        <x-stat
            title="Sales"
            description="This month"
            value="22.124"
            icon="o-arrow-trending-up"
            tooltip-bottom="There" />
        
        <x-stat
            title="Lost"
            description="This month"
            value="34"
            icon="o-arrow-trending-down"
            tooltip-left="Ops!" />
        
        <x-stat
            title="Sales"
            description="This month"
            value="22.124"
            icon="o-arrow-trending-down"
            class="text-orange-500"
            color="text-pink-500"
            tooltip-right="Gosh!" />
    </div>

    <div class="grid md:grid-cols-2 grid-cols-1 gap-4 mt-4">
        <x-card>
        </x-card>

        <x-card>
        </x-card>
    </div>

</div>
