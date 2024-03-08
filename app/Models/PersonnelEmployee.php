<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonnelEmployee extends Model
{
    use HasFactory;
    protected $table = 'personnel_employee';
    protected $guarded = [];

    public function department()
    {
        return $this->belongsTo(PersonnelDepartment::class, 'department_id', 'id');
    }

    public function position()
    {
        return $this->belongsTo(PersonnelPosition::class, 'position_id', 'id');
    }

    public function area_pegawai()
    {
        return $this->hasMany(PersonnelEmployeeArea::class, 'employee_id', 'id');
    }
}
