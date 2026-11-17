<?php

namespace App\Imports;

use App\Models\Patient;
use App\Models\MilitaryUnit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class PatientsImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected $militaryUnitId;
    protected $intakeBatch;

    public function __construct($militaryUnitId, $intakeBatch)
    {
        $this->militaryUnitId = $militaryUnitId;
        $this->intakeBatch = $intakeBatch;
        
        // บังคับอ่านหัวคอลัมน์ตามตัวอักษรเป๊ะๆ
        \Maatwebsite\Excel\Imports\HeadingRowFormatter::default('none');
    }

    public function collection(Collection $rows): void
    {
        $thaiMonths = [
            'ม.ค.' => 1, 'มกราคม' => 1,
            'ก.พ.' => 2, 'กุมภาพันธ์' => 2,
            'มี.ค.' => 3, 'มีนาคม' => 3,
            'เม.ย.' => 4, 'เมษายน' => 4,
            'พ.ค.' => 5, 'พฤษภาคม' => 5,
            'มิ.ย.' => 6, 'มิถุนายน' => 6,
            'ก.ค.' => 7, 'กรกฎาคม' => 7,
            'ส.ค.' => 8, 'สิงหาคม' => 8,
            'ก.ย.' => 9, 'กันยายน' => 9,
            'ต.ค.' => 10, 'ตุลาคม' => 10,
            'พ.ย.' => 11, 'พฤศจิกายน' => 11,
            'ธ.ค.' => 12, 'ธันวาคม' => 12,
        ];

        foreach ($rows as $row) {
            
            // 🌟 1. ระบบทำความสะอาดหัวคอลัมน์
            $cleanRow = [];
            foreach ($row as $key => $val) {
                $cleanKey = str_replace(["\r", "\n", " "], "", (string)$key); 
                $cleanRow[$cleanKey] = $val;
            }

            // 🌟 2. หาเลขบัตรประชาชน (cid / id)
            $rawCid = $cleanRow['cid'] ?? ($cleanRow['id'] ?? ($cleanRow['เลขประจำตัวประชาชน'] ?? ($cleanRow['เลขบัตรประชาชน'] ?? null)));
            if (empty(trim((string)$rawCid))) {
                continue;
            }

            if (is_numeric($rawCid)) {
                $cid = number_format((float)$rawCid, 0, '', '');
            } else {
                $cid = preg_replace('/[^0-9]/', '', trim((string)$rawCid));
            }
            
            if (strlen($cid) !== 13) {
                \Illuminate\Support\Facades\Log::warning("Import Skipped: เลข ปชช. ไม่ถูกต้อง ({$cid}) สำหรับชื่อ: " . ($cleanRow['ชื่อ'] ?? 'ไม่ระบุ'));
                continue; 
            }

            // 🌟 3. แปลงวันเกิดโดยรวมคอลัมน์ 'เกิด' (วัน/เดือน) + 'พ.ศ.เกิด' (ปี พ.ศ.)
            $dob = null;
            $birthCol  = $cleanRow['เกิด'] ?? ($cleanRow['วันเกิด'] ?? ($cleanRow['dob'] ?? null));
            $yearBeCol = $cleanRow['พ.ศ.เกิด'] ?? ($cleanRow['พศเกิด'] ?? ($cleanRow['ปีเกิด'] ?? null));

            $yearAD = null;
            if (!empty($yearBeCol) && is_numeric(trim((string)$yearBeCol))) {
                $yVal = (int)trim((string)$yearBeCol);
                $yearAD = ($yVal > 2400) ? ($yVal - 543) : $yVal;
            }

            $day = null;
            $month = null;

            if (!empty($birthCol)) {
                if ($birthCol instanceof \DateTimeInterface) {
                    $day   = (int)$birthCol->format('d');
                    $month = (int)$birthCol->format('m');
                    if (!$yearAD) {
                        $y = (int)$birthCol->format('Y');
                        $yearAD = ($y > 2400) ? ($y - 543) : $y;
                    }
                } elseif (is_numeric($birthCol)) {
                    try {
                        $dtObj = Date::excelToDateTimeObject((float)$birthCol);
                        $day   = (int)$dtObj->format('d');
                        $month = (int)$dtObj->format('m');
                        if (!$yearAD) {
                            $y = (int)$dtObj->format('Y');
                            $yearAD = ($y > 2400) ? ($y - 543) : $y;
                        }
                    } catch (\Exception $e) {}
                } else {
                    $birthStr = trim((string)$birthCol);
                    foreach ($thaiMonths as $thMonth => $mNum) {
                        if (str_contains($birthStr, $thMonth)) {
                            $month = $mNum;
                            $remain = trim(str_replace($thMonth, '', $birthStr));
                            if (preg_match('/(\d{1,2})/', $remain, $matches)) {
                                $day = (int)$matches[1];
                            }
                            break;
                        }
                    }
                    if (!$day || !$month) {
                        try {
                            $dt = Carbon::parse(str_replace('/', '-', $birthStr));
                            $day   = $dt->day;
                            $month = $dt->month;
                            if (!$yearAD) {
                                $yearAD = ($dt->year > 2400) ? ($dt->year - 543) : $dt->year;
                            }
                        } catch (\Exception $e) {}
                    }
                }
            }

            if ($yearAD && $month && $day && checkdate((int)$month, (int)$day, (int)$yearAD)) {
                $dob = sprintf('%04d-%02d-%02d', $yearAD, $month, $day);
            }

            // 🌟 4. รวมชื่อ-นามสกุล บิดามารดา
            $fatherName = trim(($cleanRow['ชื่อบิดา'] ?? '') . ' ' . ($cleanRow['สกุลบิดา'] ?? ''));
            $motherName = trim(($cleanRow['ชื่อมารดา'] ?? '') . ' ' . ($cleanRow['สกุลมารดา'] ?? ''));

            // 🌟 5. จัดการตัวเลข BMI
            $rawBmi = $cleanRow['BMI'] ?? ($cleanRow['bmi'] ?? null);
            $bmiValue = is_numeric($rawBmi) ? number_format((float)$rawBmi, 2, '.', '') : null;

            // 🌟 6. ช่องรหัสประจำตัวทหาร (military_id) ให้ปล่อยว่างไว้ (null) เพราะใน Excel ไม่มีข้อมูลนี้
            $finalMilitaryId = null;

            // 🌟 7. Smart Override หน่วยฝึก (รองรับเลขไทย และตัดช่องว่าง)
            $finalMilitaryUnitId = $this->militaryUnitId; 
            $excelUnitName = trim((string)($cleanRow['หน่วยฝึก'] ?? ''));

            if (!empty($excelUnitName)) {
                $thaiNum = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
                $arabicNum = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                $normalizedUnitName = str_replace($thaiNum, $arabicNum, $excelUnitName);
                $searchKeyword = str_replace(['หน่วยฝึก', ' '], '', $normalizedUnitName);

                if (!empty($searchKeyword)) {
                    $foundUnit = MilitaryUnit::whereRaw("REPLACE(name, ' ', '') LIKE ?", ["%{$searchKeyword}%"])
                                    ->orWhereRaw("REPLACE(short_name, ' ', '') LIKE ?", ["%{$searchKeyword}%"])
                                    ->first();
                                    
                    if ($foundUnit) {
                        $finalMilitaryUnitId = $foundUnit->id;
                    }
                }
            }

            // 🌟 8. บันทึกลงฐานข้อมูล
            Patient::updateOrCreate(
                ['cid' => $cid],
                [
                    'rank'                => 'พลฯ',
                    'first_name'          => trim((string)($cleanRow['ชื่อ'] ?? '')),
                    'last_name'           => trim((string)($cleanRow['สกุล'] ?? '')),
                    'dob'                 => $dob,
                    
                    'volunteer_status'    => trim((string)($cleanRow['ร้องขอ'] ?? '')) ?: null, 
                    'privilege_reduction' => trim((string)($cleanRow['ขอสิทธิลด'] ?? '')) ?: null,
                    'service_duration'    => trim((string)($cleanRow['เวลารับราชการ'] ?? '')) ?: null,
                    
                    'house_no'            => trim((string)($cleanRow['บ้านเลขที่'] ?? '')),
                    'village_no'          => trim((string)($cleanRow['หมู่ที่'] ?? '')),
                    'sub_district'        => trim((string)($cleanRow['ตำบล'] ?? '')),
                    'district'            => trim((string)($cleanRow['อำเภอ'] ?? '')),
                    'province'            => trim((string)($cleanRow['จังหวัด'] ?? '')),
                    
                    'father_name'         => $fatherName !== '' ? $fatherName : null,
                    'mother_name'         => $motherName !== '' ? $motherName : null,
                    
                    'height'              => trim((string)($cleanRow['สูง'] ?? '')),
                    'weight'              => trim((string)($cleanRow['น้ำหนัก'] ?? '')),
                    'chest_size'          => trim((string)($cleanRow['รอบอก'] ?? '')),
                    'bmi'                 => $bmiValue,
                    'scar_details'        => trim((string)($cleanRow['แผลเป็น'] ?? '')),
                    
                    'religion'            => trim((string)($cleanRow['ศาสนา'] ?? '')),
                    'education'           => trim((string)($cleanRow['ความรู้ย่อ'] ?? ($cleanRow['ความรู้'] ?? ''))),
                    'phone'               => trim((string)($cleanRow['โทรศัพท์'] ?? '')),
                    
                    'military_id'         => $finalMilitaryId,
                    'battalion'           => trim((string)($cleanRow['ส่งแผนก'] ?? '')),
                    'company'             => $excelUnitName,
                    'military_unit_id'    => $finalMilitaryUnitId,
                    'camp_name'           => 'ค่ายกฤษณ์สีวะรา',
                    'intake_batch'        => $this->intakeBatch,
                ]
            );
        }
    }

    public function chunkSize(): int
    {
        return 100;
    }
}