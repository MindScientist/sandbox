<?php

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
    $rate = (float) str_replace(',', '.', (string) $record->Value);
    $result = ['value' => $rate];
    file_put_contents($dirName . $date, json_encode($result, JSON_PRETTY_PRINT));
    $files[] = $dirName . $date;
    echo $date . '->' . $rate . '<br/>';
}

$command = "cat %s";
$handles = $outputs = $inputs = $errors = [];

foreach ($files as $file) {
    $cmd = sprintf($command, $file);

    $handle = proc_open($cmd, [
        ['pipe', 'r'], //stdin
        ['pipe', 'w'], //stdout
        ['pipe', "w"], //stderr
    ], $pipes);

    $handles[] = $handle;
    $outputs[] = $pipes[1];
    $errors[] = $pipes[2];
    fclose($pipes[0]);
}

$arStatuses = $arRunning = $arExitCodes = [];
$isRunning = true;

while($isRunning) {
    foreach ($handles as $i => $handle) {
        if ($arExitCodes[$i] === 0)
            continue;
        $arStatus = proc_get_status($handle);
        $arExitCodes[$i] = $arStatus['exitcode'];
        $arStatuses[$i] = $arStatus;
        $arRunning[$i] = (int)$arStatus['running'];
    }
    $isRunning = array_sum($arRunning) > 0;
    usleep(500);
}

$results = [];
foreach ($handles as $i => $handle) {
    $output = stream_get_contents($outputs[$i]);

    fclose($outputs[$i]);
    if ($arExitCodes[$i] === 0) {
        $results[] = (double)json_decode(trim($output), true)['value'];
    }

    proc_close($handle);
}

$average = round(array_sum($results) / count($results), 2);

$outputFile = $dirName . 'result.json';
file_put_contents($outputFile, json_encode(['average' => $average], JSON_PRETTY_PRINT));

echo sprintf('Average value = %f calculated and saved to file %s in JSON format<br/>', $average, $outputFile);
