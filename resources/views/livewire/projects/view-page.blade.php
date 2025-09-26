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

    public int|string $projectId = 'all';

    public function mount($id = 'all')
    {
        // si id est "all" → on garde tout
        $this->projectId = $id === 'all' ? 'all' : (int) $id;
        $this->fetchTickets();
    }

    public function fetchTicketsOld()
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

    public function fetchTickets()
    {
        $token = session('token');

        if (!$token) {
            return redirect()->route('login');
        }

        $url = "https://dev-ia.astucom.com/n8n_cosmia/ticket?page={$this->page}";

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

    public function setProject($id)
    {
        $this->projectId = $id;
        $this->page = 1; // reset pagination
        $this->fetchTickets();
    }
   
}; ?>

<div class="max-w-9xl mx-auto px-4">
    <x-header title="Details projet" subtitle="Tous les tickets" separator>
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
        <!-- Open -->
        <div class="flex flex-col">
            <div class="space-y-3">

                <div class="border-l-4 border-purple-400 bg-purple-50 p-4">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-purple-700">
                                En attente
                            </p>
                        </div>
                    </div>
                </div>


                @foreach($tickets as $ticket)
                    @if($ticket['status'] === 'en attente')
                        <div class="bg-white shadow-sm sm:rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-base font-semibold text-gray-900">
                                    {{ Str::limit($ticket['subject_ticket'], 30) }} @if($ticket['need_attention'] == 1)<span class="indicator-item badge badge-primary">Le client a répondu</span>@endif
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
                    @endif
                @endforeach
            </div>
        </div>

        <!-- In Progress -->
        <div class="flex flex-col">
            <div class="space-y-3">

                <div class="border-l-4 border-amber-400 bg-amber-50 p-4">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-amber-700">
                                En cours
                            </p>
                        </div>
                    </div>
                </div>

                @foreach ($tickets as $ticket)
                    @if($ticket['status'] === 'en cours')
                        <div class="bg-white shadow-sm sm:rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-base font-semibold text-gray-900">
                                    {{ Str::limit($ticket['subject_ticket'], 30) }}
                                </h3>
                                <div class="mt-2 max-w-xl text-sm text-gray-500">
                                    <p>{{ $ticket['num_ticket'] }} - {{ Str::limit($ticket['subject_ticket'], 30) }}</p>
                                    <p class="text-amber-500">Projet : {{ $ticket['project_name'] }}</p>
                                    <p>Label : {{ $ticket['label'] }} </p>
                                </div>
                                <div class="mt-3 text-sm/6">
                                    <a href="{{ route('ticket.detail', ['ticket' => $ticket['id']]) }}" wire:navigate class="font-semibold text-amber-600 hover:text-amber-500">
                                        voir plus
                                        <span aria-hidden="true"> &rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Done -->
        <div class="flex flex-col">
            <div class="space-y-3">

                <div class="border-l-4 border-green-400 bg-green-50 p-4">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-green-700">
                                Clôturé 
                            </p>
                        </div>
                    </div>
                </div>

                @foreach($tickets as $ticket)
                    @if($ticket['status'] === 'cloture')
                        <div class="bg-white shadow-sm sm:rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-base font-semibold text-gray-900">
                                    {{ Str::limit($ticket['subject_ticket'], 30) }}
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
                    @endif
                @endforeach

                {{-- @foreach($tickets as $ticket)
                @if($ticket['status'] === 'cloture')
                <div class="bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition cursor-pointer"">
                                            <h3 class=" text-sm font-semibold text-gray-800 mb-1">
                    {{ $ticket['num_ticket'] }} - {{ Str::limit($ticket['subject_ticket'], 30) }}
                    </h3>
                    <p class="text-xs text-gray-500 mb-2">{{ $ticket['label'] }}</p>
                    <div class="flex items-center justify-between text-xs text-gray-400">
                        <span>#{{ $ticket['id'] }}</span>
                        <span>{{ $ticket['nom_client'] }}</span>
                    </div>
                    <a href="{{ route('ticket.detail', ['ticket' => $ticket['id']]) }}">detail</a>
                </div>
                @endif
                @endforeach --}}
            </div>
        </div>
    </div>

    <nav class="flex items-center justify-between border-t border-gray-200 px-4 sm:px-0 mt-6">
        <!-- Bouton Previous -->
        <div class="-mt-px flex w-0 flex-1">
            <button wire:click="prevPage" @if($page <= 1) disabled @endif
                class="inline-flex items-center border-t-2 border-transparent pt-4 pr-1 text-sm font-medium 
                   text-gray-500 hover:border-gray-300 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="mr-3 size-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd"
                        d="M18 10a.75.75 0 0 1-.75.75H4.66l2.1 1.95a.75.75 0 1 1-1.02 1.1l-3.5-3.25a.75.75 0 0 1 0-1.1l3.5-3.25a.75.75 0 1 1 1.02 1.1l-2.1 1.95h12.59A.75.75 0 0 1 18 10Z"
                        clip-rule="evenodd" />
                </svg>
                Previous
            </button>
        </div>

        <!-- Numéros de pages -->
        <div class="hidden md:-mt-px md:flex">
            @php
                $start = max(1, $page - 2);
                $end = min($totalPage, $page + 2);
            @endphp

            {{-- Première page avec ... --}}
            @if($start > 1)
                <button wire:click="setPage(1)" class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium 
                                   text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    1
                </button>
                @if($start > 2)
                    <span
                        class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500">...</span>
                @endif
            @endif

            {{-- Pages dynamiques --}}
            @for($i = $start; $i <= $end; $i++)
                    <button wire:click="setPage({{ $i }})" class="inline-flex items-center px-4 pt-4 text-sm font-medium 
                                               {{ $i === $page
                ? 'border-t-2 border-indigo-500 text-indigo-600'
                : 'border-t-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                        aria-current="{{ $i === $page ? 'page' : '' }}">
                        {{ $i }}
                    </button>
            @endfor

            {{-- Dernière page avec ... --}}
            @if($end < $totalPage)
                @if($end < $totalPage - 1)
                    <span
                        class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500">...</span>
                @endif
                <button wire:click="setPage({{ $totalPage }})" class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium 
                                   text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    {{ $totalPage }}
                </button>
            @endif
        </div>

        <!-- Bouton Next -->
        <div class="-mt-px flex w-0 flex-1 justify-end">
            <button wire:click="nextPage" @if($page >= $totalPage) disabled @endif
                class="inline-flex items-center border-t-2 border-transparent pt-4 pl-1 text-sm font-medium 
                   text-gray-500 hover:border-gray-300 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Next
                <svg class="ml-3 size-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd"
                        d="M2 10a.75.75 0 0 1 .75-.75h12.59l-2.1-1.95a.75.75 0 1 1 1.02-1.1l3.5 3.25a.75.75 0 0 1 0 1.1l-3.5 3.25a.75.75 0 1 1-1.02-1.1l2.1-1.95H2.75A.75.75 0 0 1 2 10Z"
                        clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </nav>


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
</div>