<?php

use App\Http\Controllers\VoiceApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.key')->group(function () {
    Route::get('/branches', [VoiceApiController::class, 'branches']);
    Route::get('/summary',  [VoiceApiController::class, 'summary']);
    Route::get('/expenses', [VoiceApiController::class, 'expenses']);
    Route::get('/sales',    [VoiceApiController::class, 'sales']);
});
