<?php

use Carbon\Carbon;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {
    
    public $redundantTickets = [];
    public $loadingTickets = false;

    public function mount():void {
        $this->loadRedundantTickets();
    }

public $currentPage = 1;
public $totalPages = 1;
public $totalItems = 0;
public $perPage = 10;

public function loadRedundantTickets($page = 1): void
{
    $this->loadingTickets = true;

    try {
        $token = session('token') ?: $this->loginAndGetToken();

        if ($token) {
            $response = Http::withHeaders([
                'x-secret-key' => env('X_SECRET_KEY'),
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->get('https://dev-ia.astucom.com/n8n_cosmia/ticket/list/getRedudentTicket', [
                'page' => $page,
                'per_page' => $this->perPage,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $this->redundantTickets = $data['data'] ?? [];
                $this->currentPage = $data['current_page'] ?? 1;
                $this->totalPages = $data['total_page'] ?? 1;
                $this->totalItems = $data['total_item'] ?? 0;
            }
        }
    } catch (\Exception $e) {
        \Log::error('Error loading redundant tickets: ' . $e->getMessage());
        $this->redundantTickets = [];
    } finally {
        $this->loadingTickets = false;
    }
}

public function goToPage($page): void
{
    if ($page >= 1 && $page <= $this->totalPages) {
        $this->loadRedundantTickets($page);
        $this->dispatch('scroll-to-top');
    }
}

public function updatedPerPage(): void
{
    $this->loadRedundantTickets(1);
}

public function getPageRange(): array
{
    $range = [];
    $totalPages = $this->totalPages;
    $currentPage = $this->currentPage;

    if ($totalPages <= 7) {
        // Si 7 pages ou moins, afficher toutes les pages
        return range(1, $totalPages);
    }

    // Toujours afficher la première page
    $range[] = 1;

    if ($currentPage <= 3) {
        // Au début
        $range = array_merge($range, [2, 3, 4]);
        $range[] = '...';
        $range[] = $totalPages;
    } elseif ($currentPage >= $totalPages - 2) {
        // À la fin
        $range[] = '...';
        $range = array_merge($range, [$totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages]);
    } else {
        // Au milieu
        $range[] = '...';
        $range = array_merge($range, [$currentPage - 1, $currentPage, $currentPage + 1]);
        $range[] = '...';
        $range[] = $totalPages;
    }

    return $range;
}

}; ?>

<div class="mt-8" id="redundant-tickets-section">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">
            Tickets Redondants 
            @if($totalItems > 0)
                <span class="text-sm font-normal text-gray-500">({{ $totalItems }} au total)</span>
            @endif
        </h3>

        <!-- Select per page -->
        <div class="flex items-center gap-x-2">
            <label for="perPage" class="text-sm text-gray-700">Afficher:</label>
            <select 
                id="perPage"
                wire:model.live="perPage"
                class="rounded-md border-gray-300 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>
    
    @if($loadingTickets)
        <x-card>
            <div class="flex justify-center py-8">
                <x-loading class="loading-spinner" />
            </div>
        </x-card>
    @else
        <x-card>
            <ul role="list" class="divide-y divide-gray-200">
                @forelse($redundantTickets as $ticket)
                    <li class="flex flex-wrap items-center justify-between gap-x-6 gap-y-4 py-5 sm:flex-nowrap">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm/6 font-semibold text-gray-900 truncate">
                                {{-- <a href="#" class="hover:underline">{{ Str::limit($ticket['subjects_ticket'] ?? 'Sans sujet', 80) }}</a> --}}
                                <a 
                                    href="{{ route('redondant.view', ['email' => $ticket['original_client_mail'], 'commande' => $ticket['num_commande']]) }}"
                                    wire:navigate
                                    class="text-sm/6 font-semibold text-gray-900 hover:text-indigo-600 transition-colors"
                                >
                                    {{ Str::limit($ticket['subjects_ticket'] ?? 'Sans sujet', 80) }}
                                </a>
                            </p>
                            <div class="mt-1 flex items-center gap-x-2 text-xs/5 text-gray-500">
                                <p>
                                    <span class="font-medium">{{ $ticket['original_client_mail'] ?? 'Inconnu' }}</span>
                                </p>
                                <svg viewBox="0 0 2 2" class="size-0.5 fill-current">
                                    <circle cx="1" cy="1" r="1" />
                                </svg>
                                <p>
                                    <span class="text-gray-600">Commande: </span>
                                    <span class="font-medium">{{ $ticket['num_commande'] ?? 'inconnu' }}</span>
                                </p>
                            </div>
                        </div>
                        
                        <dl class="flex w-full flex-none justify-end gap-x-8 sm:w-auto">
                            <div class="flex items-center gap-x-2.5">
                                <dt>
                                    <span class="sr-only">Nombre de récurrences</span>
                                    <svg class="size-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                </dt>
                                <dd class="text-sm/6 font-semibold text-gray-900">
                                    {{ $ticket['total_in_group'] ?? 0 }}
                                </dd>
                            </div>
                        </dl>
                    </li>
                @empty
                    <li class="text-center py-8 text-gray-500">
                        <p>Aucun ticket redondant trouvé</p>
                    </li>
                @endforelse
            </ul>
            
            @if($totalPages > 1)
                <div class="border-t border-gray-200 px-4 py-4 sm:px-6">
                    <div class="flex items-center justify-center">
                        <div class="inline-flex rounded-md shadow-sm" role="group">
                            @foreach($this->getPageRange() as $page)
                                @if($page === '...')
                                    <button 
                                        disabled
                                        class="px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-300 cursor-default"
                                    >
                                        ...
                                    </button>
                                @else
                                    <button 
                                        wire:click="goToPage({{ $page }})"
                                        wire:loading.attr="disabled"
                                        class="px-4 py-2 text-sm font-medium border {{ $currentPage === $page 
                                            ? 'text-white bg-indigo-600 border-indigo-600 z-10' 
                                            : 'text-gray-700 bg-white border-gray-300 hover:bg-gray-50' }} 
                                            {{ $loop->first ? 'rounded-l-md' : '' }}
                                            {{ $loop->last ? 'rounded-r-md' : '' }}
                                            {{ !$loop->first && !$loop->last ? '-ml-px' : '' }}
                                            disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        {{ $page }}
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <!-- Navigation précédent/suivant -->
                    <div class="flex items-center justify-between mt-4">
                        <button 
                            wire:click="goToPage({{ $currentPage - 1 }})"
                            wire:loading.attr="disabled"
                            @if($currentPage === 1) disabled @endif
                            class="flex items-center gap-x-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                            Précédent
                        </button>

                        <span class="text-sm text-gray-700">
                            Page <span class="font-medium">{{ $currentPage }}</span> sur <span class="font-medium">{{ $totalPages }}</span>
                        </span>

                        <button 
                            wire:click="goToPage({{ $currentPage + 1 }})"
                            wire:loading.attr="disabled"
                            @if($currentPage === $totalPages) disabled @endif
                            class="flex items-center gap-x-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Suivant
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endif
        </x-card>
    @endif
</div>

@script
<script>
    $wire.on('scroll-to-top', () => {
        document.getElementById('redundant-tickets-section').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    });
</script>
@endscript