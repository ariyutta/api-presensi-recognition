<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonnelEmployeeArea extends Model
{
    use HasFactory;
    protected $table = 'personnel_employee_area';
    protected $guarded = [];

    public function area()
    {
        return $this->belongsTo(PersonnelArea::class, 'area_id', 'id');
    }

    public function pegawai()
    {
        return $this->belongsTo(PersonnelEmployee::class, 'employee_id', 'id');
    }
}
