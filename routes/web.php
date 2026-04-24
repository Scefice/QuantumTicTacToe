<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\OnlineRoomController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameController::class, 'home'])->name('home');
Route::get('/game', [GameController::class, 'game'])->name('game');
Route::get('/play-online', [OnlineRoomController::class, 'index'])->name('rooms.index');
Route::post('/play-online/create', [OnlineRoomController::class, 'create'])->name('rooms.create');
Route::post('/play-online/join', [OnlineRoomController::class, 'join'])->name('rooms.join');
Route::get('/play-online/{room:code}', [OnlineRoomController::class, 'show'])->name('rooms.show');
Route::get('/play-online/{room:code}/state', [OnlineRoomController::class, 'state'])->name('rooms.state');
Route::post('/play-online/{room:code}/pick', [OnlineRoomController::class, 'pick'])->name('rooms.pick');
Route::post('/play-online/{room:code}/reset-board', [OnlineRoomController::class, 'resetBoard'])->name('rooms.reset-board');
Route::post('/play-online/{room:code}/next-round', [OnlineRoomController::class, 'nextRound'])->name('rooms.next-round');
Route::post('/play-online/{room:code}/reset-match', [OnlineRoomController::class, 'resetMatch'])->name('rooms.reset-match');

Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
Route::get('/tournaments/create', [TournamentController::class, 'create'])->name('tournaments.create');
Route::post('/tournaments', [TournamentController::class, 'store'])->name('tournaments.store');
Route::post('/tournaments/join', [TournamentController::class, 'joinByCode'])->name('tournaments.join');
Route::get('/tournaments/{tournament:slug}', [TournamentController::class, 'show'])->name('tournaments.show');
Route::get('/tournaments/{tournament:slug}/participant-state', [TournamentController::class, 'participantState'])->name('tournaments.participant-state');
Route::get('/tournaments/{tournament:slug}/play', [TournamentController::class, 'play'])->name('tournaments.play');
Route::post('/tournaments/{tournament:slug}/participants', [TournamentController::class, 'addParticipant'])->name('tournaments.participants.store');
Route::post('/tournaments/{tournament:slug}/start', [TournamentController::class, 'start'])->name('tournaments.start');
Route::post('/tournaments/{tournament:slug}/next-round', [TournamentController::class, 'nextRound'])->name('tournaments.next-round');
Route::post('/tournaments/{tournament:slug}/matches/{match}/result', [TournamentController::class, 'recordResult'])->name('tournaments.matches.result');
Route::post('/tournaments/{tournament:slug}/participants/{participant}/drop', [TournamentController::class, 'dropParticipant'])->name('tournaments.participants.drop');
Route::post('/tournaments/{tournament:slug}/follow-up', [TournamentController::class, 'createFollowUp'])->name('tournaments.follow-up');
