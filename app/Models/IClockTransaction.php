<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IClockTransaction extends Model
{
    use HasFactory;
    protected $table = 'iclock_transaction';
    protected $guarded = [];

    public $timestamps = false;

    public function pegawai()
    {
        return $this->belongsTo(PersonnelEmployee::class, 'emp_code', 'emp_code');
    }

    public function firstPunch()
    {
        return IClockTransaction::where('emp_code', $this->emp_code)->orderBy('punch_time')->first()->punch_time;
    }

    public function lastPunch()
    {
        return IClockTransaction::where('emp_code', $this->emp_code)->orderByDesc('punch_time')->first()->punch_time;
    }

    public function totalTime()
    {
        $firstPunch = Carbon::parse($this->firstPunch());
        $lastPunch = Carbon::parse($this->lastPunch());

        return $lastPunch->diffForHumans($firstPunch, ['parts' => 2]);
    }
}
