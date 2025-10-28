<?php

use App\Livewire\Stat\StatPage;
use Livewire\Volt\Volt;

// login 
Volt::route('/login', 'auth.login')->name('login');
//Volt::route('/', 'users.index');

Route::middleware(['jwt-session-auth'])->group(function () {
    Route::post('logout', App\Livewire\Actions\Logout::class)
    ->name('logout');
    
    Volt::route('/', 'projects.index-page')->name('project.index');
    Volt::route('/project/{id}', 'projects.view-page')->name('project.view');

    // detail ticket
    Volt::route('/ticket/{ticket}/detail', 'tikets.detail')->name('ticket.detail');

        // Routes réservées aux super admin

        Volt::route('/users', 'users.list')->name('users.list');
        Volt::route('/sa-dashboard', 'dashboard.sa-dashboard')->name('dashboard.sa-dashboard');

});