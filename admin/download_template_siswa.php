<?php
// download_template_siswa.php
include '../config.php';
cek_login([1]);

require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Siswa');
$sheet->setCellValue('A1', 'NIS');
$sheet->setCellValue('B1', 'Nama Lengkap');
$sheet->setCellValue('C1', 'Nama Kelas');
$sheet->setCellValue('D1', 'Jenis Kelamin (L/P)');
$sheet->setCellValue('A2', '12345');
$sheet->setCellValue('B2', 'Ahmad Fauzi');
$sheet->setCellValue('C2', 'IX-A');
$sheet->setCellValue('D2', 'L');
$sheet->setCellValue('A3', '12346');
$sheet->setCellValue('B3', 'Siti Aminah');
$sheet->setCellValue('C3', 'IX-A');
$sheet->setCellValue('D3', 'P');

$sheet->getStyle('A1:D1')->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

foreach (range('A','D') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_import_siswa.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>