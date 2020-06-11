<?php

if ($argc < 2) {
    die("Missing directory\n");
}

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($argv[1]),
    RecursiveIteratorIterator::LEAVES_ONLY);

$paramTypes = [];
$returnTypes = [];
foreach ($it as $file) {
    if (!preg_match('/\.php$/', $file)) {
        continue;
    }

    $code = file_get_contents($file);
    if (preg_match_all('/@param\s+(\S+)/', $code, $matches)) {
        foreach ($matches[1] as $type) {
            $paramTypes[$type] = ($paramTypes[$type] ?? 0) + 1;
        }
    }

    if (preg_match_all('/@return\s+(\S+)/', $code, $matches)) {
        foreach ($matches[1] as $type) {
            $returnTypes[$type] = ($returnTypes[$type] ?? 0) + 1;
        }
    }
}

arsort($paramTypes);
arsort($returnTypes);
file_put_contents("param_types.json", json_encode($paramTypes, JSON_PRETTY_PRINT));
file_put_contents("return_types.json", json_encode($returnTypes, JSON_PRETTY_PRINT));

$paramUnionTypes = filterUnionTypes($paramTypes);
$returnUnionTypes = filterUnionTypes($returnTypes);
file_put_contents("param_union_types.json", json_encode($paramUnionTypes, JSON_PRETTY_PRINT));
file_put_contents("return_union_types.json", json_encode($returnUnionTypes, JSON_PRETTY_PRINT));

echo "Param union types: ", array_sum($paramUnionTypes), "\n";
echo "Return union types: ", array_sum($returnUnionTypes), "\n";

function filterUnionTypes(array $types): array {
    $unionTypes = [];
    foreach ($types as $type => $count) {
        $nonNullType = str_replace(['|null', 'null|'], ['', ''], $type);
        if (strpos($nonNullType, '|') === false) continue;

        $unionTypes[$type] = $count;
    }
    return $unionTypes;
}
