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

$numFiles = count($files);
$childPids = [];
$shmIds = [];
$i = 0;

while ($i < count($files)) {
    $filename = $files[$i];
    $pid = pcntl_fork();

    if ($pid == -1) {
        exit('Fork failed');
    } elseif ($pid == 0) {
        // Child
        $childPids[] = $pid;
        $value = (double)json_decode(file_get_contents($filename), true)['value'];
        $shmId = shmop_open(ftok(__FILE__, chr(rand(97, 122))), 'c', 0644, 100);
        $shmIds[] = $shmId;

        if (!$shmId) {
            exit('Failed to create shared memory segment');
        }

        shmop_write($shmId, pack('f', $value), 0);
        $i++;
        // exit();
    } else {
        // Parent
        pcntl_wait($status);
    }
}

foreach ($childPids as $pid) {
    pcntl_waitpid($pid, $status);
}

$sum = 0;
foreach ($shmIds as $shmId) {
    $value = unpack('f', shmop_read($shmId, 0, 4));
    print_r($value);
    $sum += $value[1];
    shmop_delete($shmId);
    shmop_close($shmId);
}

$average = $sum / $numFiles;
$result = array('average' => $average);
$outputFile = $dirName . 'result.json';
file_put_contents($outputFile, json_encode($result, JSON_PRETTY_PRINT));

echo sprintf('Average value = %f calculated and saved to file %s in JSON format<br/>', $average, $outputFile);
