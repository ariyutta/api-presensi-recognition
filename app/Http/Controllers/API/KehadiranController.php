<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\IClockTransaction;
use App\Models\PersonnelEmployee;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KehadiranController extends Controller
{
    function index(Request $request)
    {
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

        $punches = IClockTransaction::orderBy('punch_time', 'desc');

        if ($request->username) {
            $punches = $punches->whereHas('pegawai', function ($q) use ($request) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->username) . '%'])
                    ->orWhere('last_name', 'LIKE', '%' . $request->username . '%');
            });
        }

        if ($request->department_id) {
            $punches = $punches->whereHas('pegawai', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
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
                $employeeUsername = $employee->last_name;
                $department = $employee->department->dept_name;

                if (!isset($employeeData[$dateKey][$empCode])) {
                    $employeeData[$dateKey][$empCode] = [
                        'username' => $employeeUsername,
                        'nama_pegawai' => $employeeName,
                        'unit_departement' => $department,
                        'tanggal' => $punchTime->format('Y-m-d'),
                        'jam_keluar' => $punchTime->format('Y-m-d H:i:s'),
                        'jam_masuk' => $punchTime->format('Y-m-d H:i:s'),
                    ];
                } else {
                    $employeeData[$dateKey][$empCode]['jam_masuk'] = $punchTime->format('Y-m-d H:i:s');
                }
            }
        }

        foreach ($employeeData as &$dateData) {
            $dateData = array_values($dateData);
        }
        // $employeeData = array_values($employeeData);
        return response()->json($employeeData);
    }

    // private function getShiftId($time)
    // {
    //     $shift_id = null;
    //     $hour = $time->hour;

    //     if ($hour >= 7 && $hour < 14) {
    //         $shift_id = 1;
    //     } elseif ($hour >= 14 && $hour < 19) {
    //         $shift_id = 2;
    //     } else {
    //         $shift_id = 3;
    //     }

    //     return $shift_id;
    // }


    function transaksi_kehadiran()
    {
        $strSQL = IClockTransaction::select('id', 'emp_code', 'punch_time', 'terminal_alias', 'area_alias')->get();

        return response()->json($strSQL);
    }

    function store_masuk(Request $request)
    {
        $punches = [];

        foreach ($request->all() as $punchData) {
            $randomHour = str_pad(rand(8, 8), 2, '0', STR_PAD_LEFT); // Jam antara 07:00 - 07:30
            $randomMinute = str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT); // Menit antara 00 - 30
            $randomSecond = str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT); // Detik antara 00 - 59
            $randomMicrosecond = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT); // Mikrodetik antara 000000 - 999999

            $randomTime = "$randomHour:$randomMinute:$randomSecond";

            $punch = IClockTransaction::create([
                'emp_code' => $punchData['emp_code'],
                'punch_time' => $punchData['punch_time'] . ' ' . $randomTime . '-08', // Tanggal tetap, waktu acak,
                'punch_state' => 0,
                'verify_type' => 15,
                'terminal_sn' => $punchData['terminal_sn'],
                'terminal_alias' => $punchData['terminal_alias'],
                'area_alias' => $punchData['area_alias'],
                'source' => 1,
                'purpose' => 41,
                'crc' => 'CAAACAEAAADAAABAAAIACAFAEAEA',
                'is_attendance' => 1,
                'upload_time' => $punchData['punch_time'] . ' ' . $randomTime . '.' . $randomMicrosecond . '-08',
                'sync_status' => 0,
                'is_mask' => 255,
                'temperature' => 255,
                'emp_id' => $punchData['emp_id'],
                'terminal_id' => $punchData['terminal_id'],
                'company_code' => 1,
            ]);

            $punches[] = $punch;
        }

        // Beri respons berhasil
        return response()->json([
            'message' => 'Data berhasil disimpan'
        ], 200);
    }

    function store_keluar(Request $request)
    {
        // return $request->all();
        $punches = [];

        foreach ($request->all() as $punchData) {
            // Menentukan jam acak berdasarkan hari dalam seminggu
            $dayOfWeek = date('N', strtotime($punchData['punch_time'])); // Mendapatkan hari dalam seminggu (1 untuk Senin, 2 untuk Selasa, dst.)

            // Jam pulang pada hari Senin - Kamis
            if ($dayOfWeek >= 1 && $dayOfWeek <= 4) {
                $randomHour = str_pad(rand(15, 15), 2, '0', STR_PAD_LEFT); // Jam antara 16:00 - 17:00
                $randomMinute = str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT); // Menit antara 00 - 59
            }
            // Jam pulang pada hari Jumat
            elseif ($dayOfWeek == 5) { // Jumat
                $randomHour = str_pad(rand(17, 18), 2, '0', STR_PAD_LEFT); // Jam antara 17:00 - 18:00
                $randomMinute = str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT); // Menit antara 00 - 59
            }
            // Jam pulang pada hari lainnya (Sabtu - Minggu)
            else {
                // Dapat menyesuaikan ini sesuai kebutuhan, misalnya, tidak ada jam pulang pada hari Sabtu - Minggu
                $randomHour = '00';
                $randomMinute = '00';
            }

            $randomSecond = str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT); // Detik antara 00 - 59
            $randomMicrosecond = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT); // Mikrodetik antara 000000 - 999999

            $randomTime = "$randomHour:$randomMinute:$randomSecond";

            $punch = IClockTransaction::create([
                'emp_code' => $punchData['emp_code'],
                'punch_time' => $punchData['punch_time'] . ' ' . $randomTime . '-08', // Tanggal tetap, waktu acak,
                'punch_state' => 1,
                'verify_type' => 15,
                'terminal_sn' => $punchData['terminal_sn'],
                'terminal_alias' => $punchData['terminal_alias'],
                'area_alias' => $punchData['area_alias'],
                'source' => 1,
                'purpose' => 41,
                'crc' => 'CAAACAEAAADAAABAAAIACAFAEAEA',
                'is_attendance' => 1,
                'upload_time' => $punchData['punch_time'] . ' ' . $randomTime . '.' . $randomMicrosecond . '-08',
                'sync_status' => 0,
                'is_mask' => 255,
                'temperature' => 255,
                'emp_id' => $punchData['emp_id'],
                'terminal_id' => $punchData['terminal_id'],
                'company_code' => 1,
            ]);

            $punches[] = $punch;
        }

        // Beri respons berhasil
        return response()->json([
            'message' => 'Data berhasil disimpan'
        ], 200);
    }

    static function presensiOtomatis()
    {
    }
}
