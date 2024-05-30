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
            $punches = $punches->whereHas('pegawai', function ($pegawai) use ($request) {
                $pegawai->whereHas('department', function ($unit) use ($request) {
                    $unit->where('dept_code', $request->department_id);
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
                $employeeUsername = $employee->last_name;
                $employeeNIP = $employee->nickname;
                $department = $employee->department->dept_name;
                $departmentCode = $employee->department->dept_code;

                if (!isset($employeeData[$dateKey][$empCode])) {
                    $employeeData[$dateKey][$empCode] = [
                        'nip' => $employeeNIP,
                        'username' => $employeeUsername,
                        'nama_pegawai' => $employeeName,
                        'unit_departement' => $department,
                        'kode_unit' => $departmentCode,
                        'tanggal' => $punchTime->format('Y-m-d'),
                        'jam_keluar' => $punchTime->format('Y-m-d H:i:s'),
                        'jam_masuk' => $punchTime->format('Y-m-d H:i:s'),
                    ];
                } else {
                    $employeeData[$dateKey][$empCode]['jam_masuk'] = $punchTime->format('Y-m-d H:i:s');
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
    }

    function index_dawai(Request $request)
    {
        // Mendapatkan tahun dan bulan dari request
        $tahun = $request->set_tahun;
        $bulan = $request->set_bulan;

        // Menghitung jumlah hari dalam bulan yang diminta
        $jumlahHari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

        // Membentuk tanggal awal dan akhir
        $tanggalAwal = "$tahun-$bulan-01 00:00:00";
        $tanggalAkhir = "$tahun-$bulan-$jumlahHari 23:59:59";

        $punches = IClockTransaction::orderBy('punch_time', 'desc');

        if ($request->username) {
            $punches = $punches->whereHas('pegawai', function ($q) use ($request) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->username) . '%'])
                    ->orWhere('nickname', 'LIKE', '%' . $request->username . '%');
            });
        }

        if ($request->department_id) {
            $punches = $punches->whereHas('pegawai', function ($pegawai) use ($request) {
                $pegawai->whereHas('department', function ($unit) use ($request) {
                    $unit->where('dept_code', $request->department_id);
                });
            });
        }

        if ($tanggalAwal != null && $tanggalAkhir != null) {
            $punches = $punches->whereBetween('punch_time', [$tanggalAwal, $tanggalAkhir]);
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
                $employeeNIP = $employee->nickname;
                $department = $employee->department->dept_name;
                $departmentCode = $employee->department->dept_code;

                if (!isset($employeeData[$empCode])) {
                    $employeeData[$empCode] = [
                        'nama_pegawai' => $employeeName,
                        'total_absen' => 0,
                        'absen' => []
                    ];
                }

                if (!isset($employeeData[$empCode]['absen'][$dateKey])) {
                    $employeeData[$empCode]['absen'][$dateKey] = [
                        'tanggal' => $dateKey,
                        'data' => []
                    ];
                    $employeeData[$empCode]['total_absen'] += 1;
                }

                $employeeData[$empCode]['absen'][$dateKey]['data'][] = [
                    'nip' => $employeeNIP,
                    'username' => $employeeUsername,
                    'nama_pegawai' => $employeeName,
                    'unit_departement' => $department,
                    'kode_unit' => $departmentCode,
                    'tanggal' => $dateKey,
                    'jam_keluar' => $punchTime->format('Y-m-d H:i:s'),
                    'jam_masuk' => $punchTime->format('Y-m-d H:i:s')
                ];
            }
        }

        $finalOutput = [];

        foreach ($employeeData as $empCode => $data) {
            $formattedAbsen = [];
            foreach ($data['absen'] as $absenData) {
                $formattedAbsen[] = $absenData;
            }

            $finalOutput[] = [
                'nama_pegawai' => $data['nama_pegawai'],
                'total_absen' => $data['total_absen'],
                'absen' => $formattedAbsen
            ];
        }

        return response()->json($finalOutput);
    }

    function transaksi_kehadiran()
    {
        $strSQL = IClockTransaction::select('id', 'emp_code', 'punch_time', 'terminal_alias', 'area_alias')->get();

        return response()->json($strSQL);
    }

    function store_masuk(Request $request)
    {
        $punches = [];

        foreach ($request->all() as $punchData) {
            // Menentukan jam acak berdasarkan hari dalam seminggu
            $dayOfWeek = date('N', strtotime($punchData['punch_time'])); // Mendapatkan hari dalam seminggu (1 untuk Senin, 2 untuk Selasa, dst.)

            // Jam masuk pada hari Senin - Kamis
            if ($dayOfWeek >= 1 && $dayOfWeek <= 4) {
                $randomHour = str_pad(random_int(6, 6), 2, '0', STR_PAD_LEFT); // Jam antara 07:00 - 07:30
                $randomMinute = str_pad(random_int(0, 30), 2, '0', STR_PAD_LEFT); // Menit antara 00 - 30
            }
            // Jam masuk pada hari Jumat
            elseif ($dayOfWeek === 5) { // Jumat
                $randomHour = str_pad(random_int(7, 7), 2, '0', STR_PAD_LEFT); // Jam antara 09:00 - 09:30
                $randomMinute = str_pad(random_int(0, 30), 2, '0', STR_PAD_LEFT); // Menit antara 00 - 30
            }
            // Jam masuk pada hari lainnya (Sabtu - Minggu)
            else {
                $randomHour = str_pad(random_int(9, 9), 2, '0', STR_PAD_LEFT); // Jam antara 09:00 - 09:30
                $randomMinute = str_pad(random_int(0, 30), 2, '0', STR_PAD_LEFT); // Menit antara 00 - 30
            }

            $randomSecond = str_pad(random_int(0, 59), 2, '0', STR_PAD_LEFT); // Detik antara 00 - 59
            $randomMicrosecond = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT); // Mikrodetik antara 000000 - 999999

            $randomTime = "$randomHour:$randomMinute:$randomSecond";


            $punch = IClockTransaction::create([
                'emp_code' => $punchData['emp_code'],
                'punch_time' => $punchData['punch_time'] . ' ' . $randomTime . '-07', // Tanggal tetap, waktu acak,
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
                $randomHour = str_pad(random_int(16, 16), 2, '0', STR_PAD_LEFT); // Jam antara 16:00 - 17:00
                $randomMinute = str_pad(random_int(0, 59), 2, '0', STR_PAD_LEFT); // Menit antara 00 - 59
            }
            // Jam pulang pada hari Jumat
            else if ($dayOfWeek === 5) { // Jumat
                $randomHour = str_pad(random_int(17, 17), 2, '0', STR_PAD_LEFT); // Jam antara 17:00 - 18:00
                $randomMinute = str_pad(random_int(0, 59), 2, '0', STR_PAD_LEFT); // Menit antara 00 - 59
            }
            // Jam pulang pada hari lainnya (Sabtu - Minggu)
            else {
                // Dapat menyesuaikan ini sesuai kebutuhan, misalnya, tidak ada jam pulang pada hari Sabtu - Minggu
                $randomHour = '00';
                $randomMinute = '00';
            }

            $randomSecond = str_pad(random_int(0, 59), 2, '0', STR_PAD_LEFT); // Detik antara 00 - 59
            $randomMicrosecond = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT); // Mikrodetik antara 000000 - 999999

            $randomTime = "$randomHour:$randomMinute:$randomSecond";

            $punch = IClockTransaction::create([
                'emp_code' => $punchData['emp_code'],
                'punch_time' => $punchData['punch_time'] . ' ' . $randomTime . '-07', // Tanggal tetap, waktu acak,
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
