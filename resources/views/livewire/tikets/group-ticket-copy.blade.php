<?php
// resources/views/livewire/tikets/group-ticket.blade.php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {

    public int $ticketId;
    public int $activeId = 0;
    public array $tickets = [];
    public bool $loading  = true;
    public ?string $error = null;

    public array $labels = [
        1  => ['text' => 'Suivi de commande',            'badge' => 'badge-info'],
        2  => ['text' => 'Colis non reçu',               'badge' => 'badge-error'],
        3  => ['text' => 'Paiement',                     'badge' => 'badge-warning'],
        4  => ['text' => 'Facture non reçue',            'badge' => 'badge-warning'],
        5  => ['text' => 'Produit défectueux',           'badge' => 'badge-error'],
        6  => ['text' => 'Retour produit & rétractation','badge' => 'badge-secondary'],
        7  => ['text' => 'Demande spécifique',           'badge' => 'badge-primary'],
        8  => ['text' => 'Colis vide',                   'badge' => 'badge-error'],
        9  => ['text' => 'Spam / publicité',             'badge' => 'badge-ghost'],
        10 => ['text' => 'Changement adresse',           'badge' => 'badge-accent'],
        11 => ['text' => 'Inversion de colis',           'badge' => 'badge-warning'],
    ];

    public function mount(int $ticketId, int $activeId = 0): void
    {
        $this->ticketId = $ticketId;
        $this->activeId = $activeId;
        $this->loadTickets();
    }

    public function loadTickets(): void
    {
        $this->loading = true;
        $this->error   = null;

        try {
            $token = session('token');

            $response = Http::withHeaders([
                'x-secret-key'  => env('X_SECRET_KEY'),
                'Authorization' => "Bearer {$token}",
                'Accept'        => 'application/json',
            ])->post('https://dev-ia.astucom.com/n8n_cosmia/ticket/getTicketSet', [
                'ticket_id' => (string) $this->ticketId,
            ]);

            if ($response->successful()) {
                $this->tickets = $response->json()['tickets'] ?? [];
            } else {
                $this->error = "Erreur lors du chargement des tickets";
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }

        $this->loading = false;
    }
}; ?>

<div class="mb-5">
    {{-- Style gradient border --}}
    <style>
        .card-active {
            position: relative;
            background: linear-gradient(var(--color-base-100), var(--color-base-100)) padding-box,
            linear-gradient(135deg, oklch(var(--p)), oklch(var(--s)), oklch(var(--a))) border-box;
            border: 2px solid transparent !important;
        }
    </style>

    {{-- Chargement --}}
    @if ($loading)
        <div class="flex gap-3 overflow-x-auto pb-3">
            @foreach(range(1, 4) as $i)
                <div class="p-3 flex flex-col bg-base-100 rounded-lg shadow-sm flex-shrink-0 w-56">
                    <div class="flex items-center gap-2 text-base-content/40">
                        <span class="loading loading-spinner loading-xs text-primary"></span>
                        <span class="text-xs">Chargement…</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Erreur --}}
    @elseif ($error)
        <div class="p-3 flex flex-col bg-base-100 rounded-lg shadow-sm border-l-4 border-l-error">
            <div class="flex items-center gap-2 text-error text-xs">
                <x-icon name="o-exclamation-triangle" class="h-4 w-4" />
                {{ $error }}
            </div>
        </div>

        {{-- Aucun résultat --}}
    @elseif (empty($tickets))
        <div class="p-3 flex flex-col bg-base-100 rounded-lg shadow-sm">
            <div class="flex items-center gap-2 text-base-content/50 text-xs">
                <x-icon name="o-inbox" class="h-4 w-4" />
                Aucun ticket trouvé
            </div>
        </div>

        {{-- Résultats --}}
    @else
        <div class="flex gap-3 overflow-x-auto pb-3 snap-x snap-mandatory scroll-smooth">
            @foreach ($tickets as $ticket)
                @php
                    $labelId   = $ticket['label_id'] ?? null;
                    $labelInfo = $labels[$labelId] ?? [
                        'text'  => 'Inconnu',
                        'badge' => 'badge-ghost',
                    ];
                    $commande  = ($ticket['num_commande'] ?? 'inconnu') !== 'inconnu'
                                    ? $ticket['num_commande']
                                    : '—';
                    $isActive  = $ticket['id'] === $activeId;
                @endphp

                <div
                    class="snap-start flex-shrink-0 w-56 p-3 flex flex-col rounded-lg shadow-sm
                           hover:shadow-md transition-all
                           {{ $isActive ? 'card-active' : 'bg-base-100 hover:bg-base-200' }}"
                >
                    {{-- Indicateur actif --}}
                    @if ($isActive)
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-primary flex items-center gap-1">
                                <span class="inline-block w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                Ticket actuel
                            </span>
                        </div>
                    @endif

                    {{-- Titre --}}
                    <h3 class="font-semibold text-sm text-gray-500 flex items-center gap-1.5">
                        <a href="{{ route('ticket.detail', $ticket['id']) }}"
                           wire:navigate
                           class="hover:text-primary hover:underline transition-colors">
                            {{ $ticket['num_ticket'] }}
                        </a>
                    </h3>

                    {{-- Badge label --}}
                    <div class="mt-2">
                        <span class="badge {{ $labelInfo['badge'] }} badge-xs badge-outline">
                            {{ $labelInfo['text'] }}
                        </span>
                    </div>

                    {{-- Infos --}}
                    <div class="mt-2 flex justify-between items-center gap-2">
                        <span class="text-xs text-base-content/50 flex items-center gap-1">
                           № CMD / {{ $commande }}
                        </span>
                        <span class="font-mono text-xs text-base-content/40">
                            #{{ $ticket['id'] }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
