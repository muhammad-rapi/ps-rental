<?php

use App\Http\Controllers\PerangkatController;
use App\Http\Controllers\TransaksiController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::post('/transaksi/mulai', [TransaksiController::class, 'prosesMulai'])->name('transaksi.mulai');
Route::get('/perangkat', [PerangkatController::class, 'index'])->name('perangkat.index');
Route::get('/transaksi/mulai/{perangkat}', [TransaksiController::class, 'mulaiForm'])->name('transaksi.mulai_form');
Route::post('/transaksi/proses-mulai', [TransaksiController::class, 'prosesMulai'])->name('transaksi.proses_mulai');

