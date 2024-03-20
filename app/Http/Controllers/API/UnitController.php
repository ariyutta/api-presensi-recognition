<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PersonnelDepartment;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    function index()
    {
        $unit = PersonnelDepartment::select('dept_code as unit_id', 'dept_name as unit_nama')->where('id', '!=', 1)->orderBy('dept_name', 'asc')->get();

        return response()->json($unit);
    }

    function detail($idUnit)
    {
        $strSQL = PersonnelDepartment::with(['pegawai' => function ($q) {
            $q->select('id', 'emp_code', 'first_name', 'photo', 'department_id');
            return $q;
        }])->where('id', $idUnit)->get();

        return response()->json($strSQL);
    }
}
