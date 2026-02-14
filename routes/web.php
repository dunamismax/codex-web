<?php

use App\Http\Controllers\CodexDirectoryController;
use App\Http\Controllers\CodexStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('chat');
})->name('chat');

Route::post('/codex/stream', CodexStreamController::class)->name('codex.stream');
Route::get('/codex/directories', CodexDirectoryController::class)->name('codex.directories');
