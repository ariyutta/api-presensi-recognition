<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\IClockTransaction;
use App\Models\PersonnelEmployee;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class CetakController extends Controller
{
    function kehadiran_tendik(Request $request)
    {
        try {
            $periode = explode(' - ', $request->periode);

            if (count($periode) == 2) {
                $startDate = $periode[0];
                $endDate = $periode[1];
            } else if (count($periode) == 1) {
                $startDate = $periode[0];
                $endDate = $periode[0];
            } else {
                $startDate = date('Y-m-d') . ' 00:00:00';
                $endDate =  date('Y-m-d') . ' 23:59:59';
            }

            $punches = IClockTransaction::select('id', 'punch_time', 'emp_id', 'emp_code')->whereHas('pegawai', function ($pegawai) {
                $pegawai->whereHas('position', function ($jenis_pegawai) {
                    $jenis_pegawai->where('id', '!=', 2);
                });
            })->orderBy('punch_time', 'asc');

            if ($request->username) {
                $punches = $punches->whereHas('pegawai', function ($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->username) . '%'])
                        ->orWhere('nickname', 'LIKE', '%' . $request->username . '%');
                });
            }

            if ($request->department_id) {
                $punches = $punches->whereHas('pegawai', function ($pegawai) use ($request) {
                    $pegawai->whereHas('department', function ($unit) use ($request) {
                        $unit->where('id', $request->department_id);
                    });
                });
            }

            if ($startDate != null && $endDate != null) {
                $punches = $punches->whereBetween('punch_time', [$startDate, $endDate]);
            }

            $punches = $punches->get();

            $employeeData = [];

            foreach ($punches as $punch) {
                $empCode = $punch->emp_code;
                $punchTime = Carbon::parse($punch->punch_time);
                $dateKey = $punchTime->toDateString();

                $employee = PersonnelEmployee::where('emp_code', $empCode)->first();

                if ($employee) {
                    $employeeName = $employee->first_name;
                    $employeeNIP = $employee->nickname;
                    $department = $employee->department->dept_name;

                    if (!isset($employeeData[$dateKey][$empCode])) {
                        $employeeData[$dateKey][$empCode] = [
                            'nip' => $employeeNIP,
                            'nama_pegawai' => $employeeName,
                            'unit_departement' => $department,
                            'tanggal' => $punchTime->format('Y-m-d'),
                            'jam_masuk' => $punchTime->format('Y-m-d H:i:s'),
                            'jam_keluar' => $punchTime->format('Y-m-d H:i:s'),
                        ];
                    } else {
                        if ($punchTime->lessThan(Carbon::parse($employeeData[$dateKey][$empCode]['jam_masuk']))) {
                            $employeeData[$dateKey][$empCode]['jam_masuk'] = $punchTime->format('Y-m-d H:i:s');
                        }
                        if ($punchTime->greaterThan(Carbon::parse($employeeData[$dateKey][$empCode]['jam_keluar']))) {
                            $employeeData[$dateKey][$empCode]['jam_keluar'] = $punchTime->format('Y-m-d H:i:s');
                        }
                    }
                }
            }

            $finalOutput = [];

            foreach ($employeeData as $date => $data) {
                $formattedData = [
                    'tanggal' => $date,
                    'data' => array_values($data),
                ];

                $finalOutput[] = $formattedData;
            }

            return response()->json($finalOutput);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
