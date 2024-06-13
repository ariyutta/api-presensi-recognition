<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\IClockTransaction;
use App\Models\PersonnelEmployee;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        try {
            $tahun = $request->set_tahun;
            $bulan = $request->set_bulan;

            $jumlahHari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

            $tanggalAwal = "$tahun-$bulan-01 00:00:00";
            $tanggalAkhir = "$tahun-$bulan-$jumlahHari 23:59:59";

            $punches = IClockTransaction::orderBy('punch_time', 'asc');

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

                    $dayOfWeek = $punchTime->dayOfWeek;
                    $isFastingMonth = $this->isRamadan($punchTime);

                    if ($dayOfWeek == Carbon::FRIDAY) {
                        $jenisAbsen = $isFastingMonth ? 'normal_jumat_puasa' : 'normal_jumat';
                    } else {
                        $jenisAbsen = $isFastingMonth ? 'normal_puasa' : 'normal';
                    }

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
                            'nip' => $employeeNIP,
                            'username' => $employeeUsername,
                            'nama_pegawai' => $employeeName,
                            'unit_departement' => $department,
                            'kode_unit' => $departmentCode,
                            'jam_masuk' => $punchTime->format('Y-m-d H:i:s'),
                            'jam_keluar' => $punchTime->format('Y-m-d H:i:s'),
                            'jenis_absen' => $jenisAbsen
                        ];
                        $employeeData[$empCode]['total_absen'] += 1;
                    } else {
                        if ($punchTime->lessThan(Carbon::parse($employeeData[$empCode]['absen'][$dateKey]['jam_masuk']))) {
                            $employeeData[$empCode]['absen'][$dateKey]['jam_masuk'] = $punchTime->format('Y-m-d H:i:s');
                        }
                        if ($punchTime->greaterThan(Carbon::parse($employeeData[$empCode]['absen'][$dateKey]['jam_keluar']))) {
                            $employeeData[$empCode]['absen'][$dateKey]['jam_keluar'] = $punchTime->format('Y-m-d H:i:s');
                        }
                    }
                }
            }

            $finalOutput = [];

            foreach ($employeeData as $empCode => $data) {
                $formattedAbsen = [];
                foreach ($data['absen'] as $absenData) {
                    $formattedAbsen[] = [
                        'tanggal' => $absenData['tanggal'],
                        'data' => [[
                            'nip' => $absenData['nip'],
                            'username' => $absenData['username'],
                            'nama_pegawai' => $absenData['nama_pegawai'],
                            'unit_departement' => $absenData['unit_departement'],
                            'kode_unit' => $absenData['kode_unit'],
                            'tanggal' => $absenData['tanggal'],
                            'jam_keluar' => $absenData['jam_keluar'],
                            'jam_masuk' => $absenData['jam_masuk'],
                            'jenis_absen' => $absenData['jenis_absen']
                        ]]
                    ];
                }

                $finalOutput[] = [
                    'nama_pegawai' => $data['nama_pegawai'],
                    'total_absen' => $data['total_absen'],
                    'absen' => $formattedAbsen
                ];
            }

            return response()->json($finalOutput);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => $e->getMessage()
            ], 500);
        }
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
            $randomMicrosecond = str_pad(random_int(111111, 999999), 6, '0', STR_PAD_LEFT); // Mikrodetik antara 000000 - 999999

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

    function isRamadan($date)
    {
        $ramadanStart = Carbon::createFromDate($date->year, 3, 11);
        $ramadanEnd = Carbon::createFromDate($date->year, 4, 9);
        return $date->between($ramadanStart, $ramadanEnd);
    }

    function absen_manual_per_unit(Request $request)
    {
        // $request = request();

        // DB::beginTransaction();
        // try {
        //     $employees = PersonnelEmployee::select('id', 'emp_code as id_senja', 'nickname as nip', 'first_name as nama_pegawai')
        //         ->whereHas('department', function ($q) use ($request) {
        //             $q->where('dept_code', $request->department_id);
        //         });

        //     if ($request->nip != null) {
        //         $employees = $employees->whereIn('nickname', $request->nip);
        //     }

        //     $employees = $employees->get();

        //     foreach ($employees as $employee) {
        //         $randomTime = Carbon::today()->addHours(random_int($request->jam, $request->jam))
        //             ->addMinutes(random_int(0, 30))
        //             ->addSeconds(random_int(0, 59))
        //             ->toTimeString();

        //         IClockTransaction::create([
        //             'emp_code'        => $employee->id_senja,
        //             'punch_time'      => $request->tanggal . ' ' . $randomTime . '-07',
        //             'punch_state'     => $request->jenis_absen === 'masuk' ? 0 : 1,
        //             'verify_type'     => 0,
        //             'source'          => 15,
        //             'purpose'         => 41,
        //             'is_attendance'   => 1,
        //             'upload_time'     => $request->tanggal . ' ' . $randomTime . '.' . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT) . '-07',
        //             'sync_status'     => 0,
        //             'is_mask'         => 255,
        //             'temperature'     => 255,
        //             'emp_id'          => $employee->id,
        //             'company_code'    => 1,
        //         ]);
        //     }

        //     DB::commit();

        //     return response()->json([
        //         'status_code' => 201,
        //         'message'     => 'Data Berhasil Disimpan!'
        //     ], 201);
        // } catch (Exception $e) {
        //     DB::rollBack();
        //     return response()->json([
        //         'status_code' => 500,
        //         'message'     => 'Terjadi Kesalahan : ' . $e->getMessage()
        //     ], 500);
        // }

        $request = request();

        DB::beginTransaction();
        try {
            $employees = PersonnelEmployee::select('id', 'emp_code as id_senja', 'nickname as nip', 'first_name as nama_pegawai')
                ->whereHas('department', function ($q) use ($request) {
                    $q->where('dept_code', $request->department_id);
                });

            if ($request->nip != null) {
                $employees = $employees->whereIn('nickname', $request->nip);
            }

            $employees = $employees->get();

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            // Loop through each day in the month
            while ($startDate->lte($endDate)) {
                foreach ($employees as $employee) {
                    $randomTime = $startDate->copy()->addHours(random_int($request->jam, $request->jam))
                        ->addMinutes(random_int(0, 30))
                        ->addSeconds(random_int(0, 59))
                        ->toTimeString();

                    IClockTransaction::create([
                        'emp_code'        => $employee->id_senja,
                        'punch_time'      => $startDate->toDateString() . ' ' . $randomTime . '-07',
                        'punch_state'     => $request->jenis_absen === 'masuk' ? 0 : 1,
                        'verify_type'     => 0,
                        'source'          => 15,
                        'purpose'         => 41,
                        'is_attendance'   => 1,
                        'upload_time'     => $startDate->toDateString() . ' ' . $randomTime . '.' . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT) . '-07',
                        'sync_status'     => 0,
                        'is_mask'         => 255,
                        'temperature'     => 255,
                        'emp_id'          => $employee->id,
                        'company_code'    => 1,
                    ]);
                }
                $startDate->addDay();
            }

            DB::commit();

            return response()->json([
                'status_code' => 201,
                'message'     => 'Data Berhasil Disimpan!'
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message'     => 'Terjadi Kesalahan : ' . $e->getMessage()
            ], 500);
        }

        // $client = new Client();
        // $apiUrl = 'http://172.16.40.85:8002/api/service/get-hari-kerja?tanggal=' . $request->tanggal;

        // try {
        //     // Lakukan request ke API
        //     $response = $client->get($apiUrl);
        //     $data = json_decode($response->getBody(), true);

        //     // Ambil daftar hari kerja dari API response
        //     $workingDays = $data['hari_kerja'];

        //     DB::beginTransaction();
        //     try {
        //         $employees = PersonnelEmployee::select('id', 'emp_code as id_senja', 'nickname as nip', 'first_name as nama_pegawai')
        //             ->whereHas('department', function ($q) use ($request) {
        //                 $q->where('dept_code', $request->department_id);
        //             });

        //         if ($request->nip != null) {
        //             $employees = $employees->whereIn('nickname', $request->nip);
        //         }

        //         $employees = $employees->get();

        //         // Loop through each working day
        //         foreach ($workingDays as $workingDay) {
        //             foreach ($employees as $employee) {
        //                 $randomTime = Carbon::parse($workingDay)->addHours(random_int($request->jam, $request->jam))
        //                     ->addMinutes(random_int(0, 30))
        //                     ->addSeconds(random_int(0, 59))
        //                     ->toTimeString();

        //                 IClockTransaction::create([
        //                     'emp_code'        => $employee->id_senja,
        //                     'punch_time'      => $workingDay . ' ' . $randomTime . '-07',
        //                     'punch_state'     => $request->jenis_absen === 'masuk' ? 0 : 1,
        //                     'verify_type'     => 0,
        //                     'source'          => 15,
        //                     'purpose'         => 41,
        //                     'is_attendance'   => 1,
        //                     'upload_time'     => $workingDay . ' ' . $randomTime . '.' . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT) . '-07',
        //                     'sync_status'     => 0,
        //                     'is_mask'         => 255,
        //                     'temperature'     => 255,
        //                     'emp_id'          => $employee->id,
        //                     'company_code'    => 1,
        //                 ]);
        //             }
        //         }

        //         DB::commit();

        //         return response()->json([
        //             'status_code' => 201,
        //             'message'     => 'Data Berhasil Disimpan!'
        //         ], 201);
        //     } catch (Exception $e) {
        //         DB::rollBack();
        //         return response()->json([
        //             'status_code' => 500,
        //             'message'     => 'Terjadi Kesalahan : ' . $e->getMessage()
        //         ], 500);
        //     }
        // } catch (Exception $e) {
        //     return response()->json([
        //         'status_code' => 500,
        //         'message'     => 'Terjadi Kesalahan saat menghubungi API: ' . $e->getMessage()
        //     ], 500);
        // }
    }
}
