<?php
$projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
$reportPath = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'translation_audit' . DIRECTORY_SEPARATOR . 'report.json';
$langPath = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'es-ES.json';

if(!file_exists($reportPath)){
    echo "Report not found at $reportPath\n";
    exit(1);
}
$report = json_decode(file_get_contents($reportPath), true);
if(!isset($report['missing'])){
    echo "No missing keys in report.\n";
    exit(0);
}
$missing = array_keys($report['missing']);

$translations = [];
if(file_exists($langPath)){
    $translations = json_decode(file_get_contents($langPath), true) ?: [];
}

function humanize($key){
    $k = preg_replace('#^/#', '', $key);
    $k = str_replace(['/',"\\",'.','_','-'], ' ', $k);
    // remove placeholders like :count or :name
    $k = preg_replace('/:[a-zA-Z_]+/', '', $k);
    $k = preg_replace('/\s+/', ' ', $k);
    $k = trim($k);
    if($k === '') return 'Traducción pendiente';
    // Capitalize first letter
    $k = mb_strtoupper(mb_substr($k,0,1,'UTF-8'),'UTF-8') . mb_substr($k,1,null,'UTF-8');
    return 'Traducción: ' . $k;
}

$added = 0;
foreach($missing as $k){
    if(isset($translations[$k])) continue;
    $translations[$k] = humanize($k);
    $added++;
}

if($added === 0){
    echo "No new translations to add.\n";
    exit(0);
}

// sort keys for stable file
ksort($translations);
file_put_contents($langPath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Added $added provisional translations to $langPath\n";
exit(0);
