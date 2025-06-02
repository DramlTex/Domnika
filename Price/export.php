<?php
session_start();

// Управляет отображением колонки "Мин. заказ"
$includeMinOrder = false;

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

// Порядок сортировки стран и типов, аналогичный JavaScript
$COUNTRY_ORDER = [
    'ИНДИЯ', 'ЦЕЙЛОН', 'ИРАН', 'ВЬЕТНАМ',
    'КИТАЙ', 'КЕНИЯ', 'ЕГИПЕТ', 'НИГЕРИЯ', 'ЮЖНАЯ АФРИКА'
];
$TYPE_ORDER = [];

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

// Стили для строк со страной и типом, повторяющие оформление из JS
$countryRowStyle = [
    'font' => ['bold' => true, 'size' => 18],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
    ]
];

$typeRowStyle = [
    'font' => ['italic' => true, 'size' => 16],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FAFAFA']],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
    ]
];

function normalizeCountry(string $value): string
{
    return mb_strtoupper(trim($value));
}

function sortItems(array $items, array $countryOrder, array $typeOrder): array
{
    $countryMap = array_flip($countryOrder);
    $typeMap    = array_flip($typeOrder);

    usort($items, function ($a, $b) use ($countryMap, $typeMap, $countryOrder, $typeOrder) {
        $aCountry = normalizeCountry($a['supplier'] ?? '');
        $bCountry = normalizeCountry($b['supplier'] ?? '');
        $ai = $countryMap[$aCountry] ?? count($countryOrder);
        $bi = $countryMap[$bCountry] ?? count($countryOrder);
        if ($ai !== $bi) {
            return $ai <=> $bi;
        }

        $aType = $a['tip'] ?? '';
        $bType = $b['tip'] ?? '';
        $ati = $typeMap[$aType] ?? count($typeOrder);
        $bti = $typeMap[$bType] ?? count($typeOrder);
        if ($ati !== $bti) {
            return $ati <=> $bti;
        }

        if ($aType !== $bType) {
            return strcmp($aType, $bType);
        }

        return strcmp($a['articul'] ?? '', $b['articul'] ?? '');
    });

    return $items;
}

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
    if ($includeMinOrder) {
        $columnWidths = [
            'A' => 5, 'B' => 12, 'C' => 40, 'D' => 12,
            'E' => 12, 'F' => 12, 'G' => 15, 'H' => 10,
            'I' => 12, 'J' => 15, 'K' => 12, 'L' => 15
        ];
        $lastColumn = 'L';
        $photoColumn = 'L';
    } else {
        $columnWidths = [
            'A' => 5, 'B' => 12, 'C' => 40, 'D' => 12,
            'E' => 12, 'F' => 12, 'G' => 15, 'H' => 10,
            'I' => 12, 'J' => 15, 'K' => 15
        ];
        $lastColumn = 'K';
        $photoColumn = 'K';
    }
    
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
    if ($includeMinOrder) {
        $sheet->setCellValue("K{$dataStartRow}", 'Мин. заказ');
        $sheet->setCellValue("L{$dataStartRow}", 'Фото');
    } else {
        $sheet->setCellValue("{$photoColumn}{$dataStartRow}", 'Фото');
    }
    
    // Стили для заголовков
    $sheet->getStyle("A{$dataStartRow}:{$lastColumn}{$dataStartRow}")
        ->applyFromArray($headerStyle);
    applyBorderFill($sheet, "A{$dataStartRow}:{$lastColumn}{$dataStartRow}");

    // 4. Сортировка и заполнение данных с группировкой по стране и типу
    $items = sortItems($items, $COUNTRY_ORDER, $TYPE_ORDER);

    $rowNum = $dataStartRow;
    $counter = 1;
    $currentCountry = null;
    $currentType = null;

    foreach ($items as $row) {
        $country = $row['supplier'] ?? '';
        $type    = $row['tip'] ?? '';

        if ($currentCountry !== $country) {
            $rowNum++;
            $sheet->mergeCells("A{$rowNum}:{$lastColumn}{$rowNum}");
            $sheet->setCellValue("A{$rowNum}", $country);
            $sheet->getStyle("A{$rowNum}:{$lastColumn}{$rowNum}")
                ->applyFromArray($countryRowStyle);
            applyBorderFill($sheet, "A{$rowNum}:{$lastColumn}{$rowNum}");
            $currentCountry = $country;
            $currentType = null;
        }

        if ($currentType !== $type) {
            $rowNum++;
            $sheet->mergeCells("A{$rowNum}:{$lastColumn}{$rowNum}");
            $sheet->setCellValue("A{$rowNum}", $type);
            $sheet->getStyle("A{$rowNum}:{$lastColumn}{$rowNum}")
                ->applyFromArray($typeRowStyle);
            applyBorderFill($sheet, "A{$rowNum}:{$lastColumn}{$rowNum}");
            $currentType = $type;
        }

        $rowNum++;
        $sheet->setCellValue("A{$rowNum}", $counter);
        $sheet->setCellValue("B{$rowNum}", $row['articul'] ?? '');
        $sheet->setCellValue("C{$rowNum}", $row['name'] ?? '');
        $sheet->setCellValue("D{$rowNum}", $row['uom'] ?? '');
        $sheet->setCellValue("E{$rowNum}", $type);
        $sheet->setCellValue("F{$rowNum}", $country);
        $sheet->setCellValue("G{$rowNum}", (int)($row['mass'] ?? 0));
        $sheet->setCellValue("H{$rowNum}", (int)($row['price'] ?? 0));
        $sheet->setCellValue("I{$rowNum}", (int)($row['stock'] ?? 0));
        $sheet->setCellValue("J{$rowNum}", $row['volumeWeight'] ?? '');
        if ($includeMinOrder) {
            $sheet->setCellValue("K{$rowNum}", $row['min_order_qty'] ?? '');
        }

        if (!empty($row['photoUrl'])) {
            $sheet->setCellValue("{$photoColumn}{$rowNum}", 'фото');
            $hyperlink = $sheet->getCell("{$photoColumn}{$rowNum}")->getHyperlink();
            $hyperlink->setUrl($row['photoUrl']);
            $hyperlink->setTooltip('Нажмите для просмотра фото');
            $sheet->getStyle("{$photoColumn}{$rowNum}")->applyFromArray($hyperlinkStyle);
        } else {
            $sheet->setCellValue("{$photoColumn}{$rowNum}", '');
        }

        $counter++;
    }
    // Настройка высоты строк
    $headerRowHeight = 30;    // Высота строки заголовков
    $dataRowHeight = 30;      // Высота строк с данными

    // Устанавливаем высоту строки заголовков
    $sheet->getRowDimension($dataStartRow)->setRowHeight($headerRowHeight);

    // Устанавливаем высоту для всех строк с данными
    foreach (range($dataStartRow + 1, $rowNum) as $r) {
        $sheet->getRowDimension($r)->setRowHeight($dataRowHeight);
    }
    // Форматирование чисел
    $lastRow = $rowNum;
    $sheet->getStyle("G" . ($dataStartRow + 1) . ":I{$lastRow}")
          ->getNumberFormat()
          ->setFormatCode(NumberFormat::FORMAT_NUMBER);


    // Центрируем данные и разрешаем перенос слов
    $sheet->getStyle("A{$dataStartRow}:{$lastColumn}{$lastRow}")
          ->getAlignment()
          ->setHorizontal(Alignment::HORIZONTAL_CENTER)
          ->setVertical(Alignment::VERTICAL_CENTER)
          ->setWrapText(true);

    // Колонка "Название" выравнивается по левому краю
    $sheet->getStyle("C{$dataStartRow}:C{$lastRow}")
          ->getAlignment()
          ->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // Обводка для области с данными
    applyBorderFill($sheet, "A{$dataStartRow}:{$lastColumn}{$lastRow}");

    // Включаем фильтр на строку заголовков
    $sheet->setAutoFilter("A{$dataStartRow}:{$lastColumn}{$lastRow}");

    // Центрирование для столбца с фото
    $sheet->getStyle("{$photoColumn}".($dataStartRow+1).":{$photoColumn}{$lastRow}")
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