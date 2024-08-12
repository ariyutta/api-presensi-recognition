<?php

use App\Http\Controllers\API\CetakController;
use App\Http\Controllers\API\KehadiranController;
use App\Http\Controllers\API\PegawaiController;
use App\Http\Controllers\API\UnitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/', [KehadiranController::class, 'index'])->name('index');

Route::get('/getKehadiran', [KehadiranController::class, 'index'])->name('index');
Route::get('/getKehadiranV2', [KehadiranController::class, 'transaksi_kehadiran'])->name('index');
Route::get('/get-kehadiran-dawai', [KehadiranController::class, 'index_dawai'])->name('index_dawai');
Route::get('/getPegawai', [PegawaiController::class, 'index'])->name('index');
Route::get('/getUnit', [UnitController::class, 'index'])->name('index');
Route::get('/getUnit/{id}', [UnitController::class, 'detail'])->name('index');
Route::get('/getNIH', [PegawaiController::class, 'getNIH'])->name('getNIH');

Route::prefix('kehadiran')->name('kehadiran.')->group(function () {
    Route::post('/store-jam-masuk', [KehadiranController::class, 'store_masuk'])->name('store_masuk');
    Route::post('/store-jam-keluar', [KehadiranController::class, 'store_keluar'])->name('store_keluar');
    Route::post('/store-manual-unit', [KehadiranController::class, 'absen_manual_per_unit'])->name('absen_manual_per_unit');
});

Route::prefix('cetak')->name('cetak.')->group(function () {
    Route::post('/kehadiran-tendik', [CetakController::class, 'kehadiran_tendik'])->name('kehadiran_tendik');
});
