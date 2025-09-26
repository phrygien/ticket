<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Illuminate\Validation\Rule;
use Mary\Traits\Toast;

new class extends Component {
    use WithFileUploads;

    use Toast;

    public int $ticketId;
    public array $ticketDetails = [];
    public bool $loading = true;

    public string $activeTab = 'description'; // Onglet actif: description | conversation
    public bool $myModal1 = false;

    public ?array $selectedMessage = null;

    #[Validate(['photos.*' => 'image|max:1024'])]
    public $photos = [];

    public $destinateur;

    public bool $showDrawer2 = false;

    public $message_txt;

    public bool $showSendmailTab = false;


    public $message_client;

    public function mount($ticket)
    {
        $this->ticketId = $ticket;
        $this->fetchTicketDetails();
    }

    public function fetchTicketDetails()
    {
        $token = session('token');
        if (!$token)
            return redirect()->route('login');

        $response = Http::withHeaders([
            'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->get("https://dev-ia.astucom.com/n8n_cosmia/ticket/{$this->ticketId}");

        if ($response->successful()) {
            $this->ticketDetails = $response->json();
            $this->destinateur = $response['details'][0]['original_client_mail'];
        }

        $this->loading = false;
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function openMessage($index)
    {
        $messages = $this->ticketDetails['conversation']['messages'] ?? [];
        $this->selectedMessage = $messages[$index] ?? null;
    }

    function formatMessage($message)
    {
        $message = strip_tags($message);

        $pattern = '~(https?://[^\s<]+|www\.[^\s<]+)~i';

        $message = preg_replace_callback($pattern, function ($matches) {
            $url = $matches[0];
            $href = preg_match('~^https?://~i', $url) ? $url : "http://$url";
            return '<a href="' . e($href) . '" target="_blank" class="text-blue-600 underline">' . e($url) . '</a>';
        }, $message);

        $message = nl2br($message);

        return $message;
    }


    public function replySelectedMessage()
    {
        if ($this->selectedMessage) {
            $this->message_txt = strip_tags($this->selectedMessage['message'] ?? '');
        }

        $this->activeTab = 'sendmail';
    }


    public function replyFirstMessage()
    {
        $messages = $this->ticketDetails['conversation']['messages'] ?? [];

        if (!empty($messages)) {
            $firstMessage = $messages[0];
            $this->message_client = strip_tags($firstMessage['message'] ?? '');
        }

        $this->showSendmailTab = true;

        $this->activeTab = 'sendmail';
    }



    // detect language
    public function detectLanguage()
    {
        $token = session('token');
        if (!$token) {
            return redirect()->route('login');
        }

        $detectResponse = Http::withHeaders([
            'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post("https://dev-ia.astucom.com/n8n_cosmia/openai/detectlanguageiso", [
                    "text" => $this->message_client,
                ]);

        if ($detectResponse->successful()) {
            return $detectResponse->json();
        }

        return null;
    }

    // translate language
    public function translateOpenAI()
    {
        $token = session('token');
        if (!$token) {
            return redirect()->route('login');
        }

        $detectedLang = $this->detectLanguage();

        if (!$detectedLang || empty($detectedLang['langue'])) {
            return ['error' => 'Impossible de détecter la langue'];
        }

        $sourceLang = $detectedLang['langue'];
        $targetLang = 'en';

        if ($sourceLang === $targetLang) {
            return ['translated_text' => $this->message_txt];
        }

        $translateResponse = Http::withHeaders([
            'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post("https://dev-ia.astucom.com/n8n_cosmia/openai/translateandcorrect", [
                    "text" => $this->message_txt,
                    "target" => $sourceLang,
                ]);

        if ($translateResponse->successful()) {
            $translated = $translateResponse->json('translated_text');

            $this->message_txt = $translated;

            return ['translated_text' => $translated];
        }

        $this->loading = false;
    }


    // reply email
    public function reply(): void
    {
        $token = session('token');

        // Validation Livewire
        $this->validate([
            'message_txt' => 'required|string',
            'destinateur' => 'required',
        ]);

        $attachments = [];
        if (!empty($this->photos)) {
            foreach ($this->photos as $file) {
                $attachments[] = [
                    'filename' => $file->getClientOriginalName(),
                    'mimeType' => $file->getMimeType(),
                    'contentBase64' => preg_replace('/\s+/', '', base64_encode(file_get_contents($file->getRealPath()))),
                ];
            }
        }

        $messages = $this->ticketDetails['conversation']['messages'] ?? [];
        $firstMessage = $messages[0];
        $firstMessageId = $firstMessage['message_id'];
        $ticket_id = $this->ticketId;

        $body = [
            "ticket_id" => $ticket_id,
            "first_message_id" => $firstMessageId,
            "replyText" => $this->message_txt,
            "attachements" => $attachments,
        ];

        // dd(json_encode($body, JSON_UNESCAPED_SLASHES));
        // Appel API
        $response = Http::withHeaders([
            'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post("https://dev-ia.astucom.com/n8n_cosmia/ticket/replymail", $body);



        if ($response->successful()) {
            $this->message_txt = '';
            $this->photos = [];
            $this->success('Email envoyé avec succès !');
        } else {
            $this->error('Erreur lors de l’envoi de l’email !');
        }
    }


    public function updateStatus($newStatus)
    {
        $token = session('token');
        if (!$token) {
            return redirect()->route('login');
        }

        $response = Http::withHeaders([
            'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->put("https://dev-ia.astucom.com/n8n_cosmia/ticket/{$this->ticketId}", [
            "status" => $newStatus,
        ]);

        if ($response->successful()) {
            $this->ticketDetails['status'] = $newStatus; // mettre à jour localement
            $this->success("Le ticket est maintenant en statut : {$newStatus}");
        } else {
            $this->error("Impossible de mettre à jour le ticket !");
        }
    }


public function getNextStatus(): array
{
    $current = $this->ticketDetails['details'][0]['status'] ?? 'en attente';

    return match ($current) {
        'en attente' => ['label' => 'Mettre en cours', 'next' => 'en cours'],
        'en cours'   => ['label' => 'Clôturer le ticket', 'next' => 'cloture'],
        'cloture'    => ['label' => 'Réouvrir (en attente)', 'next' => 'en attente'],
        default      => ['label' => 'Mettre en attente', 'next' => 'en attente'],
    };
}


};
?>

<div class="max-w-7xl mx-auto">

    <x-header title="Détail du ticket #{{ $ticketId }}" subtitle="Informations complètes" separator>
        <x-slot:actions>
        <x-button 
            class="btn-primary"
            label="{{ $this->getNextStatus()['label'] }}"
            wire:click="updateStatus('{{ $this->getNextStatus()['next'] }}')" 
        />
        </x-slot:actions>
    </x-header>

    <div class="mx-auto max-w-7xl">

        {{-- Onglets --}}
        <div class="border-b border-gray-200 mb-4">
            <nav class="-mb-px flex space-x-8">
                <button wire:click="setTab('description')"
                    class="px-3 py-2 text-sm font-medium whitespace-nowrap border-b-2
                    {{ $activeTab === 'description' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-200' }}">
                    <!-- Icon Ticket -->
                    <svg class="w-4 h-4 inline-block mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m0 0l-6-6m6 6H3" />
                    </svg>
                    Ticket - {{ $ticketDetails['details'][0]['num_ticket'] }}
                </button>

                <button wire:click="setTab('conversation')"
                    class="px-3 py-2 text-sm font-medium whitespace-nowrap border-b-2
                    {{ $activeTab === 'conversation' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-200' }}">
                    <!-- Icon Mail / Boîte mail -->
                    <svg class="w-4 h-4 inline-block mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Conversation ({{ count($ticketDetails['conversation']['messages'] ?? []) }})
                </button>

                <button wire:click="setTab('commentaire')"
                    class="px-3 py-2 text-sm font-medium whitespace-nowrap border-b-2
                    {{ $activeTab === 'commentaire' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-200' }}">
                    <!-- Icon Comment -->
                    <svg class="w-4 h-4 inline-block mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4-.86L3 20l1.86-4a9.863 9.863 0 01-.86-4c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    Commentaires
                </button>

                @if($showSendmailTab)
                    <button wire:click="setTab('sendmail')"
                        class="px-3 py-2 text-sm font-medium whitespace-nowrap border-b-2
                            {{ $activeTab === 'sendmail' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-200' }}">
                        <!-- Icon Mail -->
                        <svg class="w-4 h-4 inline-block mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Send mail
                    </button>
                @endif
            </nav>
        </div>

    </div>

    <div>
        @if($activeTab === 'description')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="px-4 py-6 sm:px-6">
                        <h3 class="text-base/7 font-semibold text-gray-900">{{ __('Infos Ticket')}}</h3>
                        <p class="mt-1 max-w-2xl text-sm/6 text-gray-500">{{ __('Tous les information sur le ticket') }}.
                        </p>
                    </div>
                    <div class="border-t border-gray-100">
                        <dl class="divide-y divide-gray-100">
                            <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-900">Status</dt>
                                <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    {{ $ticketDetails['details'][0]['status'] }}
                                </dd>
                            </div>
                            <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-900">Numero ticket</dt>
                                <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    {{ $ticketDetails['details'][0]['num_ticket'] }}
                                </dd>
                            </div>
                            <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-900">Numero de commande</dt>
                                <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    {{ $ticketDetails['details'][0]['num_commande'] }}
                                </dd>
                            </div>
                            <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-900">Application for</dt>
                                <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    {{ $ticketDetails['details'][0]['subject_ticket'] }}
                                </dd>
                            </div>
                            <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-900">Email address</dt>
                                <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    {{ $ticketDetails['details'][0]['original_client_mail'] }}
                                </dd>
                            </div>
                            <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-900">Email address</dt>
                                <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    {{ $ticketDetails['details'][0]['reception_mail'] }}
                                </dd>
                            </div>
                            <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-900">Nom et prenoms du clients</dt>
                                <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    {{ $ticketDetails['details'][0]['nom_client'] }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="bg-gray-50 sm:rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-base font-semibold text-gray-900">To-Do</h3>

                        <div class="mt-2 max-w-xl text-sm text-gray-700 max-h-[500px] overflow-y-auto pr-2">
                            @php
                            $todos = $ticketDetails['details'][0]['to_do'] ?? '';
                            $items = preg_split('/\r\n|\r|\n/', trim($todos));
                            @endphp

                            <ul class="list-disc pl-5 space-y-1">
                                @foreach($items as $item)
                                    @if(!empty(trim($item)))
                                        <li>{{ trim($item) }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            @elseif($activeTab === 'conversation')

                        <div class="md:text-end text-start">
                            <x-button icon="o-arrow-path" class="btn-circle btn-outline float-left btn-warning"
                                wire:click="translateOpenAI" spiner="translateOpenAI" />


                            <button wire:click="replyFirstMessage" type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300
                                            font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2
                                            dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
                                Répondre
                            </button>
                        </div>

                        <div class="w-full">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <div class="space-y-4 max-h-[820px] overflow-y-auto">

                                    <ul role="list" class="divide-y divide-white divide-y-6">
                                        @php
    $messages = $ticketDetails['conversation']['messages'] ?? [];
    $detail = $ticketDetails['details'][0] ?? [];
    $clientEmail = strtolower($detail['original_client_mail'] ?? '');
    $supportEmail = strtolower($detail['reception_mail'] ?? '');
                                        @endphp

                                        @forelse($messages as $idx => $msg)
                                            @php
        $fromRaw = $msg['from'] ?? '';
        // Extraire email depuis "Nom <email>"
        if (preg_match('/<([^>]+)>/', $fromRaw, $m)) {
            $fromEmail = strtolower($m[1]);
        } elseif (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $fromRaw, $m2)) {
            $fromEmail = strtolower($m2[0]);
        } else {
            $fromEmail = strtolower(trim($fromRaw));
        }

        $label = null;
        $badgeClasses = '';
        if ($clientEmail && strpos($fromEmail, $clientEmail) !== false) {
            $label = 'Client';
            $badgeClasses = 'bg-blue-100 text-blue-600';
        } elseif ($supportEmail && strpos($fromEmail, $supportEmail) !== false) {
            $label = 'Support';
            $badgeClasses = 'bg-green-100 text-green-600';
        }
                                            @endphp

                                            <li class="flex flex-wrap items-center justify-between gap-x-6 gap-y-4 py-5 sm:flex-nowrap">
                                                <div>
                                                    {{-- Sujet --}}
                                                    <p class="text-sm font-semibold text-gray-900">
                                                        <a @click="$wire.myModal1 = true" href="#"
                                                            wire:click.prevent="$wire.openMessage({{ $idx }})" class="hover:underline">
                                                            {{ $msg['subject'] ?? '(Sans objet)' }}
                                                        </a>
                                                    </p>

                                                    {{-- Expéditeur + Date --}}
                                                    <div class="mt-1 flex items-center gap-x-2 text-xs text-gray-500">
                                                        <p class="flex items-center gap-x-1">
                                                            <span class="hover:underline">{{ $fromRaw }}</span>

                                                            @if($label)
                                                                <span
                                                                    class="ml-1 inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium {{ $badgeClasses }}">
                                                                    {{ $label }}
                                                                </span>
                                                            @endif
                                                        </p>
                                                        <svg viewBox="0 0 2 2" class="size-0.5 fill-current">
                                                            <circle cx="1" cy="1" r="1" />
                                                        </svg>
                                                        <p>
                                                            <time>
                                                                {{ isset($msg['date']) ? \Carbon\Carbon::parse($msg['date'])->format('d/m/Y H:i') : '' }}
                                                            </time>
                                                        </p>
                                                    </div>

                                                    {{-- Aperçu du message --}}
                                                    <p class="mt-1 text-xs text-gray-600">
                                                        {!! nl2br(e(strip_tags(\Illuminate\Support\Str::words($msg['message'] ?? '', 30, '...')))) !!}
                                                        <a href="#" wire:click.prevent="openMessage({{ $idx }})"
                                                            class="font-semibold text-indigo-600 hover:text-indigo-500">
                                                            en savoir plus →
                                                        </a>

                                                    </p>
                                                </div>
                                            </li>
                                        @empty
                                            <li class="py-5 text-gray-500">Aucun message trouvé.</li>
                                        @endforelse
                                    </ul>

                                </div>

                                @if($selectedMessage)
                                    <div class="bg-gray-50 sm:rounded-lg">
                                        <div class="px-4 py-5 sm:p-6">
                                            <h3 class="text-base font-semibold text-gray-900">
                                                {{ $selectedMessage['subject'] ?? '(Sans objet)' }}
                                            </h3>
                                            <p class="text-sm text-gray-500"><strong>Expéditeur :</strong>
                                                {{ $selectedMessage['from'] ?? '-' }}
                                            </p>
                                            <p class="text-sm text-gray-500"><strong>Date :</strong>
                                                {{ isset($selectedMessage['date']) ? \Carbon\Carbon::parse($selectedMessage['date'])->format('d/m/Y H:i') : '-' }}
                                            </p>

                                            <div class="mt-2 max-w-xl text-sm text-gray-700 prose">
                                                {!! $this->formatMessage($selectedMessage['message'] ?? '') !!}
                                            </div>

                                            @if(!empty($selectedMessage['attachments']))
                                                        @php
            $images = [];
            $otherFiles = [];
            foreach ($selectedMessage['attachments'] as $file) {
                if (Str::startsWith($file['mimeType'], 'image/')) {
                    $images[] = $file['data'];
                } else {
                    $otherFiles[] = $file;
                }
            }
                                                        @endphp

                                                        @if(!empty($otherFiles))
                                                            <div class="mt-4 border-t pt-3">
                                                                <p class="text-sm font-medium text-gray-700 mb-2">Autres fichiers :</p>
                                                                <ul class="space-y-4">
                                                                    @foreach($otherFiles as $file)
                                                                        @php
                    $ext = pathinfo($file['filename'], PATHINFO_EXTENSION);
                                                                        @endphp

                                                                        @if(strtolower($ext) === 'pdf')
                                                                            <!-- Prévisualisation PDF -->
                                                                            <x-card title="Nice things">
                                                                                                                                                            <p class="text-sm font-medium text-gray-700 mb-1">{{ $file['filename'] }}</p>
                                                                                <iframe src="{{ $file['data'] }}" class="w-full h-64" frameborder="0"></iframe>
                                                                                <a href="{{ $file['data'] }}" download="{{ $file['filename'] }}"
                                                                                    class="text-blue-600 hover:underline mt-1 inline-block">
                                                                                    Télécharger
                                                                                </a>
                                                                            </x-card>
                                                                        @else
                                                                            <!-- Autres fichiers -->
                                                                            <li class="flex items-center space-x-2">
                                                                                <a href="{{ $file['data'] }}" download="{{ $file['filename'] }}" target="_blank"
                                                                                    class="text-blue-600 hover:underline">
                                                                                    {{ $file['filename'] }}
                                                                                </a>
                                                                                <span class="text-xs text-gray-500">
                                                                                    ({{ number_format($file['size'] / 1024, 1) }} KB)
                                                                                </span>
                                                                            </li>
                                                                        @endif
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        @endif
                                            @endif

                                        </div>
                                    </div>
                                @endif

                            </div>

                        </div>
            @endif
            @if($activeTab === 'sendmail')
                <div class="mx-auto max-w-7xl">
                    <div>
                        <x-header title="Repondre email" separator />

                        <!-- Grid stuff from Tailwind -->
                        <div class="grid gap-5 lg:grid-cols-2">
                            <div>
                                <x-form wire:submit="reply">
                                    <x-input label="Destinateur" wire:model="destinateur" placeholder="Destinateur"
                                        icon="o-user" hint="Le client qui va recevoire l'email" readonly />
                                    <x-markdown wire:model="message_txt" label="Message" />
                                    {{-- <x-markdown wire:model="message_client" label="Message du client" /> --}}

                                    <x-slot:actions>
                                        <div class="flex justify-between w-full">
                                            <x-button label="Annuler" />

                                            <div class="flex space-x-2">
                                                <x-button icon="o-language" class="btn-circle btn-outline"
                                                    wire:click="detectLanguage" spinner="detectLanguage" />
                                                <x-button icon="o-language" class="btn-circle btn-outline btn-accent"
                                                    wire:click="translateOpenAI" spinner="translateOpenAI" />
                                                <x-button icon="o-paper-airplane" class="btn-circle btn-outline btn-primary"
                                                    type="submit" spinner="reply" />
                                            </div>
                                        </div>
                                    </x-slot:actions>

                                </x-form>
                            </div>
                            <div>
                                <x-file wire:model="photos" label="Documents" multiple />
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($activeTab === 'commentaire')

                <div class="mx-auto max-w-5xl">
                    @foreach ($ticketDetails['comment'] as $comment)
                        <div class="bg-white shadow-sm rounded-md sm:rounded-lg p-4 mt-2">
                            <h3 class="text-sm font-semibold text-gray-700">Commentaire #{{ $comment['id'] }}</h3>
                            <p class="mt-1 text-gray-600">{{ $comment['comment'] }}</p>
                            <p class="text-xs text-gray-400 mt-1">Publié le :
                                {{ \Carbon\Carbon::parse($comment['created_at'])->format('d/m/Y H:i') }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

    </div>



</div>