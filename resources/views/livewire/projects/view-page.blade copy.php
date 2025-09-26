<?php

use Livewire\Volt\Component;

new class extends Component {
    public bool $myModal1 = false;
    public array $selectedTicket = [];

    public function openTicket($ticket)
    {
        $this->selectedTicket = $ticket;
        $this->myModal1 = true;
    }
}; ?>

<div class="w-full mx-auto px-4">
    <x-header title="Projet - COSMASHOP" subtitle="Tous les tickets" separator />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">

        <!-- Open -->
        <div class="bg-gray-50 rounded-xl shadow-sm flex flex-col">
            <div class="flex items-center justify-between p-4 border-b">
                <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <span class="text-blue-500">⬤</span> Open
                </h2>
                <span class="text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded-full">7</span>
            </div>
            <div class="p-3 space-y-3">
                @foreach([1,2,3,4,5,6,7] as $i)
                    <div class="bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition cursor-pointer"
                         wire:click="openTicket({{
                            json_encode([
                                'title' => "Ticket $i - Bug sur checkout",
                                'desc' => "Description rapide du problème rencontré par l’utilisateur...",
                                'tag'  => 'Idea',
                                'comments' => 3,
                                'id' => 8500 + $i,
                                'user' => "https://i.pravatar.cc/64?img=$i"
                            ])
                         }})">
                        <h3 class="text-sm font-semibold text-gray-800 mb-1">Ticket {{ $i }} - Bug sur checkout</h3>
                        <p class="text-xs text-gray-500 mb-2">Description rapide du problème rencontré par l’utilisateur...</p>
                        <div class="flex items-center justify-between text-xs text-gray-400">
                            <div class="flex gap-2">
                                <span class="px-2 py-0.5 text-[10px] bg-pink-100 text-pink-600 rounded-full">Idea</span>
                                <span class="flex items-center gap-1">💬 3</span>
                                <span>#{{ 8500 + $i }}</span>
                            </div>
                            <img src="https://i.pravatar.cc/24?img={{ $i }}" class="w-5 h-5 rounded-full" alt="user">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- In Progress -->
        <div class="bg-gray-50 rounded-xl shadow-sm flex flex-col">
            <div class="flex items-center justify-between p-4 border-b">
                <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <span class="text-yellow-500">⬤</span> In Progress
                </h2>
                <span class="text-xs bg-yellow-100 text-yellow-600 px-2 py-1 rounded-full">2</span>
            </div>
            <div class="p-3 space-y-3">
                @foreach([1,2] as $i)
                    <div class="bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition cursor-pointer"
                         wire:click="openTicket({{
                            json_encode([
                                'title' => "Task $i - Refactoring code",
                                'desc' => "Amélioration du code backend pour optimiser les perfs...",
                                'tag'  => 'Billing',
                                'comments' => 5,
                                'id' => 8600 + $i,
                                'user' => "https://i.pravatar.cc/64?img=".(20+$i)
                            ])
                         }})">
                        <h3 class="text-sm font-semibold text-gray-800 mb-1">Task {{ $i }} - Refactoring code</h3>
                        <p class="text-xs text-gray-500 mb-2">Amélioration du code backend pour optimiser les perfs...</p>
                        <div class="flex items-center justify-between text-xs text-gray-400">
                            <div class="flex gap-2">
                                <span class="px-2 py-0.5 text-[10px] bg-blue-100 text-blue-600 rounded-full">Billing</span>
                                <span class="flex items-center gap-1">💬 5</span>
                                <span>#{{ 8600 + $i }}</span>
                            </div>
                            <img src="https://i.pravatar.cc/24?img={{ 20 + $i }}" class="w-5 h-5 rounded-full" alt="user">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Done -->
        <div class="bg-gray-50 rounded-xl shadow-sm flex flex-col">
            <div class="flex items-center justify-between p-4 border-b">
                <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <span class="text-green-500">⬤</span> Done
                </h2>
                <span class="text-xs bg-green-100 text-green-600 px-2 py-1 rounded-full">5</span>
            </div>
            <div class="p-3 space-y-3">
                @foreach([1,2,3,4,5] as $i)
                    <div class="bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition cursor-pointer"
                         wire:click="openTicket({{
                            json_encode([
                                'title' => "Feature $i - API intégrée",
                                'desc' => "Intégration de l’API de paiement terminée avec succès.",
                                'tag'  => 'Account',
                                'comments' => $i,
                                'id' => 8700 + $i,
                                'user' => "https://i.pravatar.cc/64?img=".(40+$i)
                            ])
                         }})">
                        <h3 class="text-sm font-semibold text-gray-800 mb-1">Feature {{ $i }} - API intégrée</h3>
                        <p class="text-xs text-gray-500 mb-2">Intégration de l’API de paiement terminée avec succès.</p>
                        <div class="flex items-center justify-between text-xs text-gray-400">
                            <div class="flex gap-2">
                                <span class="px-2 py-0.5 text-[10px] bg-green-100 text-green-600 rounded-full">Account</span>
                                <span class="flex items-center gap-1">💬 {{ $i }}</span>
                                <span>#{{ 8700 + $i }}</span>
                            </div>
                            <img src="https://i.pravatar.cc/24?img={{ 40 + $i }}" class="w-5 h-5 rounded-full" alt="user">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Modal -->
    <x-modal wire:model="myModal1" title="{{ $selectedTicket['title'] ?? 'Détail du ticket' }}" class="backdrop-blur">
        <p class="text-gray-700 mb-3">{{ $selectedTicket['desc'] ?? '' }}</p>

        <div class="flex items-center justify-between text-sm text-gray-500">
            <span class="px-2 py-0.5 text-[10px] rounded-full bg-gray-100 text-gray-600">
                {{ $selectedTicket['tag'] ?? '' }}
            </span>
            <span>💬 {{ $selectedTicket['comments'] ?? 0 }}</span>
            <span>#{{ $selectedTicket['id'] ?? '' }}</span>
            @if(!empty($selectedTicket['user']))
                <img src="{{ $selectedTicket['user'] }}" class="w-6 h-6 rounded-full" alt="user">
            @endif
        </div>

        <x-slot:actions>
            <x-button label="Fermer" @click="$wire.myModal1 = false" />
        </x-slot:actions>
    </x-modal>
</div>
