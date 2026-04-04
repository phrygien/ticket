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
                $fetched = $response->json()['tickets'] ?? [];

                // Masque le groupe si le seul ticket retourné est le ticket actif lui-même
                if (count($fetched) === 1 && ($fetched[0]['id'] ?? null) === $this->activeId) {
                    $fetched = [];
                }

                $this->tickets = $fetched;
            } else {
                $this->error = "Erreur lors du chargement des tickets";
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }

        $this->loading = false;
    }
}; ?>

{{-- ↓ py-3 px-2 : padding externe pour ne pas coller aux composants voisins --}}
<div class="w-full py-3 px-2">

    <style>
        .card-active {
            position: relative;
            background: linear-gradient(var(--color-base-100), var(--color-base-100)) padding-box,
            linear-gradient(135deg, oklch(var(--p)), oklch(var(--s)), oklch(var(--a))) border-box;
            border: 2px solid transparent !important;
        }

        #ticket-scroll::-webkit-scrollbar {
            display: none;
        }
        #ticket-scroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .scroll-arrow {
            transition: opacity .2s ease, transform .15s ease;
        }
        .scroll-arrow:hover {
            transform: translateY(-50%) scale(1.1);
        }
        .scroll-arrow.hidden-arrow {
            opacity: 0 !important;
            pointer-events: none;
        }
    </style>

    {{-- Chargement --}}
    @if ($loading)
        <div class="flex gap-4 overflow-x-auto pb-3">
            @foreach(range(1, 4) as $i)
                <div class="p-4 flex flex-col bg-base-100 rounded-lg shadow-sm flex-shrink-0 w-56">
                    <div class="flex items-center gap-2 text-base-content/40">
                        <span class="loading loading-spinner loading-xs text-primary"></span>
                        <span class="text-xs">Chargement…</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Erreur --}}
    @elseif ($error)
        <div class="p-4 flex flex-col bg-base-100 rounded-lg shadow-sm border-l-4 border-l-error">
            <div class="flex items-center gap-2 text-error text-xs">
                <x-icon name="o-exclamation-triangle" class="h-4 w-4" />
                {{ $error }}
            </div>
        </div>

        {{-- Aucun résultat --}}
    @elseif (empty($tickets))
        <div class="p-4 flex flex-col bg-base-100 rounded-lg shadow-sm">
            <div class="flex items-center gap-2 text-base-content/50 text-xs">
                <x-icon name="o-inbox" class="h-4 w-4" />
                Aucun ticket associé trouvé
            </div>
        </div>

        {{-- Résultats --}}
    @else
        <div class="relative">

            {{-- Flèche gauche --}}
            <button
                id="scroll-btn-left"
                aria-label="Défiler à gauche"
                class="scroll-arrow hidden-arrow absolute top-1/2 -translate-y-1/2 z-10 bg-base-100/80 backdrop-blur-sm rounded-full p-0 border-0 cursor-pointer"
                style="opacity:0; left: -32px;"
            >
                <svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="grad-arrow-left" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stop-color="#B4B2A9"/>
                            <stop offset="100%" stop-color="#7F77DD"/>
                        </linearGradient>
                    </defs>
                    <circle cx="17" cy="17" r="15.25" stroke="url(#grad-arrow-left)" stroke-width="1.5" opacity="0.85"/>
                    <path d="M20 17H14M14 17L17 13M14 17L17 21"
                          stroke="url(#grad-arrow-left)"
                          stroke-width="1.8"
                          stroke-linecap="round"
                          stroke-linejoin="round"/>
                </svg>
            </button>

            {{-- Flèche droite --}}
            <button
                id="scroll-btn-right"
                aria-label="Défiler à droite"
                class="scroll-arrow absolute top-1/2 -translate-y-1/2 z-10 bg-base-100/80 backdrop-blur-sm rounded-full p-0 border-0 cursor-pointer"
                style="right: -32px;"
            >
                <svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="grad-arrow-right" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stop-color="#7F77DD"/>
                            <stop offset="100%" stop-color="#B4B2A9"/>
                        </linearGradient>
                    </defs>
                    <circle cx="17" cy="17" r="15.25" stroke="url(#grad-arrow-right)" stroke-width="1.5" opacity="0.85"/>
                    <path d="M14 17H20M20 17L17 13M20 17L17 21"
                          stroke="url(#grad-arrow-right)"
                          stroke-width="1.8"
                          stroke-linecap="round"
                          stroke-linejoin="round"/>
                </svg>
            </button>

            {{-- Liste des tickets --}}
            {{-- ↓ gap-4 : plus d'espace entre les cartes / px-12 : place pour les flèches --}}
            <div
                id="ticket-scroll"
                class="flex gap-4 overflow-x-auto snap-x snap-mandatory scroll-smooth px-12 py-2"
            >
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

                    {{-- ↓ p-4 : plus de padding interne dans chaque carte --}}
                    <div
                        class="snap-start flex-shrink-0 w-56 p-4 flex flex-col rounded-lg shadow-sm
                               hover:shadow-md transition-all
                               {{ $isActive ? 'card-active' : 'bg-base-100 hover:bg-base-200' }}"
                    >
                        {{-- Indicateur actif --}}
                        @if ($isActive)
                            <div class="flex items-center justify-between mb-2">
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
                        <div class="mt-3 flex justify-between items-center gap-2">
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

        </div>
    @endif

</div>

@script
<script>
    (function () {
        const scroller  = document.getElementById('ticket-scroll');
        const btnLeft   = document.getElementById('scroll-btn-left');
        const btnRight  = document.getElementById('scroll-btn-right');

        if (!scroller || !btnLeft || !btnRight) return;

        function updateArrows() {
            const atStart = scroller.scrollLeft <= 4;
            const atEnd   = scroller.scrollLeft + scroller.clientWidth >= scroller.scrollWidth - 4;

            btnLeft.classList.toggle('hidden-arrow', atStart);
            btnLeft.style.opacity = atStart ? '0' : '1';

            btnRight.classList.toggle('hidden-arrow', atEnd);
            btnRight.style.opacity = atEnd ? '0' : '1';
        }

        btnLeft.addEventListener('click', () => {
            scroller.scrollBy({ left: -240, behavior: 'smooth' });
        });

        btnRight.addEventListener('click', () => {
            scroller.scrollBy({ left: 240, behavior: 'smooth' });
        });

        scroller.addEventListener('scroll', updateArrows, { passive: true });

        updateArrows();
    })();
</script>
@endscript
