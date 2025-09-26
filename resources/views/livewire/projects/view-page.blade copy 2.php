<?php 

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public bool $myModal1 = false;
    public array $selectedTicket = [];
    public array $ticketDetails = [];
    public array $tickets = [];
    public int $page = 1;
    public int $totalPage = 1;
    public bool $loadingDetails = false;

    public function mount()
    {
        $this->fetchTickets();
    }

    public function fetchTickets()
    {
        // ⚡ On récupère le token de session
        $token = session('token');

        if (!$token) {
            // 👉 Si pas de token, redirection vers login
            return redirect()->route('login');
        }

        $response = Http::withHeaders([
            'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->get("https://dev-ia.astucom.com/n8n_cosmia/ticket?page={$this->page}");

        if ($response->successful()) {
            $data = $response->json();
            $this->tickets = $data['data'] ?? [];
            $this->totalPage = $data['total_page'] ?? 1;
        }
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

    public function setPage(int $page)
    {
        $this->page = $page;
        $this->fetchTickets();
    }

    public function nextPage()
    {
        if ($this->page < $this->totalPage) {
            $this->page++;
            $this->fetchTickets();
        }
    }

    public function prevPage()
    {
        if ($this->page > 1) {
            $this->page--;
            $this->fetchTickets();
        }
    }

    public function openTicket($ticketId)
    {
        $ticket = collect($this->tickets)->firstWhere('id', $ticketId);
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
}; ?>

<div class="max-w-9xl mx-auto px-4">
    <x-header title="Projet - COSMASHOP" subtitle="Tous les tickets" separator>
        <x-slot:middle class="!justify-end">
            <x-input icon="o-bolt" placeholder="Search..." />
        </x-slot:middle>
        <x-slot:actions>
        </x-slot:actions>
    </x-header>

    <x-card class="p-2">
        <ul class="flex flex-wrap justify-center text-sm font-medium text-center text-gray-500 dark:text-gray-400 gap-x-2">
            <li>
                <a href="#" class="inline-block px-4 py-2 rounded-lg hover:text-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 dark:hover:text-white">
                    Tous
                </a>
            </li>
            <li>
                <a href="#" class="inline-block px-4 py-2 text-white bg-blue-600 rounded-lg active" aria-current="page">
                    Suivi colis
                </a>
            </li>
            <li>
                <a href="#" class="inline-block px-4 py-2 rounded-lg hover:text-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 dark:hover:text-white">
                    Probleme colis
                </a>
            </li>
            <li>
                <a href="#" class="inline-block px-4 py-2 rounded-lg hover:text-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 dark:hover:text-white">
                    Probleme de paiement
                </a>
            </li>
            <li>
                <a href="#" class="inline-block px-4 py-2 rounded-lg hover:text-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 dark:hover:text-white">
                    Retous/Retractation
                </a>
            </li>
            <li>
                <a class="inline-block px-4 py-2 text-gray-400 cursor-not-allowed dark:text-gray-500">
                    Produits defectueux
                </a>
            </li>
        </ul>
    </x-card>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
        <!-- Open -->
        <div class="bg-gray-50 rounded-xl shadow-sm flex flex-col">
            <div class="flex items-center justify-between p-4 border-b">
                <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <span class="text-blue-500">⬤</span> Open
                </h2>
                <span class="text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded-full">
                    {{ collect($tickets)->where('status', 'en attente')->count() }}
                </span>
            </div>
            <div class="p-3 space-y-3">
                @foreach($tickets as $ticket)
                    @if($ticket['status'] === 'en attente')
                        <div class="bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition cursor-pointer"
                             wire:click="openTicket({{ $ticket['id'] }})">
                            <h3 class="text-sm font-semibold text-gray-800 mb-1">
                                {{ $ticket['num_ticket'] }} - {{ Str::limit($ticket['subject_ticket'], 30) }}
                            </h3>
                            <p class="text-xs text-gray-500 mb-2">{{ $ticket['label'] }}</p>
                            <div class="flex items-center justify-between text-xs text-gray-400">
                                <span>#{{ $ticket['id'] }}</span>
                                <span>{{ $ticket['nom_client'] }}</span>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- In Progress -->
        <div class="bg-gray-50 rounded-xl shadow-sm flex flex-col">
            <div class="flex items-center justify-between p-4 border-b">
                <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <span class="text-yellow-500">⬤</span> In Progress
                </h2>
                <span class="text-xs bg-yellow-100 text-yellow-600 px-2 py-1 rounded-full">
                    {{ collect($tickets)->where('status', 'en cours')->count() }}
                </span>
            </div>
            <div class="p-3 space-y-3">
                @foreach($tickets as $ticket)
                    @if($ticket['status'] === 'en cours')
                        <div class="bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition cursor-pointer"
                             wire:click="openTicket({{ $ticket['id'] }})">
                            <h3 class="text-sm font-semibold text-gray-800 mb-1">
                                {{ $ticket['num_ticket'] }} - {{ Str::limit($ticket['subject_ticket'], 30) }}
                            </h3>
                            <p class="text-xs text-gray-500 mb-2">{{ $ticket['label'] }}</p>
                            <div class="flex items-center justify-between text-xs text-gray-400">
                                <span>#{{ $ticket['id'] }}</span>
                                <span>{{ $ticket['nom_client'] }}</span>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Done -->
        <div class="bg-gray-50 rounded-xl shadow-sm flex flex-col">
            <div class="flex items-center justify-between p-4 border-b">
                <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <span class="text-green-500">⬤</span> Done
                </h2>
                <span class="text-xs bg-green-100 text-green-600 px-2 py-1 rounded-full">
                    {{ collect($tickets)->where('status', 'cloture')->count() }}
                </span>
            </div>
            <div class="p-3 space-y-3">
                @foreach($tickets as $ticket)
                    @if($ticket['status'] === 'cloture')
                        <div class="bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition cursor-pointer"
                             wire:click="openTicket({{ $ticket['id'] }})">
                            <h3 class="text-sm font-semibold text-gray-800 mb-1">
                                {{ $ticket['num_ticket'] }} - {{ Str::limit($ticket['subject_ticket'], 30) }}
                            </h3>
                            <p class="text-xs text-gray-500 mb-2">{{ $ticket['label'] }}</p>
                            <div class="flex items-center justify-between text-xs text-gray-400">
                                <span>#{{ $ticket['id'] }}</span>
                                <span>{{ $ticket['nom_client'] }}</span>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="flex justify-center mt-6">
        <div class="join">
            <button 
                class="join-item btn" 
                wire:click="prevPage" 
                @if($page <= 1) disabled @endif>
                ⬅
            </button>

            @php
                $start = max(1, $page - 2);
                $end = min($totalPage, $page + 2);
            @endphp

            @if($start > 1)
                <button class="join-item btn" wire:click="setPage(1)">1</button>
                @if($start > 2)
                    <button class="join-item btn btn-disabled">...</button>
                @endif
            @endif

            @for($i = $start; $i <= $end; $i++)
                <button 
                    class="join-item btn @if($i === $page) btn-active @endif" 
                    wire:click="setPage({{ $i }})">
                    {{ $i }}
                </button>
            @endfor

            @if($end < $totalPage)
                @if($end < $totalPage - 1)
                    <button class="join-item btn btn-disabled">...</button>
                @endif
                <button class="join-item btn" wire:click="setPage({{ $totalPage }})">{{ $totalPage }}</button>
            @endif

            <button 
                class="join-item btn" 
                wire:click="nextPage" 
                @if($page >= $totalPage) disabled @endif>
                ➡
            </button>
        </div>
    </div>

    <!-- Modal Détails -->
    <x-modal wire:model="myModal1" title="Détails du Ticket" class="backdrop-blur" box-class="bg-white max-w-7xl h-[90vh]">
        @if($loadingDetails)
            <div class="flex items-center justify-center py-8">
                <div class="loading loading-spinner loading-lg"></div>
                <span class="ml-2">Chargement des détails...</span>
            </div>
        @elseif(!empty($ticketDetails))
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-full">
                <!-- Colonne gauche - Infos ticket -->
                <div class="lg:col-span-1 space-y-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Informations du Ticket</h3>
                        @if(!empty($ticketDetails['details']) && count($ticketDetails['details']) > 0)
                            @php $detail = $ticketDetails['details'][0]; @endphp
                            <div class="space-y-2 text-sm">
                                <div><span class="font-medium">Numéro:</span> {{ $detail['num_ticket'] ?? 'N/A' }}</div>
                                <div><span class="font-medium">Sujet:</span> {{ Str::limit($detail['subject_ticket'] ?? 'N/A', 50) }}</div>
                                <div><span class="font-medium">Client:</span> {{ $detail['nom_client'] ?? 'N/A' }}</div>
                                <div><span class="font-medium">Commande:</span> {{ $detail['num_commande'] ?? 'N/A' }}</div>
                                <div><span class="font-medium">Email client:</span> {{ $detail['original_client_mail'] ?? 'N/A' }}</div>
                                <div><span class="font-medium">Status:</span> 
                                    <span class="px-2 py-1 rounded-full text-xs 
                                        @if($detail['status'] === 'en attente') bg-blue-100 text-blue-800
                                        @elseif($detail['status'] === 'en cours') bg-yellow-100 text-yellow-800
                                        @else bg-green-100 text-green-800 @endif">
                                        {{ ucfirst($detail['status'] ?? 'N/A') }}
                                    </span>
                                </div>
                                <div><span class="font-medium">Créé le:</span> {{ isset($detail['created_at']) ? \Carbon\Carbon::parse($detail['created_at'])->format('d/m/Y H:i') : 'N/A' }}</div>
                            </div>
                        @endif
                    </div>

                    <!-- To-Do Section -->
                    @if(!empty($ticketDetails['details'][0]['to_do']))
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-800 mb-3">Procédures à suivre</h3>
                            <div class="text-xs text-gray-700 max-h-64 overflow-y-auto whitespace-pre-line">
                                {{ $ticketDetails['details'][0]['to_do'] }}
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Colonne droite - Conversation -->
                <div class="lg:col-span-2 flex flex-col">
                    <h3 class="font-semibold text-gray-800 mb-4">Conversation Email</h3>
                    <div class="flex-1 overflow-y-auto bg-gray-50 rounded-lg p-4">
                        @if(!empty($ticketDetails['conversation']['messages']))
                            @foreach(array_reverse($ticketDetails['conversation']['messages']) as $index => $message)
                                @php
                                    // Détermine si c'est un message du client ou du support
                                    $isFromClient = strpos(strtolower($message['from'] ?? ''), 'dev.test@cosmashop.com') === false;
                                    $chatClass = $isFromClient ? 'chat-start' : 'chat-end';
                                    $senderName = $isFromClient ? 'Client' : 'Support';
                                    $avatarUrl = $isFromClient 
                                        ? 'https://img.daisyui.com/images/profile/demo/anakeen@192.webp' 
                                        : 'https://img.daisyui.com/images/profile/demo/kenobee@192.webp';
                                @endphp
                                
                                <div class="chat {{ $chatClass }}">
                                    <div class="chat-image avatar">
                                        <div class="w-10 rounded-full">
                                            <img alt="{{ $senderName }}" src="{{ $avatarUrl }}" />
                                        </div>
                                    </div>
                                    <div class="chat-header">
                                        {{ $senderName }}
                                        <time class="text-xs opacity-50 ml-1">
                                            {{ isset($message['date']) ? \Carbon\Carbon::parse($message['date'])->format('d/m H:i') : 'N/A' }}
                                        </time>
                                    </div>
                                    <div class="chat-bubble {{ $isFromClient ? 'chat-bubble-primary' : 'chat-bubble-secondary' }} max-w-lg">
                                        @if(!empty($message['subject']) && $message['subject'] !== 'Sans objet')
                                            <div class="font-semibold text-sm mb-2 opacity-90">
                                                {{ $message['subject'] }}
                                            </div>
                                        @endif
                                        <div class="whitespace-pre-line text-sm">
                                            {{ Str::limit($message['message'] ?? 'Pas de contenu', 500) }}
                                        </div>
                                    </div>
                                    <div class="chat-footer opacity-50 text-xs">
                                        {{ $message['from'] ?? 'Expéditeur inconnu' }}
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center text-gray-500 py-8">
                                <div class="chat chat-start">
                                    <div class="chat-image avatar">
                                        <div class="w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                            <span class="text-gray-500">?</span>
                                        </div>
                                    </div>
                                    <div class="chat-bubble chat-bubble-warning">
                                        Aucun message dans cette conversation
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <x-slot:actions>
            <x-button label="Fermer" @click="$wire.closeModal()" class="btn-primary" />
        </x-slot:actions>
    </x-modal>
</div>