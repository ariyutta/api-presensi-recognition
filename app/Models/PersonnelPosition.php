<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonnelPosition extends Model
{
    use HasFactory;
    protected $table = 'personnel_position';
    protected $guarded = [];
}
