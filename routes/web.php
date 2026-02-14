<?php

use App\Http\Controllers\CodexStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('chat');
})->name('chat');

Route::post('/codex/stream', CodexStreamController::class)->name('codex.stream');
