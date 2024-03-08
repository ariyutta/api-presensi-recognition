<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\KehadiranController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PresensiOtomatis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'presensi:otomatis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fungsi Presensi Otomatis dari controlelr';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            //code...
            $data = DB::table('user')->where('unit', 'upt tik')->get();
            foreach ($data as $key => $value) {
                # code...
                KehadiranController::presensiOtomatis();
            }

            $this->info('sukses');
        } catch (\Throwable $th) {
            $this->info('failed');
        }
    }
}
