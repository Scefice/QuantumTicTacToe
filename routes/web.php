<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameController::class, 'home'])->name('home');
Route::get('/game', [GameController::class, 'game'])->name('game');
