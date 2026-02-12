<?php

use Carbon\Carbon;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {

    public $projects = [];
    public $chartData = [];
    public string $ticketStatus = 'all';
    public $daterange = 0;

    // Donut chart properties
    public array $donutData = [];
    public bool $loadingDonut = false;
    public string $selectedMonth;
    public string $selectedYear;
    public array $months = [];
    public array $years = [];
    public $ticketPartitionData = [];
    public bool $loadingPartition = false;
    public string $role;

    public bool $myModal1 = false;

    public string $project_id = "";
    public $projet;


    public function mount(): void
    {
        $this->selectedMonth = date('m');
        $this->selectedYear = date('Y');

        $this->initializeFilters();
        $this->allProject();
        $this->loadTicketSummary();
        $this->loadDonutData();
        $this->loadTicketPartition();
        $this->loadUserActivitySummary();
        $this->loadRedundantData();

        $this->role = session('role');
    }

    public function initializeFilters(): void
    {
        // Mois
        $this->months = [
            ['id' => 'all', 'name' => 'Tous les mois'],
            ['id' => '01', 'name' => 'Janvier'],
            ['id' => '02', 'name' => 'Février'],
            ['id' => '03', 'name' => 'Mars'],
            ['id' => '04', 'name' => 'Avril'],
            ['id' => '05', 'name' => 'Mai'],
            ['id' => '06', 'name' => 'Juin'],
            ['id' => '07', 'name' => 'Juillet'],
            ['id' => '08', 'name' => 'Août'],
            ['id' => '09', 'name' => 'Septembre'],
            ['id' => '10', 'name' => 'Octobre'],
            ['id' => '11', 'name' => 'Novembre'],
            ['id' => '12', 'name' => 'Décembre'],
        ];

        // Années
        $currentYear = date('Y');
        $this->years = [
            ['id' => 'all', 'name' => 'Toutes les années']
        ];
        for ($i = 0; $i < 5; $i++) {
            $year = $currentYear - $i;
            $this->years[] = ['id' => (string) $year, 'name' => (string) $year];
        }
    }

    public function loadDonutData(): void
    {
        $this->loadingDonut = true;

        try {
            $token = session('token') ?: $this->loginAndGetToken();

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->post('https://dev-ia.astucom.com/n8n_cosmia/dash/getdonutSummary', [
                            'month' => $this->selectedMonth,
                            'year' => $this->selectedYear,
                            'project_id' => $this->projet,
                        ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $this->donutData = $data['details'] ?? [];

                    $this->dispatch('donut-chart-updated', [
                        'data' => $this->donutData,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error loading donut data: ' . $e->getMessage());
            $this->donutData = [];
        } finally {
            $this->loadingDonut = false;
        }
    }

    public function updatedSelectedMonth(): void
    {
        $this->loadDonutData();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadDonutData();
    }

    public function getTotalRequests(): int
    {
        return array_sum(array_column($this->donutData, 'nb'));
    }

    public function updatedTicketStatus()
    {
        $this->refreshChart();
    }

    public function updatedDaterange()
    {
        if (!is_numeric($this->daterange) || $this->daterange < 1) {
            $this->daterange = 0;
        }
        $this->refreshChart();
    }

    public function updatedProjectId(): void
    {
        //dd($this->project_id);
        //$this->refreshChart();
        $this->loadTicketPartition();
        //$this->loadDonutData();
        //$this->loadTicketSummary();
        //$this->loadUserActivitySummary(); // Ajout du rafraîchissement
    }

    public function updatedProjet(): void
    {
        $this->loadDonutData();
    }

    private function refreshChart()
    {
        $this->loadTicketSummary();
        $this->loadDonutData();
        $this->dispatch('chart-updated', [
            'labels' => $this->chartData['labels'] ?? [],
            'values' => $this->chartData['values'] ?? [],
            'status' => $this->ticketStatus,
            'days' => $this->daterange
        ]);
    }

    public function allProject()
    {
        try {
            $token = session('token');

            if (!$token) {
                $token = $this->loginAndGetToken();
            }

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->get(env('API_REST') . '/project');

                if ($response->successful()) {
                    $this->projects = $response->json();
                }
            }
        } catch (\Throwable $th) {
            $this->projects = [];
        }
    }

    public function loadTicketSummary()
    {
        try {
            $token = session('token') ?: $this->loginAndGetToken();

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->post(env('API_REST') . '/dash/getticketsummary', [
                            "ticket_status" => $this->ticketStatus === 'all' ? null : $this->ticketStatus,
                            "date_range" => (int) $this->daterange,
                        ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (!empty($data['details'])) {
                        $this->chartData = [
                            'labels' => collect($data['details'])->pluck('period')->toArray(),
                            'values' => collect($data['details'])->pluck('nombre')->toArray(),
                        ];
                    } else {
                        $this->chartData = ['labels' => [], 'values' => []];
                    }
                } else {
                    \Log::warning('API Error: ' . $response->status() . ' - ' . $response->body());
                    $this->chartData = ['labels' => [], 'values' => []];
                }
            }
        } catch (\Throwable $th) {
            \Log::error('Exception in loadTicketSummary: ' . $th->getMessage());
            $this->chartData = ['labels' => [], 'values' => []];
        }
    }

    public function loadTicketPartition(): void
    {
        $this->loadingPartition = true;

        try {
            $token = session('token') ?: $this->loginAndGetToken();

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->post('https://dev-ia.astucom.com/n8n_cosmia/dash/getTicketPartitionSummary', [
                            'month' => 'all',
                            'year' => 'all',
                            'project_id' => $this->project_id,
                        ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $rawData = $data['details'] ?? [];

                    $this->ticketPartitionData = $rawData;

                    $this->dispatch('ticket-partition-updated', [
                        'data' => $rawData
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error loading ticket partition: ' . $e->getMessage());
            $this->ticketPartitionData = [];
        } finally {
            $this->loadingPartition = false;
        }
    }

    public function getPartitionTotal(): int
    {
        $total = 0;
        foreach ($this->ticketPartitionData as $row) {
            foreach ($row as $key => $value) {
                if ($key !== 'date' && is_numeric($value)) {
                    $total += (int) $value;
                }
            }
        }
        return $total;
    }


    public $userActivityData = [];
    public bool $loadingUserActivity = false;

    public function loadUserActivitySummary(): void
    {
        $this->loadingUserActivity = true;

        try {
            $token = session('token') ?: $this->loginAndGetToken();

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->post('https://dev-ia.astucom.com/n8n_cosmia/dash/getuseractivitysummary', [
                            'month' => now()->format('m'),
                            'year' => now()->format('Y'),
                        ]);

                $apiData = $response->successful() ? $response->json()['details'] ?? [] : [];

                // Génération de tous les jours du mois en cours
                $start = Carbon::now()->startOfMonth();
                $today = Carbon::now()->startOfDay();
                $days = [];

                for ($date = $start->copy(); $date <= $today; $date->addDay()) {
                    // Exclure samedi (6) et dimanche (0)
                    if (in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                        continue;
                    }

                    $days[$date->format('Y-m-d')] = ['date' => $date->format('Y-m-d')];
                }

                // Fusion avec les données API
                foreach ($apiData as $row) {
                    $date = Carbon::parse($row['date'])->format('Y-m-d');
                    if (isset($days[$date])) {
                        $days[$date] = array_merge($days[$date], $row);
                    }
                }

                // Normalisation (remplir les valeurs manquantes à 0)
                $allKeys = collect($apiData)->flatMap(fn($r) => array_keys($r))->unique()->toArray();

                $this->userActivityData = collect($days)
                    ->map(function ($day) use ($allKeys) {
                        foreach ($allKeys as $key) {
                            if (!isset($day[$key])) {
                                $day[$key] = $key === 'date' ? $day['date'] : 0;
                            }
                        }
                        return $day;
                    })
                    ->values()
                    ->toArray();
            }
        } catch (\Exception $e) {
            \Log::error('Erreur chargement activité par personne : ' . $e->getMessage());
            $this->userActivityData = [];
        } finally {
            $this->loadingUserActivity = false;
        }
    }
    public function with(): array
    {
        return [
            'page_title' => 'Tous les projets',
            'totalRequests' => $this->getTotalRequests(),
            'partitionTotal' => $this->getPartitionTotal(),
        ];
    }

    public function resetFilter()
    {
        $this->project_id = '';
    }

    public $redundantCount = 0;
    public $totalEmails = 0;

    public function loadRedundantData(): void
    {
        $this->loadingRedundant = true;

        try {
            $token = session('token') ?: $this->loginAndGetToken();

            if ($token) {
                $response = Http::withHeaders([
                    'x-secret-key' => env('X_SECRET_KEY'),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->post('https://dev-ia.astucom.com/n8n_cosmia/dash/getRedudantRequest', [
                            'ticket_status' => 'all',
                            'date_range' => 1,
                        ]);

                if ($response->successful()) {
                    $data = $response->json();

                    // Récupérer les valeurs depuis details[0]
                    $details = $data['details'][0] ?? [];
                    $this->redundantCount = $details['nb_reccurent'] ?? 0;
                    $this->totalEmails = $details['total_mail_trigered'] ?? 0;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error loading redundant data: ' . $e->getMessage());
            $this->redundantCount = 0;
            $this->totalEmails = 0;
        } finally {
            $this->loadingRedundant = false;
        }
    }

};
?>

<div class="mx-auto max-w-9xl">
    <x-header title="Projet" subtitle="Nos projets sur N8N" separator />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($projects as $project)
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <h2 class="sr-only" id="profile-overview-title">Profile Overview</h2>
                    <div class="bg-white p-6">
                        <div class="sm:flex sm:items-center sm:justify-between">
                            <div class="sm:flex sm:space-x-5">
                                <div class="mt-4 text-center sm:mt-0 sm:pt-1 sm:text-left">
                                    <p class="text-sm font-medium text-gray-600">{{ $project['name'] }}</p>
                                    <p class="text-xl font-bold text-gray-900 sm:text-2xl">{{ $project['code'] }}</p>
                                </div>
                            </div>
                            <div class="mt-5 flex justify-center sm:mt-0">
                                <x-button icon-right="o-arrow-long-right"
                                    href="{{ route('project.view', ['id' => $project['id']]) }}" wire:navigate
                                    label="en savoir plus" class="btn-primary btn-outline" />
                            </div>
                        </div>
                    </div>
                    <div
                        class="grid grid-cols-1 divide-y divide-gray-200 border-t border-gray-200 bg-gray-50 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div class="px-6 py-5 text-center text-sm font-medium">
                            <span class="text-gray-900">{{ $project['pending_ticket'] }}</span>
                            <span class="text-gray-600">ticket en attente</span>
                        </div>
                        <div class="px-6 py-5 text-center text-sm font-medium">
                            <span class="text-gray-900">{{ $project['in_progress_ticket'] }}</span>
                            <span class="text-gray-600">ticket en cours</span>
                        </div>
                        <div class="px-6 py-5 text-center text-sm font-medium">
                            <span class="text-gray-900">{{ $project['closed_ticket'] }}</span>
                            <span class="text-gray-600">ticket fermé</span>
                        </div>
                    </div>
                </div>
            @endforeach
    </div>

    <div class="mt-3 grid grid-cols-1">
        <x-card subtitle="Statistiques par période" separator>
            <div class="mb-5 flex items-end gap-6">

                <fieldset class="fieldset flex-1">
                    <legend class="fieldset-legend">Filtrer par statut</legend>
                    <select class="select w-full" wire:model.live="ticketStatus">
                        <option value="all">Tous les statuts</option>
                        <option value="en attente">En attente</option>
                        <option value="en cours">En cours</option>
                        <option value="cloture">Clôturé</option>
                    </select>
                    <span class="label">Optionnel</span>
                </fieldset>


                <fieldset class="fieldset flex-1">
                    <legend class="fieldset-legend">Période (jours)</legend>
                    <select class="select w-full" wire:model.live.debounce.500ms="daterange">
                        <option value="0">7 Jours</option>
                        <option value="1">15 Jours</option>
                        <option value="2">1 Mois</option>
                        <option value="3">3 Mois</option>
                        <option value="4">6 Mois</option>
                        <option value="5">1 Ans</option>
                    </select>
                    <span class="label">Optionnel</span>
                </fieldset>

            </div>

            <div wire:loading wire:target="ticketStatus,daterange" class="flex items-center justify-center py-4">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                <span class="ml-2 text-sm text-gray-500">Mise à jour en cours...</span>
            </div>

            <div wire:ignore>
                <div id="ticketLineChart" style="height: 300px;"></div>
            </div>

            <div class="mt-4 text-xs text-gray-500 border-t pt-3">
                <p>
                    Filtres actifs:
                    <span
                        class="font-medium">{{ $ticketStatus === 'all' ? 'Tous les statuts' : ucfirst($ticketStatus) }}</span>
                    •
                    @php
$labels = [
    0 => '7 jours',
    1 => '15 jours',
    2 => '1 mois',
    3 => '3 mois',
    4 => '6 mois',
    5 => '1 an',
];
                    @endphp
                    <span class="font-medium">
                        {{ $labels[$daterange] ?? '' }}
                    </span>

                </p>
            </div>
        </x-card>
    </div>



    <!-- Stats Mail avec Meme Probleme !-->
    {{-- <div class="mt-5 grid grid-cols-2 gap-6">
        <x-stat 
            title="Nombre d'envois récurrents" 
            value="{{ number_format($this->redundantCount ?? 0) }}" 
            icon="o-envelope" 
            color="text-primary" 
        />

        <x-stat 
            title="Total des mails envoyés automatiquement" 
            value="{{ number_format($this->totalEmails ?? 0) }}" 
            icon="o-envelope" 
            color="text-pink-500"
        />
    </div> --}}

    <div class="mt-5 grid grid-cols-1">
        <x-card subtitle="Classification par catégorie" separator>
            <form class="filter flex flex-wrap items-center gap-2">
                <input class="btn btn-square" type="reset" value="×" wire:click="resetFilter()" />
            
                <input value="all" wire:model.live="project_id" class="btn" type="radio" name="project_filter"
                    aria-label="Tous les projets" checked />
            
                @foreach($projects as $project)
                    <input value="{{ $project['id'] }}" wire:model.live="project_id" class="btn" type="radio" name="project_filter"
                        aria-label="{{ $project['name'] }}" />
                @endforeach
            
                <!-- Loader pendant le changement de projet -->
                <div wire:loading wire:target="project_id" class="flex items-center ml-3">
                    <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div>
                </div>
            </form>
            <x-button class="btn btn-soft btn-accent float-right" label="Mode Tableau" @click="$wire.myModal1 = true" />
 

            {{-- <div wire:loading wire:target="project_id" class="flex justify-center items-center h-96">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                <span class="ml-3 text-gray-500">Chargement...</span>
            </div> --}}

            <div wire:ignore wire:loading.remove wire:target="project_id" class="w-full">
                <div id="ticketPartitionChart" class="w-full h-[400px]"></div>
            </div>


            @if(!empty($ticketPartitionData))
                <div class="mt-4 text-xs text-gray-500 border-t pt-3">
                    <p class="font-medium">
                        Total: {{ $partitionTotal }} tickets classifiés
                    </p>
                </div>
            @endif
        </x-card>
    </div>


    <x-modal wire:model="myModal1" title="Classification par catégorie - Mode Tableau" class="backdrop-blur" box-class="max-w-7xl">
        @if(empty($ticketPartitionData))
            <p class="text-center text-gray-500 py-6">Aucune donnée disponible.</p>
        @else
            @php
    // Extraire les dates
    $dates = array_column($ticketPartitionData, 'date');

    // Extraire les catégories (exclure 'date')
    $categories = array_keys(array_diff_key($ticketPartitionData[0], ['date' => '']));
            @endphp

            <div class="overflow-x-auto landscape-scrollbar">
                <table class="min-w-full border border-gray-200 rounded-lg text-sm shadow-sm">
                    <thead class="bg-gray-50 text-gray-700 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left border-b border-gray-300 bg-gray-100 sticky left-0 z-20 font-semibold">
                                Date
                            </th>
                            @foreach($categories as $category)
                                <th class="px-4 py-3 text-center border-b border-gray-300 whitespace-nowrap font-semibold">
                                    {{ ucfirst(str_replace('_', ' ', $category)) }}
                                </th>
                            @endforeach
                            <th class="px-4 py-3 text-center border-b border-gray-300 bg-gray-100 font-semibold">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($ticketPartitionData as $row)
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-4 py-3 border-b border-gray-100 bg-white sticky left-0 z-10 font-medium text-gray-800 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($row['date'])->translatedFormat('d M Y') }}
                                </td>
                                @php
        $rowTotal = 0;
                                @endphp
                                @foreach($categories as $category)
                                    @php
            $value = (int) ($row[$category] ?? 0);
            $rowTotal += $value;
                                    @endphp
                                    <td class="px-4 py-3 border-b border-gray-100 text-gray-700 text-center">
                                        <span class="inline-block min-w-[40px] px-2 py-1 rounded {{ $value > 0 ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-400' }}">
                                            {{ $value }}
                                        </span>
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 border-b border-gray-100 bg-gray-50 text-center font-bold text-gray-900">
                                    {{ $rowTotal }}
                                </td>
                            </tr>
                        @endforeach
                        
                        {{-- Ligne de totaux --}}
                        <tr class="bg-gray-100 font-bold">
                            <td class="px-4 py-3 border-t-2 border-gray-300 bg-gray-100 sticky left-0 z-10">
                                Total
                            </td>
                            @php
    $grandTotal = 0;
                            @endphp
                            @foreach($categories as $category)
                                @php
        $categoryTotal = array_sum(array_column($ticketPartitionData, $category));
        $grandTotal += $categoryTotal;
                                                        @endphp
                                                        <td class="px-4 py-3 border-t-2 border-gray-300 text-center text-blue-700">
                                                            {{ $categoryTotal }}
                                                        </td>
                                                    @endforeach
                            <td class="px-4 py-3 border-t-2 border-gray-300 bg-gray-200 text-center text-gray-900">
                                {{ $grandTotal }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 text-sm text-gray-600">
                <p><strong>Total de tickets classifiés :</strong> {{ $partitionTotal }}</p>
                <p><strong>Période :</strong> {{ count($ticketPartitionData) }} jours</p>
            </div>
        @endif
    
        <x-slot:actions>
            <x-button label="Fermer" @click="$wire.myModal1 = false" class="btn-soft btn-primary" />
        </x-slot:actions>
    </x-modal>


        {{-- <div class="grid grid-cols-1 space-y-6">
            @foreach($projects as $project)
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <h2 class="sr-only" id="profile-overview-title">Profile Overview</h2>
                    <div class="bg-white p-6">
                        <div class="sm:flex sm:items-center sm:justify-between">
                            <div class="sm:flex sm:space-x-5">
                                <div class="mt-4 text-center sm:mt-0 sm:pt-1 sm:text-left">
                                    <p class="text-sm font-medium text-gray-600">{{ $project['name'] }}</p>
                                    <p class="text-xl font-bold text-gray-900 sm:text-2xl">{{ $project['code'] }}</p>
                                </div>
                            </div>
                            <div class="mt-5 flex justify-center sm:mt-0">
                                <x-button icon-right="o-arrow-long-right"
                                    href="{{ route('project.view', ['id' => $project['id']]) }}" wire:navigate
                                    label="en savoir plus" class="btn-primary btn-outline" />
                            </div>
                        </div>
                    </div>
                    <div
                        class="grid grid-cols-1 divide-y divide-gray-200 border-t border-gray-200 bg-gray-50 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div class="px-6 py-5 text-center text-sm font-medium">
                            <span class="text-gray-900">{{ $project['pending_ticket'] }}</span>
                            <span class="text-gray-600">ticket en attente</span>
                        </div>
                        <div class="px-6 py-5 text-center text-sm font-medium">
                            <span class="text-gray-900">{{ $project['in_progress_ticket'] }}</span>
                            <span class="text-gray-600">ticket en cours</span>
                        </div>
                        <div class="px-6 py-5 text-center text-sm font-medium">
                            <span class="text-gray-900">{{ $project['closed_ticket'] }}</span>
                            <span class="text-gray-600">ticket fermé</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div> --}}


        {{-- <x-card subtitle="Statistiques par période" separator>
            <div class="mb-5 flex items-end gap-6">

                <fieldset class="fieldset flex-1">
                    <legend class="fieldset-legend">Filtrer par statut</legend>
                    <select class="select w-full" wire:model.live="ticketStatus">
                        <option value="all">Tous les statuts</option>
                        <option value="en attente">En attente</option>
                        <option value="en cours">En cours</option>
                        <option value="cloture">Clôturé</option>
                    </select>
                    <span class="label">Optionnel</span>
                </fieldset>


                <fieldset class="fieldset flex-1">
                    <legend class="fieldset-legend">Période (jours)</legend>
                    <select class="select w-full" wire:model.live.debounce.500ms="daterange">
                        <option value="0">7 Jours</option>
                        <option value="1">15 Jours</option>
                        <option value="2">1 Mois</option>
                        <option value="3">3 Mois</option>
                        <option value="4">6 Mois</option>
                        <option value="5">1 Ans</option>
                    </select>
                    <span class="label">Optionnel</span>
                </fieldset>

            </div>

            <div wire:loading wire:target="ticketStatus,daterange" class="flex items-center justify-center py-4">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                <span class="ml-2 text-sm text-gray-500">Mise à jour en cours...</span>
            </div>

            <div wire:ignore>
                <div id="ticketLineChart" style="height: 300px;"></div>
            </div>

            <div class="mt-4 text-xs text-gray-500 border-t pt-3">
                <p>
                    Filtres actifs:
                    <span
                        class="font-medium">{{ $ticketStatus === 'all' ? 'Tous les statuts' : ucfirst($ticketStatus) }}</span>
                    •
                    @php
                        $labels = [
                            0 => '7 jours',
                            1 => '15 jours',
                            2 => '1 mois',
                            3 => '3 mois',
                            4 => '6 mois',
                            5 => '1 an',
                        ];
                    @endphp

                    <span class="font-medium">
                        {{ $labels[$daterange] ?? '' }}
                    </span>

                </p>
            </div>
        </x-card>

        <x-card subtitle="Classification par catégorie" separator>
            <div wire:loading wire:target="loadTicketPartition" class="flex justify-center items-center h-96">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                <span class="ml-3 text-gray-500">Chargement...</span>
            </div>

            <div wire:ignore>
                <div id="ticketPartitionChart" style="height: 400px;"></div>
            </div>

            @if(!empty($ticketPartitionData))
                <div class="mt-4 text-xs text-gray-500 border-t pt-3">
                    <p class="font-medium">
                        Total: {{ $partitionTotal }} tickets classifiés
                    </p>
                </div>
            @endif
        </x-card> --}}

        @if($role == 'super_admin')
            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-6">

                <x-card subtitle="Répartition selon le type de demande">
                    {{-- Filtres --}}
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-base-200 rounded-lg">
                        <x-select label="Mois" icon="o-calendar" :options="$months" wire:model.live="selectedMonth" />
                        <x-select label="Année" icon="o-calendar-days" :options="$years" wire:model.live="selectedYear" />
                        <div class="flex items-end">
                            <fieldset class="fieldset">
                                <legend class="fieldset-legend">Par projet</legend>
                                <select class="select w-full" wire:model.live="projet">
                                    <option selected>Tous</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                                    @endforeach
                                </select>
                            </fieldset>
                        </div>
                    </div>

                    @if($loadingDonut)
                        <div class="flex justify-center items-center h-64">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                            {{-- <span class="ml-3 text-gray-500">Chargement...</span> --}}
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-6">
                            <div class="text-center mb-6">
                                <div class="text-4xl font-bold text-error">{{ $totalRequests }}</div>
                                <div class="text-sm text-gray-500">Total Demandes</div>
                            </div>

                            <!-- Indicateur de chargement -->
                            <div wire:loading wire:target="projet" class="flex items-center justify-center py-4">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                                <span class="ml-2 text-sm text-gray-500 text-center">Mise à jour en cours...</span>
                            </div>

                            <!-- Chart masqué pendant le chargement -->
                            <div wire:ignore wire:loading.remove wire:target="projet">
                                <div id="donutChart" style="height: 400px; width: 100%;"></div>
                            </div>

                        </div>
                    @endif
                </x-card>


                <x-card subtitle="Statistiques par personne">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-semibold text-gray-600">
                            Activité par jour (du 1er au {{ now()->translatedFormat('d M Y') }}, hors week-ends)
                        </h3>
                        {{-- <x-button icon="o-arrow-path" wire:click="loadUserActivitySummary" spinner="loadUserActivitySummary"
                            label="Rafraîchir" class="btn-soft btn-primary" /> --}}
                    </div>

                    @if($loadingUserActivity)
                        <div class="flex justify-center items-center h-64">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                            {{-- <span class="ml-3 text-gray-500">Chargement des données...</span> --}}
                        </div>
                    @elseif(empty($userActivityData))
                        <p class="text-center text-gray-500 py-6">Aucune donnée disponible.</p>
                    @else
                        @php
        // Transformer les données : dates en colonnes, users en lignes
        $dates = [];
        $userStats = [];

        foreach ($userActivityData as $row) {
            $date = $row['date'];
            if (!in_array($date, $dates)) {
                $dates[] = $date;
            }

            foreach ($row as $key => $value) {
                if ($key !== 'date') {
                    if (!isset($userStats[$key])) {
                        $userStats[$key] = [];
                    }
                    $userStats[$key][$date] = $value;
                }
            }
        }
                        @endphp

                        <div class="overflow-x-auto landscape-scrollbar">
                            <table class="min-w-max border border-gray-200 rounded-lg text-sm shadow-sm">
                                <thead class="bg-gray-50 text-gray-700 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-2 text-left border-b border-gray-300 bg-gray-50 sticky left-0 z-20 whitespace-nowrap">
                                            Utilisateur
                                        </th>
                                        @foreach($dates as $date)
                                            <th class="px-4 py-2 text-center border-b border-gray-300 whitespace-nowrap">
                                                {{ \Carbon\Carbon::parse($date)->translatedFormat('d M') }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($userStats as $userName => $stats)
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-4 py-2 border-b border-gray-100 bg-white sticky left-0 z-10 font-medium text-gray-800 whitespace-nowrap">
                                                {{ ucfirst($userName) }}
                                            </td>
                                            @foreach($dates as $date)
                                                <td class="px-4 py-2 border-b border-gray-100 text-gray-700 text-center">
                                                    <span class="block min-w-[60px]">
                                                        {{ $stats[$date] ?? '0' }}
                                                    </span>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>

            </div>
        @endif


</div>

{{-- Dans la section <script>, remplacez le code existant par celui-ci --}}

<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>

<script>
    let lineChart = null;
    let donutChart = null;
    let barChart = null;

    const dayLabels = {
        0: "7 jours",
        1: "15 jours",
        2: "1 mois",
        3: "3 mois",
        4: "6 mois",
        5: "1 an"
    };

// Noms lisibles pour les catégories
    const categoryLabels = {
        'suivi_commande': 'Suivi commande',
        'colis_non_recu': 'Colis non reçu',
        'paiement': 'Paiement',
        'facture_non_recu': 'Facture non reçue',
        'produit_defectueux': 'Produit défectueux',
        'retour_retractation': 'Retour/Rétractation',
        'demande_specifique': 'Demande spécifique',
        'colis_vide': 'Colis vide',
        'spam': 'Spam',
        'changement_adresse_livraison': 'Changement Adresse',
        'inversion_colis': 'Inversion Colis'
    };

    // Couleurs pour chaque catégorie
    const categoryColors = {
        'suivi_commande': '#3b82f6',
        'colis_non_recu': '#ef4444',
        'paiement': '#10b981',
        'facture_non_recu': '#f59e0b',
        'produit_defectueux': '#8b5cf6',
        'retour_retractation': '#ec4899',
        'demande_specifique': '#06b6d4',
        'colis_vide': '#f97316',
        'spam': '#b8c925ff'
        'changement_adresse_livraison': 'rgb(9, 10, 1)',
        'inversion_colis': 'rgba(99, 100, 3, 0.5)',
    };

    const highchartsColors = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', 
        '#8b5cf6', '#ec4899', '#06b6d4', '#f97316',
        '#b8c925ff', 'rgb(9, 10, 1)', 'rgba(99, 100, 3, 0.5)'
    ];

    // Configuration commune pour les exports
    const exportingConfig = {
        enabled: true,
        buttons: {
            contextButton: {
                menuItems: [
                    'viewFullscreen',
                    'separator',
                    'downloadPNG',
                    'downloadJPEG',
                    'downloadPDF',
                    'downloadSVG',
                    'separator',
                    'downloadCSV',
                    'downloadXLS'
                ],
                symbolStroke: '#3b82f6',
                theme: {
                    fill: 'transparent'
                }
            }
        },
        filename: 'graphique-export',
        chartOptions: {
            title: {
                style: {
                    fontSize: '16px'
                }
            }
        }
    };

    // ===== LINE CHART =====
    function renderTicketChart(labels, values, status = 'all', days = 0) {
        let seriesName = 'Tickets';
        if (status !== 'all') {
            seriesName += ` (${status})`;
        }
        seriesName += ` - ${dayLabels[days] ?? ""}`;

        const container = document.getElementById('ticketLineChart');
        if (!container) return;

        if (lineChart) {
            lineChart.destroy();
        }

        lineChart = Highcharts.chart('ticketLineChart', {
            chart: {
                type: 'area'
            },
            title: {
                text: 'Statistiques des tickets par période'
            },
            subtitle: {
                text: `Filtré par: ${status !== 'all' ? status : 'Tous les statuts'} • ${dayLabels[days] ?? ""}`
            },
            xAxis: {
                categories: labels,
                title: {
                    text: 'Période'
                }
            },
            yAxis: {
                title: {
                    text: 'Nombre de tickets'
                },
                allowDecimals: false
            },
            legend: {
                enabled: true
            },
            plotOptions: {
                area: {
                    fillColor: {
                        linearGradient: {
                            x1: 0,
                            y1: 0,
                            x2: 0,
                            y2: 1
                        },
                        stops: [
                            [0, 'rgba(59, 130, 246, 0.3)'],
                            [1, 'rgba(59, 130, 246, 0.05)']
                        ]
                    },
                    marker: {
                        radius: 4
                    },
                    lineWidth: 2,
                    states: {
                        hover: {
                            lineWidth: 3
                        }
                    },
                    threshold: null
                }
            },
            series: [{
                name: seriesName,
                data: values,
                color: '#3b82f6'
            }],
            credits: {
                enabled: false
            },
            tooltip: {
                shared: true,
                valueSuffix: ' tickets'
            },
            exporting: {
                ...exportingConfig,
                filename: `tickets-${status}-${dayLabels[days]?.replace(/\s/g, '-')}`
            }
        });
    }

    function updateChart(labels, values, status = 'all', days = 0) {
        if (lineChart) {
            let seriesName = 'Tickets';
            if (status !== 'all') {
                seriesName += ` (${status})`;
            }
            seriesName += ` - ${dayLabels[days] ?? ""}`;

            lineChart.update({
                title: {
                    text: 'Statistiques des tickets par période'
                },
                subtitle: {
                    text: `Filtré par: ${status !== 'all' ? status : 'Tous les statuts'} • ${dayLabels[days] ?? ""}`
                },
                xAxis: {
                    categories: labels
                },
                series: [{
                    name: seriesName,
                    data: values
                }],
                exporting: {
                    filename: `tickets-${status}-${dayLabels[days]?.replace(/\s/g, '-')}`
                }
            });
        } else {
            renderTicketChart(labels, values, status, days);
        }
    }

    // ===== BAR CHART (Classification) =====
    function renderStackedBarChart(rawData) {
        const container = document.getElementById('ticketPartitionChart');
        if (!container) {
            console.log('Container ticketPartitionChart not found');
            return;
        }

        if (barChart) {
            barChart.destroy();
        }

        if (!rawData || rawData.length === 0) {
            console.log('No data available for classification chart');
            return;
        }

        // Extract dates for x-axis labels
        const dates = rawData.map(item => {
            const d = new Date(item.date);
            return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
        });

        // Extract categories (excluding 'date')
        const categories = Object.keys(rawData[0]).filter(key => key !== 'date');

        // Create series for each category
        const series = categories.map(category => ({
            name: categoryLabels[category] || category,
            data: rawData.map(item => parseInt(item[category]) || 0),
            color: categoryColors[category] || '#999999'
        }));

        barChart = Highcharts.chart('ticketPartitionChart', {
            chart: {
                type: 'line'
            },
            title: {
                text: 'Classification des tickets par catégorie'
            },
            subtitle: {
                text: 'Évolution temporelle des différentes catégories'
            },
            yAxis: {
                title: {
                    text: 'Nombre de tickets'
                },
                allowDecimals: false
            },
            xAxis: {
                categories: dates,
                accessibility: {
                    rangeDescription: 'Période de temps'
                }
            },
            legend: {
                layout: 'vertical',
                align: 'right',
                verticalAlign: 'middle'
            },
            plotOptions: {
                series: {
                    label: {
                        connectorAllowed: false
                    },
                    marker: {
                        enabled: true,
                        radius: 3
                    }
                }
            },
            series: series,
            credits: {
                enabled: false
            },
            tooltip: {
                shared: true,
                crosshairs: true,
                formatter: function() {
                    let s = '<b>' + this.x + '</b><br/>';
                    let total = 0;
                    this.points.forEach(point => {
                        s += '<span style="color:' + point.color + '">\u25CF</span> ' + 
                             point.series.name + ': ' + point.y + '<br/>';
                        total += point.y;
                    });
                    s += '<b>Total: ' + total + '</b>';
                    return s;
                }
            },
            exporting: {
                ...exportingConfig,
                filename: 'classification-tickets-par-categorie'
            },
            responsive: {
                rules: [{
                    condition: {
                        maxWidth: 500
                    },
                    chartOptions: {
                        legend: {
                            layout: 'horizontal',
                            align: 'center',
                            verticalAlign: 'bottom'
                        }
                    }
                }]
            }
        });
    }

    // ===== DONUT CHART =====
    function renderDonutChart(data) {
        const container = document.getElementById('donutChart');
        if (!container) {
            console.log('Container donutChart not found');
        return;
        }

        if (donutChart) {
            donutChart.destroy();
        }

        const chartData = data.map((item, index) => ({
            name: item.label_name,
        y: parseInt(item.nb),
        color: highchartsColors[index % highchartsColors.length]
        }));

        donutChart = Highcharts.chart('donutChart', {
            chart: {
            type: 'pie'
            },
        title: {
            text: 'Répartition des demandes par type'
            },
        plotOptions: {
            pie: {
            innerSize: '50%',
        dataLabels: {
            enabled: true,
        format: '<b> ( {point.y} )  {point.name} : {point.percentage:.1f}%</b>'
                    },
        showInLegend: true
                }
            },
        series: [{
            name: 'Demandes',
        data: chartData
            }],
        credits: {
            enabled: false
            },
        tooltip: {
            pointFormat: '<b>{point.y}</b> ({point.percentage:.1f}%)'
            },
        exporting: {
            ...exportingConfig,
            filename: 'repartition-demandes-par-type'
            }
        });
    }

    function initDonutChart() {
        setTimeout(() => {
            const donutData = @json($donutData ?? []);
            if (donutData && donutData.length > 0) {
                renderDonutChart(donutData);
            }
        }, 100);
    }

    function initStackedBarChart() {
        setTimeout(() => {
            const partitionData = @json($ticketPartitionData ?? []);
            if (partitionData && partitionData.length > 0) {
                renderStackedBarChart(partitionData);
            }
        }, 100);
    }

    // ===== INITIALIZATION =====
    document.addEventListener("DOMContentLoaded", () => {
        const initialLabels = @json($chartData['labels'] ?? []);
        const initialValues = @json($chartData['values'] ?? []);
        const initialStatus = @json($ticketStatus);
        const initialDays = @json($daterange);
        const initialProject = @json($project_id);
        renderTicketChart(initialLabels, initialValues, initialStatus, initialDays, initialProject);

        initDonutChart();
        initStackedBarChart();
    });

    document.addEventListener("livewire:navigated", () => {
        const initialLabels = @json($chartData['labels'] ?? []);
        const initialValues = @json($chartData['values'] ?? []);
        const initialStatus = @json($ticketStatus);
        const initialDays = @json($daterange);
        const initialProject = @json($project_id);
        renderTicketChart(initialLabels, initialValues, initialStatus, initialDays, initialProject);

        initDonutChart();
        initStackedBarChart();
    });

    document.addEventListener("livewire:init", () => {
        Livewire.on("chart-updated", (event) => {
            const [data] = event;
            updateChart(data.labels, data.values, data.status, data.days);
        });

        Livewire.on("donut-chart-updated", (event) => {
            const [data] = event;
            renderDonutChart(data.data);
        });

        Livewire.on("ticket-partition-updated", (event) => {
            const [data] = event;
            renderStackedBarChart(data.data);
        });
    });
</script>