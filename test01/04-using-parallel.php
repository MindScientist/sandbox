<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\ParallelFunctions;
use Amp\Promise;

$apiUrl = 'http://www.cbr.ru/scripts/XML_dynamic.asp';

$requestParams = [
    'date_req1' => '08/03/2023',
    'date_req2' => '14/03/2023',
    'VAL_NM_RQ' => 'R01235',
];

$apiUrl .= '?' . http_build_query($requestParams);
$response = file_get_contents($apiUrl);
$xml = simplexml_load_string($response);
$dirName = __DIR__ . '/quotes/';
$files = [];

foreach ($xml->Record as $record) {
    $date = (string) $record->attributes()->Date;
    $rate = (double) str_replace(',', '.', (string) $record->Value);
    $result = ['value' => $rate];
    file_put_contents($dirName . $date, json_encode($result, JSON_PRETTY_PRINT));
    $files[] = $dirName . $date;
    echo $date . '->' . $rate . '<br/>';
}

try {
    $results = Promise\wait(
        ParallelFunctions\parallelMap($files, function ($file) {
            return (double)json_decode(file_get_contents($file), true)['value'];
        })
    );
} catch (Throwable $e) {
    die($e->getMessage());
}

$average = array_sum($results) / count($results);

$outputFile = $dirName. "result.json";
file_put_contents($outputFile, json_encode(['average' => $average]));

echo sprintf('Average value = %f calculated and saved to file %s in JSON format<br/>', $average, $outputFile);
