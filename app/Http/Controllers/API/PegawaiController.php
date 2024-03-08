<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PersonnelEmployee;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    function index()
    {
        $strSQL = PersonnelEmployee::select('id', 'emp_code', 'first_name', 'photo', 'hire_date', 'department_id', 'position_id')->orderBy('first_name', 'asc')->get();

        return response()->json($strSQL);
    }
}
