<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PegawaiReference;
use App\Models\PersonnelDepartment;
use App\Models\PersonnelEmployee;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    function index(Request $request)
    {
        $strSQL = PersonnelEmployee::select('first_name as nama', 'last_name as username', 'nickname as nip', 'department_id as unit_id');

        if ($request->unit_id != null) {
            $strSQL = $strSQL->whereHas('department', function ($q) use ($request) {
                $q->where('dept_code', $request->unit_id);
            });
        }

        $strSQL = $strSQL->orderBy('first_name', 'asc')->get();

        $results = [];

        foreach ($strSQL as $item) {
            $department = PersonnelDepartment::find($item->unit_id);

            if ($department) {
                $unit_nama = $department->dept_name;
                $unit_id = $department->dept_code;
            } else {
                $unit_nama = 'Tidak Diketahui';
                $unit_id = null;
            }

            $result = [
                'nama' => $item->nama,
                'username' => $item->username,
                'nip' => $item->nip,
                'unit_nama' => $unit_nama,
                'unit_id' => $unit_id
            ];

            $results[] = $result;
        }

        return response()->json($results);
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
