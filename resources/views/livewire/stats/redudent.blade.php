<?php

use Livewire\Volt\Component;

new class extends Component {
    
    // Mounted function
    public function mount(): void {}
}; ?>

<div class="mx-auto max-w-9xl">
    <x-header title="Tickets redondants" subtitle="Liste des tickets redondants" separator />

    <livewire:stats.card-total />
    <livewire:stats.list-redudent />
</div>
