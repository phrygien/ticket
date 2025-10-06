<?php 

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public bool $myModal1 = false;
    public array $selectedTicket = [];
    public array $ticketDetails = [];

    public string $search = '';
    public string $searchType = 'all';

    public array $ticketsByStatus = [
        'en attente' => [],
        'en cours' => [],
        'cloture' => []
    ];

    public array $pagesByStatus = [
        'en attente' => 1,
        'en cours' => 1,
        'cloture' => 1
    ];

    public array $hasMorePages = [
        'en attente' => true,
        'en cours' => true,
        'cloture' => true
    ];

    public array $loadingByStatus = [
        'en attente' => false,
        'en cours' => false,
        'cloture' => false
    ];

    public array $totalItemByStatus = [
        'en attente' => 0,
        'en cours' => 0,
        'cloture' => 0
    ];

    public bool $loadingDetails = false;
    public int|string $projectId = 'all';

    public function mount($id = 'all')
    {
        $this->projectId = $id === 'all' ? 'all' : (int) $id;
        $this->loadAllStatuses();
    }

    public function updatedSearch()
    {
        $this->pagesByStatus = [
            'en attente' => 1,
            'en cours' => 1,
            'cloture' => 1
        ];

        $this->ticketsByStatus = [
            'en attente' => [],
            'en cours' => [],
            'cloture' => []
        ];

        $this->loadAllStatuses();
    }

    public function loadAllStatuses()
    {
        $this->fetchTicketsByStatus('en attente');
        $this->fetchTicketsByStatus('en cours');
        $this->fetchTicketsByStatus('cloture');
    }

    public function fetchTicketsByStatus($status, $append = false)
    {
        $this->loadingByStatus[$status] = true;

        $token = session('token');
        if (!$token) {
            return redirect()->route('login');
        }

        $page = $this->pagesByStatus[$status];
        $url = env('API_REST') ."/ticket?page={$page}&status=" . urlencode($status);

        if ($this->projectId !== 'all') {
            $url .= "&project_id={$this->projectId}";
        }

        if (!empty($this->search)) {
            $searchValue = trim($this->search);

            switch ($this->searchType) {
                case 'num_ticket':
                    $url .= "&num_ticket=" . $searchValue;
                    break;
                case 'subject':
                    $url .= "&subject_ticket=" . urlencode($searchValue);
                    break;
                case 'email':
                    $url .= "&original_client_mail=" . urlencode($searchValue);
                    break;
                case 'client':
                    $url .= "&nom_client=" . urlencode($searchValue);
                    break;
                case 'commande':
                    $url .= "&num_commande=" . $searchValue;
                    break;
                case 'all':
                default:
                    if (is_numeric($searchValue)) {
                        $url .= "&num_ticket=" . $searchValue;
                    } elseif (str_contains($searchValue, '@')) {
                        $url .= "&original_client_mail=" . urlencode($searchValue);
                    } else {
                        $url .= "&subject_ticket=" . urlencode($searchValue);
                    }
                    break;
            }
        }

        $response = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->get($url);

        if ($response->successful()) {
            $data = $response->json();
            $newTickets = $data['data'] ?? [];

            if ($append) {
                $this->ticketsByStatus[$status] = array_merge(
                    $this->ticketsByStatus[$status],
                    $newTickets
                );
            } else {
                $this->ticketsByStatus[$status] = $newTickets;
            }

            $currentPage = $data['current_page'] ?? $page;
            $totalPages = $data['total_page'] ?? 1;
            $this->hasMorePages[$status] = $currentPage < $totalPages;
            $this->totalItemByStatus[$status] = $data['total_item'];
        }

        $this->loadingByStatus[$status] = false;
    }

    public function loadMore($status)
    {
        if ($this->hasMorePages[$status] && !$this->loadingByStatus[$status]) {
            $this->pagesByStatus[$status]++;
            $this->fetchTicketsByStatus($status, true);
        }
    }


    public function updateTicketStatus($ticketId, $newStatus)
    {
        $token = session('token');

        $response = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->put(env('API_REST')."/ticket/{$ticketId}", [
                    'status' => $newStatus
                ]);

        if ($response->successful()) {
            $this->loadAllStatuses();

            session()->flash('success', 'Ticket déplacé avec succès');
        } else {
            session()->flash('error', 'Erreur lors du déplacement du ticket');
        }
    }

    /**
     * Recharge tous les tickets quand le projet change
     */
    public function setProject($id)
    {
        $this->projectId = $id;

        $this->pagesByStatus = [
            'en attente' => 1,
            'en cours' => 1,
            'cloture' => 1
        ];

        $this->hasMorePages = [
            'en attente' => true,
            'en cours' => true,
            'cloture' => true
        ];

        $this->ticketsByStatus = [
            'en attente' => [],
            'en cours' => [],
            'cloture' => []
        ];

        $this->loadAllStatuses();
    }

    public function fetchTicketDetails($ticketId)
    {
        $this->loadingDetails = true;

        $token = session('token');

        $response = Http::withHeaders([
            'x-secret-key' => env('X_SECRET_KEY'),
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->get(env('API_REST')."/ticket/{$ticketId}");

        if ($response->successful()) {
            $this->ticketDetails = $response->json();
        }

        $this->loadingDetails = false;
    }

    public function openTicket($ticketId)
    {
        $ticket = null;
        foreach ($this->ticketsByStatus as $tickets) {
            $found = collect($tickets)->firstWhere('id', $ticketId);
            if ($found) {
                $ticket = $found;
                break;
            }
        }

        if ($ticket) {
            $this->selectedTicket = $ticket;
            $this->myModal1 = true;
            $this->fetchTicketDetails($ticketId);
        }
    }

    public function closeModal()
    {
        $this->myModal1 = false;
        $this->ticketDetails = [];
        $this->selectedTicket = [];
    }

    public function getTicketCountForStatus($status)
    {
        return count($this->ticketsByStatus[$status]);
    }

    public function getTotalTicketForStatus($status)
    {
        return $this->totalItemByStatus[$status];
    }

 public function updatedSearchType()
{
    if (!empty($this->search)) {
        $this->resetPagination();
        $this->loadAllStatuses();
    }
}

private function resetPagination()
{
    $this->pagesByStatus = [
        'en attente' => 1,
        'en cours' => 1,
        'cloture' => 1
    ];

    $this->ticketsByStatus = [
        'en attente' => [],
        'en cours' => [],
        'cloture' => []
    ];
}

}; ?>

<div class="min-h-screen w-full bg-gradient-to-br from-gray-50 to-gray-100">
<x-header title="Tableau Kanban" subtitle="Gestion des tickets" separator>
    <x-slot:middle class="!justify-end">
        <div class="flex items-center gap-3">
            <fieldset class="fieldset hidden sm:block">
                <select class="select" wire:model.live="searchType">
                    <option value="all">Tout</option>
                    <option value="num_ticket">N° Ticket</option>
                    <option value="subject">Sujet</option>
                    <option value="email">Email</option>
                    <option value="client">Client</option>
                    <option value="commande">N° Commande</option>
                </select>
            </fieldset>
            
            <x-input 
                icon="o-magnifying-glass" 
                placeholder="Rechercher..." 
                wire:model.live.debounce.500ms="search"
                clearable
                class="w-64"
            />
        </div>
    </x-slot:middle>
    
    <x-slot:actions>
        <div class="flex items-center gap-3">
            <div class="sm:hidden">
                <select aria-label="Select a tab"
                    wire:model.live="projectId"
                    class="block w-full rounded-lg border-0 py-2 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600">
                    <option value="all">Tous les projets</option>
                    <option value="1">COSMASHOP</option>
                    <option value="2">COSMA PARFUMERIES</option>
                    <option value="3">DIGIPARF</option>
                </select>
            </div>
            
            <div class="hidden sm:block">
                <nav class="flex gap-2 bg-white rounded-lg p-1 shadow-sm">
                    <button wire:click="setProject('all')"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200
                        {{ $projectId === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                        Tous
                    </button>
                    <button wire:click="setProject(1)"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200
                        {{ $projectId === 1 ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                        COSMASHOP
                    </button>
                    <button wire:click="setProject(2)"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200
                        {{ $projectId === 2 ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                        COSMA PARFUMERIES
                    </button>
                    <button wire:click="setProject(3)"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200
                        {{ $projectId === 3 ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                        DIGIPARF
                    </button>
                </nav>
            </div>
        </div>
    </x-slot:actions>
</x-header>

<div class="mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Colonne En Attente -->
        <div class="flex flex-col bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-white font-semibold">En Attente</h3>
                </div>
                <span class="bg-white/20 backdrop-blur-sm text-white text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ $this->getTicketCountForStatus('en attente') }} sur {{ $this->getTotalTicketForStatus('en attente') }}
                </span>
            </div>

            <div 
                id="status-en-attente" 
                class="flex-1 p-4 space-y-3 overflow-y-auto max-h-[calc(100vh-280px)]"
                x-data="kanbanColumn('en attente')"
                x-init="init()"
                @scroll="handleScroll"
                @drop.prevent="handleDrop($event)"
                @dragover.prevent
                @dragenter.prevent="$el.classList.add('ring-2', 'ring-purple-300', 'bg-purple-50')"
                @dragleave.prevent="$el.classList.remove('ring-2', 'ring-purple-300', 'bg-purple-50')"
            >
                @foreach($ticketsByStatus['en attente'] as $ticket)
                    <div 
                        draggable="true"
                        data-ticket-id="{{ $ticket['id'] }}"
                        data-status="en attente"
                        @dragstart="handleDragStart($event)"
                        @dragend="handleDragEnd($event)"
                        class="group bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-all duration-200 cursor-move hover:scale-[1.02]"
                    >
                        <div class="flex items-start justify-between mb-3">
                            <span class="text-xs font-medium text-purple-600 bg-purple-100 px-2 py-1 rounded">
                                {{ $ticket['num_ticket'] }}
                            </span>
                            @if($ticket['need_attention'] == 1)
                                <span class="flex items-center gap-1 text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded animate-pulse">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    Réponse du client
                                </span>
                            @endif
                        </div>

                        <h4 class="font-semibold text-gray-900 mb-2 line-clamp-2">
                            {{ Str::limit($ticket['subject_ticket'], 60) }}
                        </h4>

                        <div class="space-y-1.5 mb-3">
                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" class="size-4">
                                    <path d="M1.75 1.002a.75.75 0 1 0 0 1.5h1.835l1.24 5.113A3.752 3.752 0 0 0 2 11.25c0 .414.336.75.75.75h10.5a.75.75 0 0 0 0-1.5H3.628A2.25 2.25 0 0 1 5.75 9h6.5a.75.75 0 0 0 .73-.578l.846-3.595a.75.75 0 0 0-.578-.906 44.118 44.118 0 0 0-7.996-.91l-.348-1.436a.75.75 0 0 0-.73-.573H1.75ZM5 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM13 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" />
                                </svg>
                                <span class="font-medium">N° commande: {{ $ticket['num_commande'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                <span class="font-medium">{{ $ticket['label'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-purple-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                </svg>
                                <span>{{ $ticket['project_name'] }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-end">
                            <a href="{{ route('ticket.detail', ['ticket' => $ticket['id']]) }}" 
                               wire:navigate
                               class="text-sm font-medium text-purple-600 hover:text-purple-700 flex items-center gap-1 group-hover:gap-2 transition-all">
                                Voir détails
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                @endforeach
                
                @if($loadingByStatus['en attente'])
                    <div class="flex items-center justify-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
                        <span class="ml-3 text-sm text-gray-500">Chargement...</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Colonne En Cours -->
        <div class="flex flex-col bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-amber-500 to-amber-600 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <h3 class="text-white font-semibold">En Cours</h3>
                </div>
                <span class="bg-white/20 backdrop-blur-sm text-white text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ $this->getTicketCountForStatus('en cours') }} sur {{ $this->getTotalTicketForStatus('en cours') }}
                </span>
            </div>

            <div 
                id="status-en-cours" 
                class="flex-1 p-4 space-y-3 overflow-y-auto max-h-[calc(100vh-280px)]"
                x-data="kanbanColumn('en cours')"
                x-init="init()"
                @scroll="handleScroll"
                @drop.prevent="handleDrop($event)"
                @dragover.prevent
                @dragenter.prevent="$el.classList.add('ring-2', 'ring-amber-300', 'bg-amber-50')"
                @dragleave.prevent="$el.classList.remove('ring-2', 'ring-amber-300', 'bg-amber-50')"
            >
                @foreach($ticketsByStatus['en cours'] as $ticket)
                    <div 
                        draggable="true"
                        data-ticket-id="{{ $ticket['id'] }}"
                        data-status="en cours"
                        @dragstart="handleDragStart($event)"
                        @dragend="handleDragEnd($event)"
                        class="group bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-all duration-200 cursor-move hover:scale-[1.02]"
                    >
                        <div class="flex items-start justify-between mb-3">
                            <span class="text-xs font-medium text-amber-600 bg-amber-100 px-2 py-1 rounded">
                                {{ $ticket['num_ticket'] }}
                            </span>
                            @if($ticket['need_attention'] == 1)
                                <span class="flex items-center gap-1 text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded animate-pulse">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    Réponse du client
                                </span>
                            @endif
                        </div>

                        <h4 class="font-semibold text-gray-900 mb-2 line-clamp-2">
                            {{ Str::limit($ticket['subject_ticket'], 60) }}
                        </h4>

                        <div class="space-y-1.5 mb-3">
                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" class="size-4">
                                    <path d="M1.75 1.002a.75.75 0 1 0 0 1.5h1.835l1.24 5.113A3.752 3.752 0 0 0 2 11.25c0 .414.336.75.75.75h10.5a.75.75 0 0 0 0-1.5H3.628A2.25 2.25 0 0 1 5.75 9h6.5a.75.75 0 0 0 .73-.578l.846-3.595a.75.75 0 0 0-.578-.906 44.118 44.118 0 0 0-7.996-.91l-.348-1.436a.75.75 0 0 0-.73-.573H1.75ZM5 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM13 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" />
                                </svg>
                                <span class="font-medium">N° commande: {{ $ticket['num_commande'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                <span class="font-medium">{{ $ticket['label'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-amber-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                </svg>
                                <span>{{ $ticket['project_name'] }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-end">
                            <a href="{{ route('ticket.detail', ['ticket' => $ticket['id']]) }}" 
                               wire:navigate
                               class="text-sm font-medium text-amber-600 hover:text-amber-700 flex items-center gap-1 group-hover:gap-2 transition-all">
                                Voir détails
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                @endforeach
                
                @if($loadingByStatus['en cours'])
                    <div class="flex items-center justify-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-amber-600"></div>
                        <span class="ml-3 text-sm text-gray-500">Chargement...</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Colonne Clôturé -->
        <div class="flex flex-col bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-white font-semibold">Clôturé</h3>
                </div>
                <span class="bg-white/20 backdrop-blur-sm text-white text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ $this->getTicketCountForStatus('cloture') }} sur {{ $this->getTotalTicketForStatus('cloture') }}
                </span>
            </div>

            <div 
                id="status-cloture" 
                class="flex-1 p-4 space-y-3 overflow-y-auto max-h-[calc(100vh-280px)]"
                x-data="kanbanColumn('cloture')"
                x-init="init()"
                @scroll="handleScroll"
                @drop.prevent="handleDrop($event)"
                @dragover.prevent
                @dragenter.prevent="$el.classList.add('ring-2', 'ring-green-300', 'bg-green-50')"
                @dragleave.prevent="$el.classList.remove('ring-2', 'ring-green-300', 'bg-green-50')"
            >
                @foreach($ticketsByStatus['cloture'] as $ticket)
                    <div 
                        draggable="true"
                        data-ticket-id="{{ $ticket['id'] }}"
                        data-status="cloture"
                        @dragstart="handleDragStart($event)"
                        @dragend="handleDragEnd($event)"
                        class="group bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-all duration-200 cursor-move hover:scale-[1.02] opacity-75"
                    >
                        <div class="flex items-start justify-between mb-3">
                            <span class="text-xs font-medium text-green-600 bg-green-100 px-2 py-1 rounded">
                                {{ $ticket['num_ticket'] }}
                            </span>
                            @if($ticket['need_attention'] == 1)
                                <span class="flex items-center gap-1 text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded animate-pulse">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    Réponse du client
                                </span>
                            @endif
                        </div>

                        <h4 class="font-semibold text-gray-900 mb-2 line-clamp-2">
                            {{ Str::limit($ticket['subject_ticket'], 60) }}
                        </h4>

                        <div class="space-y-1.5 mb-3">
                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" class="size-4">
                                    <path d="M1.75 1.002a.75.75 0 1 0 0 1.5h1.835l1.24 5.113A3.752 3.752 0 0 0 2 11.25c0 .414.336.75.75.75h10.5a.75.75 0 0 0 0-1.5H3.628A2.25 2.25 0 0 1 5.75 9h6.5a.75.75 0 0 0 .73-.578l.846-3.595a.75.75 0 0 0-.578-.906 44.118 44.118 0 0 0-7.996-.91l-.348-1.436a.75.75 0 0 0-.73-.573H1.75ZM5 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM13 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" />
                                </svg>
                                <span class="font-medium">N° commande: {{ $ticket['num_commande'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                <span class="font-medium">{{ $ticket['label'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-green-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                </svg>
                                <span>{{ $ticket['project_name'] }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-end">
                            <a href="{{ route('ticket.detail', ['ticket' => $ticket['id']]) }}" 
                               wire:navigate
                               class="text-sm font-medium text-green-600 hover:text-green-700 flex items-center gap-1 group-hover:gap-2 transition-all">
                                Voir détails
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                @endforeach
                
                @if($loadingByStatus['cloture'])
                    <div class="flex items-center justify-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div>
                        <span class="ml-3 text-sm text-gray-500">Chargement...</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

    <x-modal wire:model="myModal1" title="Détails du Ticket" class="backdrop-blur"
        box-class="bg-gray-200 max-w-7xl h-[90vh]">
        @if($loadingDetails)
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                <span class="ml-3 text-lg">Chargement des détails...</span>
            </div>
        @elseif(!empty($ticketDetails))
            <x-card title="Client" class="mt-2">
                <p>Informations du client...</p>
            </x-card>

            <x-card title="Support" class="mt-2">
                <p>Informations du support...</p>
            </x-card>
        @endif

        <x-slot:actions>
            <x-button label="Fermer" @click="$wire.closeModal()" class="btn-primary" />
        </x-slot:actions>
    </x-modal>

    @push('scripts')
    <script>
    function kanbanColumn(status) {
        return {
            status: status,
            isLoading: false,
            draggedElement: null,
            
            init() {
                // Initialisation si nécessaire
            },
            
            handleScroll(event) {
                const element = event.target;
                const threshold = 100;
                
                if (element.scrollTop + element.clientHeight >= element.scrollHeight - threshold) {
                    this.loadMore();
                }
            },
            
            loadMore() {
                if (this.isLoading) return;
                
                this.isLoading = true;
                
                @this.call('loadMore', this.status).then(() => {
                    this.isLoading = false;
                }).catch(() => {
                    this.isLoading = false;
                });
            },

            handleDragStart(event) {
                const card = event.target;
                this.draggedElement = card;
                
                card.classList.add('opacity-50', 'scale-95');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/html', card.innerHTML);
                
                const ticketId = card.dataset.ticketId;
                const currentStatus = card.dataset.status;
                event.dataTransfer.setData('ticketId', ticketId);
                event.dataTransfer.setData('currentStatus', currentStatus);
            },

            handleDragEnd(event) {
                event.target.classList.remove('opacity-50', 'scale-95');
                
                document.querySelectorAll('[id^="status-"]').forEach(col => {
                    col.classList.remove('ring-2', 'ring-purple-300', 'ring-amber-300', 'ring-green-300', 'bg-purple-50', 'bg-amber-50', 'bg-green-50');
                });
            },

            handleDrop(event) {
                event.preventDefault();
                const column = event.currentTarget;
                
                column.classList.remove('ring-2', 'ring-purple-300', 'ring-amber-300', 'ring-green-300', 'bg-purple-50', 'bg-amber-50', 'bg-green-50');
                
                const ticketId = event.dataTransfer.getData('ticketId');
                const currentStatus = event.dataTransfer.getData('currentStatus');
                const newStatus = this.status;

                if (currentStatus === newStatus) {
                    return;
                }
                
                this.showLoadingIndicator(column);
                
                @this.call('updateTicketStatus', ticketId, newStatus)
                    .then(() => {
                        this.hideLoadingIndicator(column);
                        this.showSuccessAnimation(column);
                    })
                    .catch((error) => {
                        this.hideLoadingIndicator(column);
                        this.showErrorAnimation(column);
                        console.error('Erreur lors du déplacement:', error);
                    });
            },

            showLoadingIndicator(column) {
                const loader = document.createElement('div');
                loader.id = 'drop-loader';
                loader.className = 'absolute inset-0 bg-white/80 backdrop-blur-sm flex items-center justify-center z-50 rounded-lg';
                loader.innerHTML = `
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600 mx-auto"></div>
                        <p class="mt-2 text-sm text-gray-600">Mise à jour...</p>
                    </div>
                `;
                column.parentElement.style.position = 'relative';
                column.parentElement.appendChild(loader);
            },

            hideLoadingIndicator(column) {
                const loader = column.parentElement.querySelector('#drop-loader');
                if (loader) {
                    loader.remove();
                }
            },

            showSuccessAnimation(column) {
                const success = document.createElement('div');
                success.className = 'absolute inset-0 bg-green-500/20 backdrop-blur-sm flex items-center justify-center z-50 rounded-lg animate-pulse';
                success.innerHTML = `
                    <div class="bg-white rounded-full p-4 shadow-lg">
                        <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                `;
                column.parentElement.appendChild(success);
                
                setTimeout(() => {
                    success.remove();
                }, 1000);
            },

            showErrorAnimation(column) {
                const error = document.createElement('div');
                error.className = 'absolute inset-0 bg-red-500/20 backdrop-blur-sm flex items-center justify-center z-50 rounded-lg';
                error.innerHTML = `
                    <div class="bg-white rounded-full p-4 shadow-lg">
                        <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                `;
                column.parentElement.appendChild(error);
                
                setTimeout(() => {
                    error.remove();
                }, 1500);
            }
        }
    }
    </script>
@endpush

@push('styles')
    <style>
    .overflow-y-auto::-webkit-scrollbar {
        width: 8px;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
        transition: background 0.2s;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    @keyframes pulse-ring {
        0% {
            box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(99, 102, 241, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(99, 102, 241, 0);
        }
    }

    [draggable="true"] {
        transition: all 0.2s ease;
    }

    [draggable="true"]:active {
        cursor: grabbing;
    }

    .group:hover {
        transform: translateY(-2px);
    }

    @keyframes badge-pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.6;
        }
    }

    .animate-pulse {
        animation: badge-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @media (max-width: 1024px) {
        .max-h-\[calc\(100vh-280px\)\] {
            max-height: calc(100vh - 320px);
        }
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .animate-spin {
        animation: spin 1s linear infinite;
    }

    @keyframes checkmark {
        0% {
            stroke-dashoffset: 50;
        }
        100% {
            stroke-dashoffset: 0;
        }
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .group {
        animation: slideIn 0.3s ease-out;
    }

    .ring-2 {
        transition: all 0.3s ease;
    }
    </style>
    @endpush
</div>

