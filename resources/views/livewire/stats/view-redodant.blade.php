<?php 

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public string $clientEmail = '';
    public string $numCommande = '';
    
    public array $ticketsByStatus = [
        'en attente' => [],
        'en cours' => [],
        'cloture' => []
    ];
    
    public array $totalItemByStatus = [
        'en attente' => 0,
        'en cours' => 0,
        'cloture' => 0
    ];
    
    public bool $loading = false;
    
    public function mount()
    {
        $this->clientEmail = request()->get('email', '');
        $this->numCommande = request()->get('commande', '');
        
        if ($this->clientEmail && $this->numCommande) {
            $this->loadTickets();
        }
    }
    
    public function loadTickets(): void
    {
        $this->loading = true;

        try {
            $token = session('token');
            $response = Http::withHeaders([
                'x-secret-key' => env('X_SECRET_KEY'),
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->post('https://dev-ia.astucom.com/n8n_cosmia/ticket/getDetailRedudentTicket', [
                'client_email' => $this->clientEmail,
                'num_commande' => $this->numCommande,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $tickets = $data['details'] ?? [];
                
                // Réinitialiser les tableaux
                $this->ticketsByStatus = [
                    'en attente' => [],
                    'en cours' => [],
                    'cloture' => []
                ];
                
                // Grouper les tickets par statut
                foreach ($tickets as $ticket) {
                    $status = $ticket['status'] ?? 'en attente';
                    if (isset($this->ticketsByStatus[$status])) {
                        $this->ticketsByStatus[$status][] = $ticket;
                    }
                }
                
                // Calculer les totaux
                $this->totalItemByStatus['en attente'] = count($this->ticketsByStatus['en attente']);
                $this->totalItemByStatus['en cours'] = count($this->ticketsByStatus['en cours']);
                $this->totalItemByStatus['cloture'] = count($this->ticketsByStatus['cloture']);
            }
        } catch (\Exception $e) {
            \Log::error('Error loading ticket details: ' . $e->getMessage());
            $this->ticketsByStatus = [
                'en attente' => [],
                'en cours' => [],
                'cloture' => []
            ];
        } finally {
            $this->loading = false;
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
            $this->loadTickets();
            session()->flash('success', 'Ticket déplacé avec succès');
        } else {
            session()->flash('error', 'Erreur lors du déplacement du ticket');
        }
    }

    public function getTicketCountForStatus($status)
    {
        return count($this->ticketsByStatus[$status]);
    }

    public function getTotalTicketForStatus($status)
    {
        return $this->totalItemByStatus[$status];
    }
}; ?>

<div class="min-h-screen w-full bg-gradient-to-br from-gray-50 to-gray-100">
    <x-header title="Détails des Tickets Redondants" separator>
        <x-slot:middle class="!justify-end">
            <x-button 
                label="Retour" 
                icon="o-arrow-left" 
                link="/stat" 
                wire:navigate
                class="btn-ghost"
            />
        </x-slot:middle>
    </x-header>

    <div class="container mx-auto px-4 py-6">
        <!-- Informations du groupe -->
        <x-card class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Email du client</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $clientEmail }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Numéro de commande</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $numCommande }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total de tickets</p>
                    <p class="text-lg font-semibold text-gray-900">
                        {{ array_sum($this->totalItemByStatus) }}
                    </p>
                </div>
            </div>
        </x-card>

        @if($loading)
            <x-card>
                <div class="flex justify-center py-12">
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
                        <p class="mt-4 text-gray-600">Chargement des tickets...</p>
                    </div>
                </div>
            </x-card>
        @else
            <!-- Kanban Board -->
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
                            {{ $this->getTicketCountForStatus('en attente') }}
                        </span>
                    </div>

                    <div 
                        id="status-en-attente" 
                        class="flex-1 p-4 space-y-3 overflow-y-auto max-h-[calc(100vh-320px)]"
                        x-data="kanbanColumn('en attente')"
                        @drop.prevent="handleDrop($event)"
                        @dragover.prevent
                        @dragenter.prevent="$el.classList.add('ring-2', 'ring-purple-300', 'bg-purple-50')"
                        @dragleave.prevent="$el.classList.remove('ring-2', 'ring-purple-300', 'bg-purple-50')"
                    >
                        @forelse($ticketsByStatus['en attente'] as $ticket)
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

                                @if(!empty($ticket['to_do']))
                                    <div class="bg-blue-50 border-l-2 border-blue-400 p-2 rounded text-xs text-blue-800 mb-2">
                                        {{ Str::limit($ticket['to_do'], 100) }}
                                    </div>
                                @endif

                                <div class="space-y-1.5 mb-3">
                                    <div class="flex items-center gap-2 text-xs text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>{{ \Carbon\Carbon::parse($ticket['created_at'])->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                        </svg>
                                        <span>{{ $ticket['label'] ?? 'Sans label' }}</span>
                                    </div>
                                    @if(isset($ticket['ordre']))
                                        <div class="flex items-center gap-2 text-xs text-purple-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                            </svg>
                                            <span>Ticket #{{ $ticket['ordre'] }}</span>
                                        </div>
                                    @endif
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
                        @empty
                            <div class="text-center py-8 text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <p class="mt-2 text-sm">Aucun ticket en attente</p>
                            </div>
                        @endforelse
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
                            {{ $this->getTicketCountForStatus('en cours') }}
                        </span>
                    </div>

                    <div 
                        id="status-en-cours" 
                        class="flex-1 p-4 space-y-3 overflow-y-auto max-h-[calc(100vh-320px)]"
                        x-data="kanbanColumn('en cours')"
                        @drop.prevent="handleDrop($event)"
                        @dragover.prevent
                        @dragenter.prevent="$el.classList.add('ring-2', 'ring-amber-300', 'bg-amber-50')"
                        @dragleave.prevent="$el.classList.remove('ring-2', 'ring-amber-300', 'bg-amber-50')"
                    >
                        @forelse($ticketsByStatus['en cours'] as $ticket)
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

                                @if(!empty($ticket['to_do']))
                                    <div class="bg-blue-50 border-l-2 border-blue-400 p-2 rounded text-xs text-blue-800 mb-2">
                                        {{ Str::limit($ticket['to_do'], 100) }}
                                    </div>
                                @endif

                                <div class="space-y-1.5 mb-3">
                                    <div class="flex items-center gap-2 text-xs text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>{{ \Carbon\Carbon::parse($ticket['created_at'])->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                        </svg>
                                        <span>{{ $ticket['label'] ?? 'Sans label' }}</span>
                                    </div>
                                    @if(isset($ticket['ordre']))
                                        <div class="flex items-center gap-2 text-xs text-amber-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                            </svg>
                                            <span>Ticket #{{ $ticket['ordre'] }}</span>
                                        </div>
                                    @endif
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
                        @empty
                            <div class="text-center py-8 text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <p class="mt-2 text-sm">Aucun ticket en cours</p>
                            </div>
                        @endforelse
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
                            {{ $this->getTicketCountForStatus('cloture') }}
                        </span>
                    </div>

                    <div 
                        id="status-cloture" 
                        class="flex-1 p-4 space-y-3 overflow-y-auto max-h-[calc(100vh-320px)]"
                        x-data="kanbanColumn('cloture')"
                        @drop.prevent="handleDrop($event)"
                        @dragover.prevent
                        @dragenter.prevent="$el.classList.add('ring-2', 'ring-green-300', 'bg-green-50')"
                        @dragleave.prevent="$el.classList.remove('ring-2', 'ring-green-300', 'bg-green-50')"
                    >
                        @forelse($ticketsByStatus['cloture'] as $ticket)
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

                                @if(!empty($ticket['to_do']))
                                    <div class="bg-blue-50 border-l-2 border-blue-400 p-2 rounded text-xs text-blue-800 mb-2">
                                        {{ Str::limit($ticket['to_do'], 100) }}
                                    </div>
                                @endif

                                <div class="space-y-1.5 mb-3">
                                    <div class="flex items-center gap-2 text-xs text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>{{ \Carbon\Carbon::parse($ticket['created_at'])->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                        </svg>
                                        <span>{{ $ticket['label'] ?? 'Sans label' }}</span>
                                    </div>
                                    @if(isset($ticket['ordre']))
                                        <div class="flex items-center gap-2 text-xs text-green-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                            </svg>
                                            <span>Ticket #{{ $ticket['ordre'] }}</span>
                                        </div>
                                    @endif
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
                        @empty
                            <div class="text-center py-8 text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <p class="mt-2 text-sm">Aucun ticket clôturé</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function kanbanColumn(status) {
    return {
        status: status,
        draggedElement: null,
        
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
    .max-h-\[calc\(100vh-320px\)\] {
        max-height: calc(100vh - 400px);
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

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
@endpush