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

    public $translatedMessage = '';

    public function mount($ticket)
    {
        $this->ticketId = $ticket;
        $this->fetchTicketDetails();
    }

    public function selectMessage(array $message)
    {
        $this->selectedMessage = $message;
        $this->translatedMessage = ''; // Réinitialiser la traduction
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

    public function openMessageOld($index)
    {
        $messages = $this->ticketDetails['conversation']['messages'] ?? [];
        $this->selectedMessage = $messages[$index] ?? null;
        $this->translatedMessage = '';
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
    public function replyOld(): void
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

        //dd(json_encode($body, JSON_UNESCAPED_SLASHES));
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
            $this->success("Le ticket est maintenant en statut : {$newStatus}");

            // **rafraîchir les infos du ticket**
            $this->fetchTicketDetails();
        } else {
            $this->error("Impossible de mettre à jour le ticket !");
        }
    }



    public function getNextStatus(): array
    {
        $current = $this->ticketDetails['details'][0]['status'] ?? 'en attente';

        return match ($current) {
            'en attente' => ['label' => 'Mettre en cours', 'next' => 'en cours'],
            'en cours' => ['label' => 'Clôturer le ticket', 'next' => 'cloture'],
            'cloture' => ['label' => 'Réouvrir (en attente)', 'next' => 'en attente'],
            default => ['label' => 'Mettre en attente', 'next' => 'en attente'],
        };
    }


    // Bouton "Traduire en français"


    // 🔹 Traduire le message sélectionné
    public function translateMessage()
    {
        $token = session('token');
        if (!$token) {
            return redirect()->route('login');
        }

        if (empty($this->selectedMessage['message'])) {
            $this->translatedMessage = 'Aucun message à traduire.';
            return;
        }

        $messageText = $this->selectedMessage['message'];

        $response = Http::withHeaders([
            'x-secret-key' => 'betab0riBeM3c3Ne6MiK6JP6H4rY',
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->post("https://dev-ia.astucom.com/n8n_cosmia/openai/translateandcorrect", [
                    "text" => $messageText,
                    "target" => "fr",
                ]);

        if ($response->successful()) {
            $translated = $response->json('translated_text') ?? 'Erreur de traduction';
            $this->translatedMessage = $this->formatMessage($translated);
        } else {
            $this->translatedMessage = 'Erreur lors de l\'appel API';
        }
    }

    public function updatedSelectedMessage($value)
    {
        $this->translatedMessage = '';
    }


    public function reply(): void
    {
        $token = session('token');

        $this->validate([
            'message_txt' => 'required|string',
            'destinateur' => 'required',
        ]);

        $attachments = [];
        if (!empty($this->photos)) {
            foreach ($this->photos as $file) {
                // Vérifier que le fichier existe
                if (!$file->isValid()) {
                    $this->error('Un fichier est invalide');
                    continue;
                }

                $filePath = $file->getRealPath();
                $fileContent = file_get_contents($filePath);

                // Vérifier que le contenu n'est pas vide
                if (empty($fileContent)) {
                    $this->error("Le fichier {$file->getClientOriginalName()} est vide");
                    continue;
                }

                $base64Content = base64_encode($fileContent);

                // Vérifier l'encodage
                if (empty($base64Content)) {
                    $this->error("Erreur d'encodage pour {$file->getClientOriginalName()}");
                    continue;
                }

                $attachments[] = [
                    'filename' => $file->getClientOriginalName(),
                    'mimeType' => $file->getMimeType(),
                    'contentBase64' => $base64Content,
                ];
            }
        }

        // Vérifier que des attachements ont été ajoutés
        if (!empty($this->photos) && empty($attachments)) {
            $this->error('Aucun fichier valide n\'a pu être traité');
            return;
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

        // Debug : afficher le body avant l'envoi
        \Log::info('Body envoyé:', $body);

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
            \Log::error('Erreur API:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            $this->error('Erreur lors de l\'envoi de l\'email : ' . $response->body());
        }
    }

    public function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }


    public function downloadAttachment($messageIndex, $attachmentIndex)
    {
        $messages = $this->ticketDetails['conversation']['messages'] ?? [];

        if (!isset($messages[$messageIndex]['attachments'][$attachmentIndex])) {
            $this->error('Pièce jointe introuvable');
            return;
        }

        $attachment = $messages[$messageIndex]['attachments'][$attachmentIndex];

        // Extraire les données base64
        if (isset($attachment['data']) && str_starts_with($attachment['data'], 'data:')) {
            preg_match('/data:([^;]+);base64,(.+)/', $attachment['data'], $matches);

            if (count($matches) === 3) {
                $mimeType = $matches[1];
                $base64Data = $matches[2];
                $fileContent = base64_decode($base64Data);
                $filename = $attachment['filename'] ?? 'document';

                return response()->streamDownload(function () use ($fileContent) {
                    echo $fileContent;
                }, $filename, [
                    'Content-Type' => $mimeType,
                ]);
            }
        }

        $this->error('Impossible de télécharger la pièce jointe');
    }


    public ?int $selectedMessageIndex = null;

    public function openMessage($index)
    {
        $messages = $this->ticketDetails['conversation']['messages'] ?? [];
        $this->selectedMessage = $messages[$index] ?? null;
        $this->selectedMessageIndex = $index;
        $this->translatedMessage = '';
    }


};
?>

<div class="w-full mx-auto">
    {{-- <x-header title="Détail du ticket #{{ $ticketId }}" subtitle="Informations complètes" separator>
        <x-slot:actions>
        <x-button 
            class="btn-primary"
            label="{{ $this->getNextStatus()['label'] }}"
            wire:click="updateStatus('{{ $this->getNextStatus()['next'] }}')" 
        />
        </x-slot:actions>
    </x-header> --}}

    <div class="mx-auto w-full">

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

    <div class="flex h-[calc(100vh-200px)] bg-gray-50">
        <!-- Liste des messages (gauche) -->
        <div class="w-1/2 bg-white border-r border-gray-200 flex flex-col">
            <!-- Header avec bouton répondre -->
            <div class="p-4 border-b border-gray-200 bg-white">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Conversation</h2>
                    @if(count($ticketDetails['conversation']['messages'] ?? []) > 0)
                        <button wire:click="replyFirstMessage" type="button"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                            </svg>
                            Répondre
                        </button>
                    @endif
                </div>
            </div>

            <!-- Liste scrollable des messages -->
            <div class="flex-1 overflow-y-auto">
                @php
    $messages = $ticketDetails['conversation']['messages'] ?? [];
    $detail = $ticketDetails['details'][0] ?? [];
    $clientEmail = strtolower($detail['original_client_mail'] ?? '');
    $supportEmail = strtolower($detail['reception_mail'] ?? '');
                @endphp

                @forelse($messages as $idx => $msg)
                    @php
        $fromRaw = $msg['from'] ?? '';
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

        $isSelected = $selectedMessageIndex === $idx;
                    @endphp

                    <div wire:click="openMessage({{ $idx }})"
                        class="px-4 py-3 border-b border-gray-200 cursor-pointer hover:bg-gray-50 transition-colors {{ $isSelected ? 'bg-blue-50 border-l-4 border-l-blue-600' : '' }}">

                        <!-- Header du message -->
                        <div class="flex items-start justify-between mb-1">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <!-- Avatar initial -->
                                <div
                                    class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-sm">
                                    {{ strtoupper(substr($fromRaw, 0, 1)) }}
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900 truncate">
                                            {{ preg_match('/^([^<]+)/', $fromRaw, $nameMatch) ? trim($nameMatch[1]) : $fromRaw }}
                                        </span>
                                        @if($label)
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeClasses }}">
                                                {{ $label }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        {{ isset($msg['date']) ? \Carbon\Carbon::parse($msg['date'])->format('d/m/Y H:i') : '' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Indicateur pièces jointes -->
                            @if(!empty($msg['attachments']))
                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                            @endif
                        </div>

                        <!-- Sujet -->
                        <h3 class="text-sm font-medium text-gray-900 mb-1 truncate">
                            {{ $msg['subject'] ?? '(Sans objet)' }}
                        </h3>

                        <!-- Aperçu du message -->
                        <p class="text-xs text-gray-600 line-clamp-2">
                            {{ strip_tags($msg['message'] ?? '') }}
                        </p>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center h-full p-8 text-center">
                        <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <p class="text-gray-500">Aucun message trouvé</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Panneau de lecture (droite) -->
        <div class="flex-1 flex flex-col bg-white">
            @if($selectedMessage)
                <!-- En-tête du message -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-start justify-between mb-3">
                        <h1 class="text-xl font-semibold text-gray-900 flex-1">
                            {{ $selectedMessage['subject'] ?? '(Sans objet)' }}
                        </h1>
                    </div>

                    <!-- Info expéditeur -->
                    <div class="flex items-start gap-3">
                        <div
                            class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold">
                            {{ strtoupper(substr($selectedMessage['from'] ?? '', 0, 1)) }}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-900">
                                    {{ preg_match('/^([^<]+)/', $selectedMessage['from'] ?? '', $nameMatch) ? trim($nameMatch[1]) : ($selectedMessage['from'] ?? '-') }}
                                </span>
                                @if(!empty($selectedMessage['attachments']))
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        {{ count($selectedMessage['attachments']) }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-500">
                                <span>À: test test</span>
                                <span>•</span>
                                <span>{{ isset($selectedMessage['date']) ? \Carbon\Carbon::parse($selectedMessage['date'])->format('d/m/Y H:i') : '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenu du message -->
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <!-- Message original -->
                    <div class="prose max-w-none text-gray-700">
                        {!! $this->formatMessage($selectedMessage['message'] ?? '') !!}
                    </div>

                    <!-- Message traduit -->
                    @if($translatedMessage)
                        <div class="mt-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-r">
                            <div class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                                </svg>
                                <div class="flex-1">
                                    <strong class="text-yellow-800 text-sm font-semibold">Traduction :</strong>
                                    <div class="mt-2 text-yellow-900 prose max-w-none">{!! $translatedMessage !!}</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Pièces jointes -->
                    @if(!empty($selectedMessage['attachments']))
                        <div class="mt-6 border-t border-gray-200 pt-6">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
                                <svg class="h-5 w-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                Pièces jointes ({{ count($selectedMessage['attachments']) }})
                            </h4>

                            <div class="grid grid-cols-1 gap-2">
                                @foreach($selectedMessage['attachments'] as $attachmentIndex => $attachment)
                                    @php
                $filename = $attachment['filename'] ?? 'Fichier sans nom';
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $mimeType = $attachment['mimeType'] ?? '';

                [$iconColor, $bgColor] = match (true) {
                    $extension === 'pdf' || str_contains($mimeType, 'pdf') =>
                    ['text-red-600', 'bg-red-50'],
                    in_array($extension, ['doc', 'docx']) || str_contains($mimeType, 'word') =>
                    ['text-blue-600', 'bg-blue-50'],
                    in_array($extension, ['xls', 'xlsx']) || str_contains($mimeType, 'spreadsheet') =>
                    ['text-green-600', 'bg-green-50'],
                    in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) || str_contains($mimeType, 'image') =>
                    ['text-purple-600', 'bg-purple-50'],
                    in_array($extension, ['zip', 'rar', '7z', 'tar']) =>
                    ['text-yellow-600', 'bg-yellow-50'],
                    default => ['text-gray-600', 'bg-gray-50']
                };
                                    @endphp

                                    <div
                                        class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                        <div class="flex items-center flex-1 min-w-0">
                                            <div class="flex-shrink-0 {{ $bgColor }} rounded-lg p-2.5 {{ $iconColor }}">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                            </div>

                                            <div class="ml-3 flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">{{ $filename }}</p>
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    {{ $this->formatFileSize($attachment['size'] ?? 0) }}
                                                    @if($extension)
                                                        <span class="mx-1.5">•</span>
                                                        <span class="uppercase">{{ $extension }}</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>

                                        <button type="button"
                                            wire:click="downloadAttachment({{ $selectedMessageIndex }}, {{ $attachmentIndex }})"
                                            wire:loading.attr="disabled"
                                            class="ml-4 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-md transition-colors disabled:opacity-50">
                                            <svg wire:loading.remove wire:target="downloadAttachment" class="h-4 w-4" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <svg wire:loading wire:target="downloadAttachment" class="animate-spin h-4 w-4" fill="none"
                                                viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                    stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                            <span wire:loading.remove wire:target="downloadAttachment">Télécharger</span>
                                            <span wire:loading wire:target="downloadAttachment">...</span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Footer avec actions -->
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="translateMessage" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                            <svg wire:loading.remove wire:target="translateMessage" class="w-4 h-4" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                            </svg>
                            <svg wire:loading wire:target="translateMessage" class="animate-spin w-4 h-4" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span>Traduire en français</span>
                        </button>

                        @if(count($ticketDetails['conversation']['messages'] ?? []) > 0)
                        <button wire:click="replyFirstMessage" type="button"
                           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                            </svg>
                            Répondre
                        </button>
                    @endif
                    </div>
                </div>
            @else
                <!-- État vide -->
                <div class="flex-1 flex items-center justify-center p-8">
                    <div class="text-center">
                        <svg class="w-24 h-24 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Sélectionnez un message</h3>
                        <p class="text-sm text-gray-500">Choisissez un message dans la liste pour afficher son contenu</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

@endif
            @if($activeTab === 'sendmail')
                <div class="mx-auto w-full">



                    <div>
                        <x-header title="Repondre" separator />
                    
                        <!-- Grid stuff from Tailwind -->
                        <div class="grid gap-5 lg:grid-cols-2"> 
                            <div>
                                <x-form wire:submit="reply">
                                    <x-input label="Destinateur" wire:model="destinateur" placeholder="Destinateur" icon="o-user"
                                        hint="Le client qui va recevoire l'email" readonly />
                                    <x-markdown wire:model="message_txt" label="Message" />
                                    <x-slot:actions>
                                        <x-button label="Annuler" />
                                        <x-button icon="o-language" class="btn-circle btn-outline btn-accent" wire:click="translateOpenAI"
                                                spinner="translateOpenAI" />
                                        <x-button icon="o-paper-airplane" class="btn-circle btn-outline btn-primary" type="submit"
                                                spinner="reply" />
                                    </x-slot:actions>
                                </x-form>
                            </div>  
                            <div>
                                {{-- Get a nice picture from `StorySet` web site --}}
                                {{-- <img src="/edit-form.png" width="300" class="mx-auto" /> --}}
                                <x-file wire:model="photos" label="Attachements" multiple />
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($activeTab === 'commentaire')

                <div class="mx-auto max-w-5xl">
                    <ul role="list" class="divide-y divide-gray-100">
                        @foreach ($ticketDetails['comment'] as $comment)
                            <li class="py-4">
                                <div class="flex items-center gap-x-3">
                                    {{-- Avatar par défaut ou personnalisé --}}
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode($comment['user']['name'] ?? 'U') }}&background=random"
                                        alt="avatar" class="size-6 flex-none rounded-full bg-gray-800">

                                    {{-- Auteur du commentaire --}}
                                    <h3 class="flex-auto truncate text-sm font-semibold text-gray-900">
                                        {{ $comment['user']['name'] ?? 'Utilisateur' }}
                                    </h3>

                                    {{-- Date de publication --}}
                                    <time datetime="{{ $comment['created_at'] }}" class="flex-none text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($comment['created_at'])->diffForHumans() }}
                                    </time>
                                </div>

                                {{-- Contenu du commentaire --}}
                                <p class="mt-3 text-sm text-gray-600">
                                    {{ $comment['comment'] }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </div>

            @endif

    </div>



</div>