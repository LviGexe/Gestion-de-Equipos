<?php
// Simple translation audit for Snipe-IT repo
// Usage: php scripts/translation_audit.php

function find_files($dir, $exts = ['php','blade','js','vue','ts']){
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $files = [];
    foreach($rii as $file){
        if ($file->isDir()) continue;
        $path = $file->getPathname();
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if(in_array($ext, $exts)) $files[] = $path;
    }
    return $files;
}

function extract_keys_from_line($line){
    $keys = [];
    // patterns: __('key'), trans('key'), trans_choice('key'), Lang::get('key'), @lang('key')
    $patterns = [
        "~__\(\s*['\"]([^'\"]+)['\"]~",
        "~trans_choice\(\s*['\"]([^'\"]+)['\"]~",
        "~trans\(\s*['\"]([^'\"]+)['\"]~",
        "~Lang::get\(\s*['\"]([^'\"]+)['\"]~",
        "~@lang\(\s*['\"]([^'\"]+)['\"]~",
    ];
    foreach($patterns as $p){
        if(preg_match_all($p, $line, $m)){
            foreach($m[1] as $k) $keys[] = $k;
        }
    }
    return $keys;
}

function gather_used_keys($root){
    $files = find_files($root);
    $used = [];
    foreach($files as $f){
        $content = @file_get_contents($f);
        if($content === false) continue;
        $lines = preg_split("/\r?\n/", $content);
        foreach($lines as $i => $line){
            $keys = extract_keys_from_line($line);
            foreach($keys as $k){
                $used[$k][] = "$f:line:".($i+1);
            }
        }
    }
    return $used;
}

function load_lang_file($path){
    // include safely
    try{
        $arr = include $path;
        if(is_array($arr)) return $arr;
    }catch(Throwable $e){ }
    return [];
}

function flatten_keys($arr, $prefix = ''){
    $out = [];
    foreach($arr as $k => $v){
        $full = $prefix === '' ? $k : $prefix.'.'.$k;
        if(is_array($v)){
            $out = array_merge($out, flatten_keys($v, $full));
        }else{
            $out[$full] = $v;
        }
    }
    return $out;
}

function gather_defined_keys($langDir){
    $defined = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($langDir));
    foreach($rii as $file){
        if ($file->isDir()) continue;
        $path = $file->getPathname();
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if($ext !== 'php') continue;
        $rel = str_replace($langDir.DIRECTORY_SEPARATOR, '', $path);
        // if in subdir admin/hardware.php -> namespace admin/hardware
        $sub = dirname($rel);
        if($sub === '.') $sub = '';
        $arr = load_lang_file($path);
        $flat = flatten_keys($arr);
        foreach($flat as $k=>$v){
            // key name: if subdir then sub/file.key OR if filename is admin.php use admin.key
            $filename = pathinfo($path, PATHINFO_FILENAME);
            if($sub !== ''){
                $fullbase = str_replace(DIRECTORY_SEPARATOR, '/', $sub)."/".$filename;
            }else{
                $fullbase = $filename;
            }
            $definedKey = $fullbase . '.' . $k;
            $defined[$definedKey] = $v;
        }
    }
    return $defined;
}

function looks_english($text){
    // crude heuristic: contains common English words or mostly ASCII letters without accents
    $englishWords = [' the ',' and ',' is ',' are ',' please ',' click ',' asset ',' item ',' upload ',' error ',' warning ','please','the','and','is','are','item','asset','upload','success','failed','error'];
    $low = strtolower($text);
    foreach($englishWords as $w){
        if(strpos($low, $w) !== false) return true;
    }
    // check for accented chars - if none, might be english but could be spanish without accents
    if(!preg_match('/[\xC0-\xFF]/', $text)){
        // no accented chars; check proportion of vowels vs typical english words: fallback true
        return true;
    }
    return false;
}

$root = __DIR__ . DIRECTORY_SEPARATOR . '..';
$projectRoot = realpath($root);
if(!$projectRoot){
    echo "Cannot find project root.\n";
    exit(1);
}

echo "Scanning project for used translation keys...\n";
$used = gather_used_keys($projectRoot);
$usedKeys = array_keys($used);
sort($usedKeys);

echo "Loading defined keys from resources/lang/es-ES...\n";
$langDir = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'es-ES';
$defined = gather_defined_keys($langDir);
// also include literal keys from es-ES.json if present
// JSON translation file lives at resources/lang/{locale}.json (root of lang folder)
$jsonPath = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'es-ES.json';
if(file_exists($jsonPath)){
    $json = json_decode(file_get_contents($jsonPath), true) ?: [];
    foreach($json as $k=>$v){
        // treat the literal string as defined
        $defined[$k] = $v;
    }
}
$definedKeys = array_keys($defined);
sort($definedKeys);

$missing = [];
foreach($usedKeys as $k){
    // used keys might be namespaced like admin/hardware/message.key or general.key
    // direct match
    if(isset($defined[$k])) continue;
    // try alternative: some keys use slashes or dots - try swapping
    $alt = str_replace('/', '.', $k);
    if(isset($defined[$alt])) continue;
    // also try replacing back
    $alt2 = str_replace('.', '/', $k);
    if(isset($defined[$alt2])) continue;
    $missing[$k] = $used[$k];
}

// detect english values in defined
$englishCandidates = [];
foreach($defined as $k=>$v){
    if(is_string($v) && trim($v) !== '' && looks_english($v)){
        $englishCandidates[$k] = $v;
    }
}

$report = [
    'used_count' => count($usedKeys),
    'defined_count' => count($definedKeys),
    'missing_count' => count($missing),
    'english_candidates_count' => count($englishCandidates),
    'missing' => $missing,
    'english_candidates' => $englishCandidates,
];

$outDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'translation_audit';
if(!is_dir($outDir)) @mkdir($outDir, 0777, true);
$reportPath = $outDir . DIRECTORY_SEPARATOR . 'report.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Report written to: $reportPath\n";
echo "Missing keys: " . count($missing) . "\n";
echo "English-like defined entries in es-ES: " . count($englishCandidates) . "\n";

// print small summary top 30 missing
$top = array_slice(array_keys($missing), 0, 30);
if(count($top) > 0){
    echo "\nSample missing keys (up to 30):\n";
    foreach($top as $k){
        echo " - $k (used at: " . implode(', ', array_slice($missing[$k],0,3)) . ")\n";
    }
}

// sample english candidates
$topE = array_slice($englishCandidates, 0, 30, true);
if(count($topE) > 0){
    echo "\nSample es-ES entries that look English (up to 30):\n";
    foreach($topE as $k=>$v){
        echo " - $k => $v\n";
    }
}

echo "\nDone.\n";

exit(0);
