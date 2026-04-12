<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');

Route::get('/minhas-despesas', function () {
    return Inertia::render('MyExpenses');
})->name('my-expenses');

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login');

Route::get('/register', function () {
    return Inertia::render('Auth/Register');
})->name('register');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

Route::get('/teams', function () {
    return Inertia::render('Teams/Index');
})->name('teams.index');

Route::get('/teams/create', function () {
    return Inertia::render('Teams/Create');
})->name('teams.create');

Route::get('/teams/{team}', function (string $team) {
    return Inertia::render('Teams/Show', ['id' => $team]);
})->name('teams.show');

Route::get('/teams/{team}/expenses/create', function (string $team) {
    return Inertia::render('Expenses/Create', ['teamId' => $team]);
})->name('expenses.create');

Route::get('/teams/{team}/expenses/{expense}', function (string $team, string $expense) {
    return Inertia::render('Expenses/Show', ['teamId' => $team, 'id' => $expense]);
})->name('expenses.show');

Route::get('/public/expenses/{hash}', function (Request $request, string $hash) {
    if (! $request->has('manage')) {
        return redirect("/p/{$hash}", 302);
    }

    return Inertia::render('Public/ExpenseDashboard', [
        'hash' => $hash,
        'manage' => $request->query('manage'),
    ]);
})->name('public.expense');

Route::get('/p/{expenseHash}/{participantHash}', function (string $expenseHash, string $participantHash) {
    return Inertia::render('Public/Participant', [
        'expenseHash' => $expenseHash,
        'participantHash' => $participantHash,
    ]);
})->name('public.participant.invite');

Route::get('/p/{hash}', function (string $hash) {
    return Inertia::render('Public/ParticipantEntry', [
        'hash' => $hash,
    ]);
})->name('public.participant');
