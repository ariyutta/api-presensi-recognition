<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonnelDepartment extends Model
{
    use HasFactory;
    protected $table = 'personnel_department';
    protected $guarded = [];

    public function pegawai()
    {
        return $this->hasMany(PersonnelEmployee::class, 'department_id');
    }
}
