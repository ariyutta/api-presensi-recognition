<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PegawaiReference extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_second';
    protected $table = 'pegawai_refrence';
    protected $guarded = [];
}
