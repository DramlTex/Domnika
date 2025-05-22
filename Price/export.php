<?php
session_start();

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Настройки
set_time_limit(300);
ini_set('memory_limit', '512M');

// Проверка данных
$jsonData = $_POST['jsonData'] ?? '';
if (!$jsonData) die(json_encode(['error' => 'Нет данных для экспорта.']));
$data = json_decode($jsonData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die(json_encode(['error' => 'Ошибка JSON: ' . json_last_error_msg()]));
}
if (!is_array($data)) die(json_encode(['error' => 'Данные должны быть массивом.']));

// Группировка данных
$groupedData = [];
foreach ($data as $item) {
    $groupName = $item['group'] ?? 'Остальное';
    $groupedData[$groupName][] = $item;
}

// Создаем документ
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

// Путь к логотипу
$logoPath = __DIR__ . '/jfkxlsx.png';
$hasLogo = file_exists($logoPath);

// Основные стили
$headerStyle = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDDDDD']],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,

        'wrapText'   => true
    ]

];

$hyperlinkStyle = [
    'font' => [
        'color' => ['rgb' => '0000FF'],
        'underline' => Font::UNDERLINE_SINGLE
    ]
];

// Удобная функция для применения заливки и границ к диапазону
function applyBorderFill(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
    string $range,
    string $borderColor = '000000',
    ?string $fillColor = null
) {
    $style = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => $borderColor],
            ],
        ],
    ];

    if ($fillColor !== null) {
        $style['fill'] = [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $fillColor],
        ];
    }

    $sheet->getStyle($range)->applyFromArray($style);
}

foreach ($groupedData as $groupName => $items) {
    $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $groupName);
    $spreadsheet->addSheet($sheet);

    // 1. Устанавливаем точные ширины столбцов (в единицах Excel)
    $columnWidths = [
        'A' => 5, 'B' => 12, 'C' => 40, 'D' => 12, 
        'E' => 12, 'F' => 12, 'G' => 15, 'H' => 10,
        'I' => 12, 'J' => 15, 'K' => 12, 'L' => 15
    ];
    
    foreach ($columnWidths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    // 2. Рассчитываем общую ширину области для логотипа
    $totalColumnsWidth = 0;
    foreach ($columnWidths as $col => $width) {
        $totalColumnsWidth += $width;
    }
    
    // 3. Вставка логотипа с точными размерами
    $dataStartRow = 2;
    
    if ($hasLogo) {
        try {
            $imageSize = getimagesize($logoPath);
            if ($imageSize !== false) {
                $originalWidth = $imageSize[0];
                $originalHeight = $imageSize[1];
                $aspectRatio = $originalHeight / $originalWidth;

                // Точная ширина логотипа в пикселях (1 единица ширины Excel ≈ 7 пикселей)
                $logoWidthPx = $totalColumnsWidth * 7;
                $logoHeightPx = $logoWidthPx * $aspectRatio;

                // Высота строки в пунктах (1 px ≈ 0.75 pt)
                $rowHeightPt = $logoHeightPx * 0.75;
                $sheet->getRowDimension(1)->setRowHeight($rowHeightPt);
                
                // Вставляем логотип
                $drawing = new Drawing();
                $drawing->setName('Logo');
                $drawing->setDescription('JFK Logo');
                $drawing->setPath($logoPath);
                
                // Устанавливаем РЕАЛЬНУЮ ширину в пикселях
                $drawing->setWidth($logoWidthPx); 
                $drawing->setHeight($logoHeightPx);
                
                // Выравниваем по левому краю без отступов
                $drawing->setCoordinates('A1');
                $drawing->setOffsetX(0);
                $drawing->setOffsetY(0);
                $drawing->setWorksheet($sheet);

                // Для точности можно добавить невидимую рамку
                $sheet->getStyle('A1')->getBorders()->getOutline()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
            }
        } catch (Exception $e) {
            error_log("Ошибка при вставке логотипа: " . $e->getMessage());
            $sheet->getRowDimension(1)->setRowHeight(30);
        }
    }

    // 3. Заголовки таблицы (начинаем со второй строки)
    $sheet->setCellValue("A{$dataStartRow}", '№');
    $sheet->setCellValue("B{$dataStartRow}", 'Артикул');
    $sheet->setCellValue("C{$dataStartRow}", 'Название');
    $sheet->setCellValue("D{$dataStartRow}", 'Стандарт');
    $sheet->setCellValue("E{$dataStartRow}", 'Тип');
    $sheet->setCellValue("F{$dataStartRow}", 'Страна');
    $sheet->setCellValue("G{$dataStartRow}", 'Вес тарного места');
    $sheet->setCellValue("H{$dataStartRow}", 'Цена');
    $sheet->setCellValue("I{$dataStartRow}", 'Наличие (кг)');
    $sheet->setCellValue("J{$dataStartRow}", 'Объём тарного места');
    $sheet->setCellValue("K{$dataStartRow}", 'Мин. заказ');
    $sheet->setCellValue("L{$dataStartRow}", 'Фото');
    
    // Стили для заголовков
    $sheet->getStyle("A{$dataStartRow}:L{$dataStartRow}")->applyFromArray($headerStyle);
    applyBorderFill($sheet, "A{$dataStartRow}:L{$dataStartRow}");

    // 4. Заполнение данных
    foreach ($items as $i => $row) {
        $rowNum = $dataStartRow + 1 + $i;
        
        $sheet->setCellValue("A{$rowNum}", $i + 1);
        $sheet->setCellValue("B{$rowNum}", $row['articul'] ?? '');
        $sheet->setCellValue("C{$rowNum}", $row['name'] ?? '');
        $sheet->setCellValue("D{$rowNum}", $row['uom'] ?? '');
        $sheet->setCellValue("E{$rowNum}", $row['tip'] ?? '');
        $sheet->setCellValue("F{$rowNum}", $row['supplier'] ?? '');
        $sheet->setCellValue("G{$rowNum}", $row['mass'] ?? '');
        $sheet->setCellValue("H{$rowNum}", $row['price'] ?? '');
        $sheet->setCellValue("I{$rowNum}", $row['stock'] ?? '');
        $sheet->setCellValue("J{$rowNum}", $row['volumeWeight'] ?? '');
        $sheet->setCellValue("K{$rowNum}", $row['min_order_qty'] ?? '');
        
        // Гиперссылка на фото
        if (!empty($row['photoUrl'])) {
            $sheet->setCellValue("L{$rowNum}", 'фото');
            $hyperlink = $sheet->getCell("L{$rowNum}")->getHyperlink();
            $hyperlink->setUrl($row['photoUrl']);
            $hyperlink->setTooltip('Нажмите для просмотра фото');
            $sheet->getStyle("L{$rowNum}")->applyFromArray($hyperlinkStyle);
        } else {
            $sheet->setCellValue("L{$rowNum}", '');
        }
    }
    // Настройка высоты строк
$headerRowHeight = 30;    // Высота строки заголовков
$dataRowHeight = 30;      // Высота строк с данными

// Устанавливаем высоту строки заголовков
$sheet->getRowDimension($dataStartRow)->setRowHeight($headerRowHeight);

// Устанавливаем высоту для всех строк с данными
foreach (range($dataStartRow + 1, $dataStartRow + count($items)) as $rowNum) {
    $sheet->getRowDimension($rowNum)->setRowHeight($dataRowHeight);
}
    // Форматирование чисел
    $lastRow = $dataStartRow + count($items);
    $sheet->getStyle("G".($dataStartRow+1).":G{$lastRow}")
          ->getNumberFormat()
          ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
    
    $sheet->getStyle("H".($dataStartRow+1).":H{$lastRow}")
          ->getNumberFormat()
          ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);


    // Центрируем данные и разрешаем перенос слов
    $sheet->getStyle("A{$dataStartRow}:L{$lastRow}")
          ->getAlignment()
          ->setHorizontal(Alignment::HORIZONTAL_CENTER)
          ->setVertical(Alignment::VERTICAL_CENTER)
          ->setWrapText(true);

    // Колонка "Название" выравнивается по левому краю
    $sheet->getStyle("C{$dataStartRow}:C{$lastRow}")
          ->getAlignment()
          ->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // Обводка для области с данными
    applyBorderFill($sheet, "A{$dataStartRow}:L{$lastRow}");

    // Включаем фильтр на строку заголовков
    $sheet->setAutoFilter("A{$dataStartRow}:L{$lastRow}");

    // Центрирование для столбца с фото
    $sheet->getStyle("L".($dataStartRow+1).":L{$lastRow}")
          ->getAlignment()
          ->setHorizontal(Alignment::HORIZONTAL_CENTER)
          ->setVertical(Alignment::VERTICAL_CENTER);
}

// Настройка активного листа
$spreadsheet->setActiveSheetIndex(0);

// Генерация и отправка файла
$filename = 'JFK_Price_List_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');

exit;