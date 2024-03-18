<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PegawaiReference;
use App\Models\PersonnelEmployee;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    function index()
    {
        $strSQL = PersonnelEmployee::select('id', 'emp_code', 'first_name', 'photo', 'hire_date', 'department_id', 'position_id')->orderBy('first_name', 'asc')->get();

        return response()->json($strSQL);
    }

    function getNIH(Request $request)
    {
        $data = PegawaiReference::query();

        if ($request->nama_pegawai != null) {
            $data = $data->whereRaw('LOWER(nama_pegawai) LIKE ?', ['%' . strtolower($request->nama_pegawai) . '%']);
        }
        $data = $data->get();

        return response()->json($data);
    }
}
