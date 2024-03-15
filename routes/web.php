<?php

use App\Http\Controllers\API\KehadiranController;
use App\Http\Controllers\API\PegawaiController;
use App\Http\Controllers\API\UnitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', [KehadiranController::class, 'index'])->name('index');

Route::get('/getKehadiran', [KehadiranController::class, 'index'])->name('index');
Route::get('/getKehadiranV2', [KehadiranController::class, 'transaksi_kehadiran'])->name('index');
Route::get('/getPegawai', [PegawaiController::class, 'index'])->name('index');
Route::get('/getUnit', [UnitController::class, 'index'])->name('index');
Route::get('/getUnit/{id}', [UnitController::class, 'detail'])->name('index');
