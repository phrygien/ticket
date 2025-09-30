<?php 

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public bool $myModal1 = false;
    public array $selectedTicket = [];
    public array $ticketDetails = [];
    
    // Tickets organisés par statut
    public array $ticketsByStatus = [
        'en attente' => [],
        'en cours' => [],
        'cloture' => []
    ];
    
    // Pagination par statut
    public array $pagesByStatus = [
        'en attente' => 1,
        'en cours' => 1,
        'cloture' => 1
    ];
    
    // Indique s'il y a plus de pages pour chaque statut
    public array $hasMorePages = [
        'en attente' => true,
        'en cours' => true,
        'cloture' => true
    ];
    
    // Loading states pour chaque statut
    public array $loadingByStatus = [
        'en attente' => false,
        'en cours' => false,
        'cloture' => false
    ];
    
    public bool $loadingDetails = false;
    public int|string $projectId = 'all';

    public function mount($id = 'all')
    {
        $this->projectId = $id === 'all' ? 'all' : (int) $id;
        $this->loadAllStatuses();
    }

    /**
     * Charge les tickets pour tous les statuts
     */
    public function loadAllStatuses()
    {
        $this->fetchTicketsByStatus('en attente');
        $this->fetchTicketsByStatus('en cours');
        $this->fetchTicketsByStatus('cloture');
    }

    /**
     * Récupère les tickets pour un statut donné
     */
    public function fetchTicketsByStatus($status, $append = false)
    {
        $this->loadingByStatus[$status] = true;
        
        $token = session('token');
        if (!$token) {
            return redirect()->route('login');
        }

        $page = $this->pagesByStatus[$status];
        $url = "https://dev-ia.astucom.com/n8n_cosmia/ticket?page={$page}&status=" . urlencode($status);

        if ($this->projectId !== 'all') {
            $url .= "&project_id={$this->projectId}";
        }

        $response = Http::withHeaders([
            'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->get($url);

        if ($response->successful()) {
            $data = $response->json();
            $newTickets = $data['data'] ?? [];
            
            if ($append) {
                // Ajouter les nouveaux tickets aux existants
                $this->ticketsByStatus[$status] = array_merge(
                    $this->ticketsByStatus[$status], 
                    $newTickets
                );
            } else {
                // Remplacer les tickets existants
                $this->ticketsByStatus[$status] = $newTickets;
            }
            
            // Vérifier s'il y a plus de pages
            $currentPage = $data['current_page'] ?? $page;
            $totalPages = $data['total_page'] ?? 1;
            $this->hasMorePages[$status] = $currentPage < $totalPages;
        }
        
        $this->loadingByStatus[$status] = false;
    }

    /**
     * Charge plus de tickets pour un statut (infinity scroll)
     */
    public function loadMore($status)
    {
        if ($this->hasMorePages[$status] && !$this->loadingByStatus[$status]) {
            $this->pagesByStatus[$status]++;
            $this->fetchTicketsByStatus($status, true);
        }
    }

    /**
     * Recharge tous les tickets quand le projet change
     */
    public function setProject($id)
    {
        $this->projectId = $id;
        
        // Reset pagination pour tous les statuts
        $this->pagesByStatus = [
            'en attente' => 1,
            'en cours' => 1,
            'cloture' => 1
        ];
        
        // Reset hasMorePages
        $this->hasMorePages = [
            'en attente' => true,
            'en cours' => true,
            'cloture' => true
        ];
        
        // Vider les tickets existants
        $this->ticketsByStatus = [
            'en attente' => [],
            'en cours' => [],
            'cloture' => []
        ];
        
        // Recharger
        $this->loadAllStatuses();
    }

    public function fetchTicketDetails($ticketId)
    {
        $this->loadingDetails = true;

        $token = session('token');

        $response = Http::withHeaders([
            'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->get("https://dev-ia.astucom.com/n8n_cosmia/ticket/{$ticketId}");

        if ($response->successful()) {
            $this->ticketDetails = $response->json();
        }

        $this->loadingDetails = false;
    }

    public function openTicket($ticketId)
    {
        // Chercher le ticket dans tous les statuts
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

    /**
     * Retourne le nombre total de tickets pour un statut
     */
    public function getTicketCountForStatus($status)
    {
        return count($this->ticketsByStatus[$status]);
    }
}; ?>

<div class="max-w-9xl mx-auto px-4">
    <x-header title="Détails du projet" subtitle="Tous les tickets" separator>
        <x-slot:middle class="!justify-end">

        </x-slot:middle>
        <x-slot:actions>
            <div>
                <div class="grid grid-cols-1 sm:hidden">
                    <!-- Use an "onChange" listener to redirect the user to the selected tab URL. -->
                    <select aria-label="Select a tab"
                        class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-2 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600">
                        <option wire:click="setProject(1)">COSMASHOP</option>
                        <option wire:click="setProject(2)">COSMA PARFUMERIES</option>
                        <option wire:click="setProject(3)">DIGIPARF</option>
                    </select>
                    <svg class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end fill-gray-500"
                        viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd"
                            d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="hidden sm:block">
                <nav class="flex space-x-4" aria-label="Tabs">
                    <button wire:click="setProject('all')"
                        class="rounded-md px-3 py-2 text-sm font-medium 
                        {{ $projectId === 'all' ? 'bg-gray-200 text-gray-800' : 'text-gray-600 hover:text-gray-800' }}">
                        Tous
                    </button>
                    <button wire:click="setProject(1)"
                        class="rounded-md px-3 py-2 text-sm font-medium 
                        {{ $projectId === 1 ? 'bg-gray-200 text-gray-800' : 'text-gray-600 hover:text-gray-800' }}">
                        COSMASHOP
                    </button>
                    <button wire:click="setProject(2)"
                        class="rounded-md px-3 py-2 text-sm font-medium 
                        {{ $projectId === 2 ? 'bg-gray-200 text-gray-800' : 'text-gray-600 hover:text-gray-800' }}">
                        COSMA PARFUMERIES
                    </button>
                    <button wire:click="setProject(3)"
                        class="rounded-md px-3 py-2 text-sm font-medium 
                        {{ $projectId === 3 ? 'bg-gray-200 text-gray-800' : 'text-gray-600 hover:text-gray-800' }}">
                        DIGIPARF
                    </button>
                </nav>

                </div>
            </div>

        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
        <!-- En attente -->
        <div class="flex flex-col">
            <div class="space-y-3">
                <div class="border-l-4 border-purple-400 bg-purple-50 p-4">
                    <div class="flex justify-between items-center">
                        <div class="ml-3">
                            <p class="text-sm text-purple-700">
                                En attente
                            </p>
                        </div>
                        <span class="text-xs text-purple-600 bg-purple-100 px-2 py-1 rounded-full">
                            {{ $this->getTicketCountForStatus('en attente') }}
                        </span>
                    </div>
                </div>

                <div 
                    id="status-en-attente" 
                    class="space-y-3 max-h-[600px] overflow-y-auto"
                    x-data="infiniteScroll('en attente')"
                    x-init="init()"
                    @scroll="handleScroll"
                >
                    @foreach($ticketsByStatus['en attente'] as $ticket)
                        <div class="bg-white shadow-sm sm:rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-base font-semibold text-gray-900">
                                    {{ Str::limit($ticket['subject_ticket'], 50) }} 
                                    @if($ticket['need_attention'] == 1)
                                        <span class="indicator-item badge badge-primary">Le client a répondu</span>
                                    @endif
                                </h3>
                                <div class="mt-2 max-w-xl text-sm text-gray-500">
                                    <p>{{ $ticket['num_ticket'] }} - {{ Str::limit($ticket['subject_ticket'], 30) }}</p>
                                    <p class="text-purple-500">Projet : {{ $ticket['project_name'] }}</p>
                                    <p>Label : {{ $ticket['label'] }} </p>
                                </div>
                                <div class="mt-3 text-sm/6">
                                    <a href="{{ route('ticket.detail', ['ticket' => $ticket['id']]) }}" wire:navigate
                                        class="font-semibold text-indigo-600 hover:text-indigo-500">
                                        voir plus
                                        <span aria-hidden="true"> &rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    
                    @if($loadingByStatus['en attente'])
                        <div class="flex items-center justify-center py-4">
                            <div class="loading loading-spinner loading-md"></div>
                            <span class="ml-2 text-sm text-gray-500">Chargement...</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- En cours -->
        <div class="flex flex-col">
            <div class="space-y-3">
                <div class="border-l-4 border-amber-400 bg-amber-50 p-4">
                    <div class="flex justify-between items-center">
                        <div class="ml-3">
                            <p class="text-sm text-amber-700">
                                En cours
                            </p>
                        </div>
                        <span class="text-xs text-amber-600 bg-amber-100 px-2 py-1 rounded-full">
                            {{ $this->getTicketCountForStatus('en cours') }}
                        </span>
                    </div>
                </div>

                <div 
                    id="status-en-cours" 
                    class="space-y-3 max-h-[600px] overflow-y-auto"
                    x-data="infiniteScroll('en cours')"
                    x-init="init()"
                    @scroll="handleScroll"
                >
                    @foreach($ticketsByStatus['en cours'] as $ticket)
                        <div class="bg-white shadow-sm sm:rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-base font-semibold text-gray-900">
                                    {{ Str::limit($ticket['subject_ticket'], 50) }}
                                </h3>
                                <div class="mt-2 max-w-xl text-sm text-gray-500">
                                    <p>{{ $ticket['num_ticket'] }} - {{ Str::limit($ticket['subject_ticket'], 30) }}</p>
                                    <p class="text-amber-500">Projet : {{ $ticket['project_name'] }}</p>
                                    <p>Label : {{ $ticket['label'] }} </p>
                                </div>
                                <div class="mt-3 text-sm/6">
                                    <a href="{{ route('ticket.detail', ['ticket' => $ticket['id']]) }}" wire:navigate 
                                        class="font-semibold text-amber-600 hover:text-amber-500">
                                        voir plus
                                        <span aria-hidden="true"> &rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    
                    @if($loadingByStatus['en cours'])
                        <div class="flex items-center justify-center py-4">
                            <div class="loading loading-spinner loading-md"></div>
                            <span class="ml-2 text-sm text-gray-500">Chargement...</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Clôturé -->
        <div class="flex flex-col">
            <div class="space-y-3">
                <div class="border-l-4 border-green-400 bg-green-50 p-4">
                    <div class="flex justify-between items-center">
                        <div class="ml-3">
                            <p class="text-sm text-green-700">
                                Clôturé 
                            </p>
                        </div>
                        <span class="text-xs text-green-600 bg-green-100 px-2 py-1 rounded-full">
                            {{ $this->getTicketCountForStatus('cloture') }}
                        </span>
                    </div>
                </div>

                <div 
                    id="status-cloture" 
                    class="space-y-3 max-h-[600px] overflow-y-auto"
                    x-data="infiniteScroll('cloture')"
                    x-init="init()"
                    @scroll="handleScroll"
                >
                    @foreach($ticketsByStatus['cloture'] as $ticket)
                        <div class="bg-white shadow-sm sm:rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-base font-semibold text-gray-900">
                                    {{ Str::limit($ticket['subject_ticket'], 50) }}
                                </h3>
                                <div class="mt-2 max-w-xl text-sm text-gray-500">
                                    <p>{{ $ticket['num_ticket'] }} - {{ Str::limit($ticket['subject_ticket'], 30) }}</p>
                                    <p class="text-green-500">Projet : {{ $ticket['project_name'] }}</p>
                                    <p>Label : {{ $ticket['label'] }} </p>
                                </div>
                                <div class="mt-3 text-sm/6">
                                    <a href="{{ route('ticket.detail', ['ticket' => $ticket['id']]) }}" wire:navigate
                                        class="font-semibold text-green-600 hover:text-green-500">
                                        voir plus
                                        <span aria-hidden="true"> &rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    
                    @if($loadingByStatus['cloture'])
                        <div class="flex items-center justify-center py-4">
                            <div class="loading loading-spinner loading-md"></div>
                            <span class="ml-2 text-sm text-gray-500">Chargement...</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Détails -->
    <x-modal wire:model="myModal1" title="Détails du Ticket" class="backdrop-blur"
        box-class="bg-gray-200 max-w-7xl h-[90vh]">
        @if($loadingDetails)
            <div class="flex items-center justify-center py-8">
                <div class="loading loading-spinner loading-lg"></div>
                <span class="ml-2">Chargement des détails...</span>
            </div>
        @elseif(!empty($ticketDetails))

            <x-card title="client" class="mt-2">
                <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Deserunt officia, amet harum, sequi natus
                    molestias, aspernatur necessitatibus nulla nam quibusdam magni corporis. Aperiam soluta nam molestiae
                    laboriosam minus. Optio, repudiandae.
            </x-card>

            <x-card title="support">
                Lorem ipsum dolor sit amet, consectetur adipisicing elit. Maxime est obcaecati sunt. Officiis asperiores
                esse voluptas? Assumenda culpa iusto aut? Inventore placeat aliquam est odio magni quos quaerat molestias
                tempora?
            </x-card>
        @endif

        <x-slot:actions>
            <x-button label="Fermer" @click="$wire.closeModal()" class="btn-primary" />
        </x-slot:actions>
    </x-modal>

    <script>
    function infiniteScroll(status) {
        return {
            status: status,
            isLoading: false,
            
            init() {
                // Initialisation si nécessaire
            },
            
            handleScroll(event) {
                const element = event.target;
                const threshold = 100; // Déclencher quand on est à 100px du bas
                
                if (element.scrollTop + element.clientHeight >= element.scrollHeight - threshold) {
                    this.loadMore();
                }
            },
            
            loadMore() {
                if (this.isLoading) return;
                
                this.isLoading = true;
                
                // Appeler la méthode Livewire
                @this.call('loadMore', this.status).then(() => {
                    this.isLoading = false;
                }).catch(() => {
                    this.isLoading = false;
                });
            }
        }
    }
    </script>

    <style>
    /* Styles pour la scrollbar */
    .overflow-y-auto::-webkit-scrollbar {
        width: 6px;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    </style>
</div>