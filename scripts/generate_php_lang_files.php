<?php
// Generate PHP language files under resources/lang/es-ES/ for missing keys from the audit
$projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
$reportPath = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'translation_audit' . DIRECTORY_SEPARATOR . 'report.json';
$langJsonPath = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'es-ES.json';
$langDir = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'es-ES';

if(!file_exists($reportPath)){
    echo "report.json not found at $reportPath\n";
    exit(1);
}
$report = json_decode(file_get_contents($reportPath), true);
$missing = isset($report['missing']) ? array_keys($report['missing']) : [];
$existing = [];
if(file_exists($langJsonPath)){
    $existing = json_decode(file_get_contents($langJsonPath), true) ?: [];
}

// Helper to decide file & key path
function key_to_file_and_key($key){
    // key examples:
    // admin/accessories/message.create.success
    // general.save
    // All rights reserved.
    // mail.Accessory_Checkin_Notification
    // We want to place keys with slashes or dots into nested files. If key contains spaces or capitals, fallback to json mappings
    if(preg_match('/^[a-z0-9_\/.-]+$/i', $key)){
        // normal key
        $k = ltrim($key, '/');
        // split by / then last segment is file or file + nested
        if(strpos($k, '/') !== false){
            $parts = explode('/', $k);
            $file = array_shift($parts);
            // if more than 1 part, file might be directory -> create file under dir for first part and use rest as nested
            if(count($parts) === 1){
                $fileName = $file . '.php';
                $innerKey = $parts[0];
            }else{
                // first part may be admin, second accessories -> file admin/accessories.php and innerKey rest
                $fileName = $file . DIRECTORY_SEPARATOR . array_shift($parts) . '.php';
                $innerKey = implode('.', $parts);
            }
            return [$fileName, $innerKey];
        }
        // no slash, use first component as file
        if(strpos($k, '.') !== false){
            $parts = explode('.', $k);
            $fileName = $parts[0] . '.php';
            array_shift($parts);
            $innerKey = implode('.', $parts);
            return [$fileName, $innerKey];
        }
        return [$k . '.php', ''];
    }
    return [null, null];
}

function ensure_dir($dir){
    if(!is_dir($dir)) mkdir($dir, 0755, true);
}

ensure_dir($langDir);
$created = 0;
$updated = 0;

foreach($missing as $key){
    // try json first
    if(isset($existing[$key])) continue; // already covered by es-ES.json
    list($file, $innerKey) = key_to_file_and_key($key);
    if($file === null){
        // fallback to json, add provisional
        $existing[$key] = 'Traducción pendiente: ' . $key;
        $created++;
        continue;
    }
    $filePath = $langDir . DIRECTORY_SEPARATOR . $file;
    $dir = dirname($filePath);
    ensure_dir($dir);
    $arr = [];
    if(file_exists($filePath)){
        $arr = include $filePath;
        if(!is_array($arr)) $arr = [];
    }
    // if innerKey is empty, assign a root value
    if($innerKey === ''){
        if(!isset($arr[$key])){
            $arr[$key] = 'Traducción pendiente: ' . $key;
            $updated++;
        }
    }else{
        // build nested array by splitting innerKey by dots
        $parts = explode('.', $innerKey);
        $ref = &$arr;
        foreach($parts as $p){
            if(!isset($ref[$p]) || !is_array($ref[$p])){
                // if last part, set string
                if($p === end($parts)){
                    if(!isset($ref[$p])) $ref[$p] = 'Traducción pendiente: ' . $key;
                }else{
                    if(!isset($ref[$p]) || !is_array($ref[$p])) $ref[$p] = [];
                }
            }
            $ref = &$ref[$p];
        }
    }
    // write file
    $export = '<?php' . "\nreturn " . var_export($arr, true) . ";\n";
    file_put_contents($filePath, $export);
    $created++;
}

// write back JSON additions if any
if(!empty($existing)){
    ksort($existing);
    file_put_contents($langJsonPath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo "Created/updated language files: $created (updated: $updated)\n";
exit(0);
