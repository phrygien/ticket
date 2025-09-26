<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public int $ticketId;
    public array $ticketDetails = [];
    public bool $loading = true;

    public bool $myModal1 = false;

    // ⚡ On reçoit l'ID du ticket depuis la route
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
        }

        $this->loading = false;
    }
}; ?>


<div class="w-full mx-auto p-4">

    <x-header title="Détail du ticket #{{ $ticketId }}" subtitle="Informations complètes" separator>
    </x-header>

    <div class="grid grid-cols-3 gap-3">
        <div>
            @forelse($ticketDetails['conversation']['messages'] ?? [] as $message)


                <div class="bg-white shadow-sm sm:rounded-lg mt-3">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mt-2 max-w-xl text-sm text-gray-500">
                            <p>{!! nl2br(e(strip_tags(\Illuminate\Support\Str::words($message['message'], 15, '...')))) !!}</p>
                        </div>

                        <div>
                            <small>{{ \Carbon\Carbon::parse($message['date'])->format('d/m/Y H:i') }}</small>
                        </div>

                        <div class="mt-3 text-sm/6">
                            <a href="#" class="font-semibold text-indigo-600 hover:text-indigo-500" @click="$wire.myModal1 = true">
                                en savoir plus
                                <span aria-hidden="true"> &rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>


            @empty
                <p>Aucun message trouvé.</p>
            @endforelse            
            </div>

        <x-card>
        </x-card>

        <x-card>

            <ul role="list" class="space-y-6">


                <li class="relative flex gap-x-4">
                    <div class="absolute top-0 -bottom-6 left-0 flex w-6 justify-center">
                        <div class="w-px bg-gray-200"></div>
                    </div>
                    <div class="relative flex size-6 flex-none items-center justify-center bg-white">
                        <div class="size-1.5 rounded-full bg-gray-100 ring-1 ring-gray-300"></div>
                    </div>
                    <p class="flex-auto py-0.5 text-xs/5 text-gray-500"><span class="font-medium text-gray-900">Chelsea
                            Hagon</span> created the invoice.</p>
                    <time datetime="2023-01-23T10:32" class="flex-none py-0.5 text-xs/5 text-gray-500">7d ago</time>
                </li>
                <li class="relative flex gap-x-4">
                    <div class="absolute top-0 -bottom-6 left-0 flex w-6 justify-center">
                        <div class="w-px bg-gray-200"></div>
                    </div>
                    <div class="relative flex size-6 flex-none items-center justify-center bg-white">
                        <div class="size-1.5 rounded-full bg-gray-100 ring-1 ring-gray-300"></div>
                    </div>
                    <p class="flex-auto py-0.5 text-xs/5 text-gray-500"><span class="font-medium text-gray-900">Chelsea
                            Hagon</span> edited the invoice.</p>
                    <time datetime="2023-01-23T11:03" class="flex-none py-0.5 text-xs/5 text-gray-500">6d ago</time>
                </li>
                <li class="relative flex gap-x-4">
                    <div class="absolute top-0 -bottom-6 left-0 flex w-6 justify-center">
                        <div class="w-px bg-gray-200"></div>
                    </div>
                    <div class="relative flex size-6 flex-none items-center justify-center bg-white">
                        <div class="size-1.5 rounded-full bg-gray-100 ring-1 ring-gray-300"></div>
                    </div>
                    <p class="flex-auto py-0.5 text-xs/5 text-gray-500"><span class="font-medium text-gray-900">Chelsea
                            Hagon</span> sent the invoice.</p>
                    <time datetime="2023-01-23T11:24" class="flex-none py-0.5 text-xs/5 text-gray-500">6d ago</time>
                </li>


                <li class="relative flex gap-x-4">
                    <div class="absolute top-0 -bottom-6 left-0 flex w-6 justify-center">
                        <div class="w-px bg-gray-200"></div>
                    </div>
                    <img src="https://images.unsplash.com/photo-1550525811-e5869dd03032?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80"
                        alt="" class="relative mt-3 size-6 flex-none rounded-full bg-gray-50">
                    <div class="flex-auto rounded-md p-3 ring-1 ring-gray-200 ring-inset">
                        <div class="flex justify-between gap-x-4">
                            <div class="py-0.5 text-xs/5 text-gray-500"><span class="font-medium text-gray-900">Chelsea
                                    Hagon</span> commented</div>
                            <time datetime="2023-01-23T15:56" class="flex-none py-0.5 text-xs/5 text-gray-500">3d
                                ago</time>
                        </div>
                        <p class="text-sm/6 text-gray-500">Called client, they reassured me the invoice would be paid by
                            the 25th.</p>
                    </div>
                </li>
                <li class="relative flex gap-x-4">
                    <div class="absolute top-0 -bottom-6 left-0 flex w-6 justify-center">
                        <div class="w-px bg-gray-200"></div>
                    </div>
                    <div class="relative flex size-6 flex-none items-center justify-center bg-white">
                        <div class="size-1.5 rounded-full bg-gray-100 ring-1 ring-gray-300"></div>
                    </div>
                    <p class="flex-auto py-0.5 text-xs/5 text-gray-500"><span class="font-medium text-gray-900">Alex
                            Curren</span> viewed the invoice.</p>
                    <time datetime="2023-01-24T09:12" class="flex-none py-0.5 text-xs/5 text-gray-500">2d ago</time>
                </li>
                <li class="relative flex gap-x-4">
                    <div class="absolute top-0 left-0 flex h-6 w-6 justify-center">
                        <div class="w-px bg-gray-200"></div>
                    </div>
                    <div class="relative flex size-6 flex-none items-center justify-center bg-white">
                        <svg class="size-6 text-indigo-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"
                            data-slot="icon">
                            <path fill-rule="evenodd"
                                d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <p class="flex-auto py-0.5 text-xs/5 text-gray-500"><span class="font-medium text-gray-900">Alex
                            Curren</span> paid the invoice.</p>
                    <time datetime="2023-01-24T09:20" class="flex-none py-0.5 text-xs/5 text-gray-500">1d ago</time>
                </li>
            </ul>

            <!-- New comment form -->
            <div class="mt-6 flex gap-x-3">


            
                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80"
                    alt="" class="size-6 flex-none rounded-full bg-gray-50">
                <form action="#" class="relative flex-auto">
                    <div
                        class="overflow-hidden rounded-lg pb-12 outline-1 -outline-offset-1 outline-gray-300 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600">
                        <label for="comment" class="sr-only">Add your comment</label>
                        <textarea rows="2" name="comment" id="comment"
                            class="block w-full resize-none bg-transparent px-3 py-1.5 text-base text-gray-900 placeholder:text-gray-400 focus:outline-none sm:text-sm/6"
                            placeholder="Add your comment..."></textarea>
                    </div>

                    <div class="absolute inset-x-0 bottom-0 flex justify-between py-2 pr-2 pl-3">
                        <div class="flex items-center space-x-5">
                            <div class="flex items-center">
                                <button type="button"
                                    class="-m-2.5 flex size-10 items-center justify-center rounded-full text-gray-400 hover:text-gray-500">
                                    <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                                        data-slot="icon">
                                        <path fill-rule="evenodd"
                                            d="M15.621 4.379a3 3 0 0 0-4.242 0l-7 7a3 3 0 0 0 4.241 4.243h.001l.497-.5a.75.75 0 0 1 1.064 1.057l-.498.501-.002.002a4.5 4.5 0 0 1-6.364-6.364l7-7a4.5 4.5 0 0 1 6.368 6.36l-3.455 3.553A2.625 2.625 0 1 1 9.52 9.52l3.45-3.451a.75.75 0 1 1 1.061 1.06l-3.45 3.451a1.125 1.125 0 0 0 1.587 1.595l3.454-3.553a3 3 0 0 0 0-4.242Z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <span class="sr-only">Attach a file</span>
                                </button>
                            </div>
                            <div class="flex items-center">
                                <div>
                                    <label id="listbox-label" class="sr-only">Your mood</label>
                                    <div class="relative">
                                        <button type="button"
                                            class="relative -m-2.5 flex size-10 items-center justify-center rounded-full text-gray-400 hover:text-gray-500"
                                            aria-haspopup="listbox" aria-expanded="true"
                                            aria-labelledby="listbox-label">
                                            <span class="flex items-center justify-center">
                                                <!-- Placeholder label, show/hide based on listbox state. -->
                                                <span>
                                                    <svg class="size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"
                                                        aria-hidden="true" data-slot="icon">
                                                        <path fill-rule="evenodd"
                                                            d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.536-4.464a.75.75 0 1 0-1.061-1.061 3.5 3.5 0 0 1-4.95 0 .75.75 0 0 0-1.06 1.06 5 5 0 0 0 7.07 0ZM9 8.5c0 .828-.448 1.5-1 1.5s-1-.672-1-1.5S7.448 7 8 7s1 .672 1 1.5Zm3 1.5c.552 0 1-.672 1-1.5S12.552 7 12 7s-1 .672-1 1.5.448 1.5 1 1.5Z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                    <span class="sr-only">Add your mood</span>
                                                </span>
                                                <!-- Selected item label, show/hide based on listbox state. -->
                                                <span>
                                                    <span
                                                        class="flex size-8 items-center justify-center rounded-full bg-red-500">
                                                        <svg class="size-5 shrink-0 text-white" viewBox="0 0 20 20"
                                                            fill="currentColor" aria-hidden="true" data-slot="icon">
                                                            <path fill-rule="evenodd"
                                                                d="M13.5 4.938a7 7 0 1 1-9.006 1.737c.202-.257.59-.218.793.039.278.352.594.672.943.954.332.269.786-.049.773-.476a5.977 5.977 0 0 1 .572-2.759 6.026 6.026 0 0 1 2.486-2.665c.247-.14.55-.016.677.238A6.967 6.967 0 0 0 13.5 4.938ZM14 12a4 4 0 0 1-4 4c-1.913 0-3.52-1.398-3.91-3.182-.093-.429.44-.643.814-.413a4.043 4.043 0 0 0 1.601.564c.303.038.531-.24.51-.544a5.975 5.975 0 0 1 1.315-4.192.447.447 0 0 1 .431-.16A4.001 4.001 0 0 1 14 12Z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                    <span class="sr-only">Excited</span>
                                                </span>
                                            </span>
                                        </button>

                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit"
                            class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 ring-1 shadow-xs ring-gray-300 ring-inset hover:bg-gray-50">Comment</button>
                    </div>
                </form>
            </div>


        </x-card>

    </div>



    <x-modal wire:model="myModal1" title="Hey" class="backdrop-blur">
        Press `ESC`, click outside or click `CANCEL` to close.
    
        <x-slot:actions>
            <x-button label="Cancel" @click="$wire.myModal1 = false" />
        </x-slot:actions>
    </x-modal>

</div>