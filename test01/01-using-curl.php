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
    $rate = (double) str_replace(',', '.', (string) $record->Value);
    $result = ['value' => $rate];
    file_put_contents($dirName . $date, json_encode($result, JSON_PRETTY_PRINT));
    $files[] = $dirName . $date;
    echo $date . '->' . $rate . '<br/>';
}

$outputFile = $dirName . 'result.json';

$multiHandle = curl_multi_init();
$curlHandles = [];

foreach ($files as $fileName) {
    $url = "file://" . $fileName;

    $curlHandle = curl_init($url);
    curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlHandle, CURLOPT_HEADER, false);

    $curlHandles[] = $curlHandle;

    curl_multi_add_handle($multiHandle, $curlHandle);
}

do {
    curl_multi_exec($multiHandle, $isRrunning);
} while ($isRrunning > 0);

$results = [];
foreach ($curlHandles as $curlHandle) {
    $responseJson = curl_multi_getcontent($curlHandle);
    $result = json_decode($responseJson, true);
    $results[] = (double) $result['value'];

    curl_multi_remove_handle($multiHandle, $curlHandle);
    curl_close($curlHandle);
}

$average = round(array_sum($results) / count($results), 2);
$result = ['average' => $average];
file_put_contents($outputFile, json_encode($result, JSON_PRETTY_PRINT));

echo sprintf('Average value = %f calculated and saved to file %s in JSON format<br/>', $average, $outputFile);
