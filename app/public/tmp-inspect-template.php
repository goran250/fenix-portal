<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

$path = __DIR__ . '/wp-content/uploads/2026/11/Ny möteslista Fenix.xlsx';
$wb = IOFactory::load($path);
$ws = $wb->getSheetByName('Möteslista');
if (!$ws) {
	$ws = $wb->getSheet(0);
}
$maxRow = min(140, $ws->getHighestRow());
$maxCol = Coordinate::columnIndexFromString($ws->getHighestColumn());

for ($r = 1; $r <= $maxRow; $r++) {
	$vals = [];
	for ($c = 1; $c <= min(25, $maxCol); $c++) {
		$ref = Coordinate::stringFromColumnIndex($c) . $r;
		$v = $ws->getCell($ref)->getValue();
		if ($v !== null && $v !== '') {
			$vals[] = $c . ':' . str_replace(["\r", "\n"], ' ', (string)$v);
		}
	}
	if (!empty($vals)) {
		echo $r . ' | ' . implode(' || ', $vals) . PHP_EOL;
	}
}
