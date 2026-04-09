<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/login');
});

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

Route::get('/charges/{charge}', function (string $charge) {
    return Inertia::render('Charges/Show', ['id' => $charge]);
})->name('charges.show');
