<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// routes/api.php
// Route::middleware('auth:sanctum')->group(function () {
    Route::get('/transaksi/active', [App\Http\Controllers\TransaksiController::class, 'getActiveTransactions']);
    Route::post('/transaksi/{perangkat}/pause', [App\Http\Controllers\TransaksiController::class, 'pauseSesi']);
    Route::post('/transaksi/{perangkat}/resume', [App\Http\Controllers\TransaksiController::class, 'resumeSesi']);
    Route::post('/transaksi/{perangkat}/stop', [App\Http\Controllers\TransaksiController::class, 'stopSesi']);
// });