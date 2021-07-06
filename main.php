<?php

if ($argc !== 2) {
    echo 'Usage: php ' . basename(__FILE__) . ' [input.json]' . PHP_EOL;
    return;
}

try {
    $data = tryToReadDataFromFile($argv[1]);
    $parsedData = tryToParseData($data);
} catch (JsonException $e) {
    echo "Invalid JSON string was specified\n";
    return;
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    return;
}

$max = 0;
foreach ($parsedData as $i => [$start, $end]) {
    $max = max($max, findPeakUsage($parsedData, $i, $start, $end));
}

echo "Parking peak usage is $max";

/**
 * @param string $fileName
 * @return string
 * @throws Exception
 */
function tryToReadDataFromFile(string $fileName): string
{
    if (!file_exists($fileName)) {
        throw new Exception("Specified file \"$fileName\" doesn't exist");
    }

    $data = file_get_contents($fileName);
    if ($data === false) {
        throw new Exception("Can't read specified file \"$fileName\"");
    }

    return $data;
}

/**
 * @param string $data
 * @return array
 * @throws JsonException
 * @throws Exception
 */
function tryToParseData(string $data): array
{
    $parsedJson = json_decode($data, false, 512, JSON_THROW_ON_ERROR);
    return array_map(static function ($item): array {
        if (!is_array($item) || count($item) !== 2) {
            throw new Exception("Invalid data item: " . var_export($item, true));
        }

        $start = new DateTimeImmutable($item[0]);
        $end = new DateTimeImmutable($item[1]);
        if ($start >= $end) {
            throw new Exception("Start time must be less than end ($item[0] >= $item[1])");
        }

        return [$start, $end];
    }, $parsedJson);
}

function findPeakUsage(array $data, int $firstIndex, DateTimeImmutable $start, DateTimeImmutable $end): int
{
    $len = count($data);
    for ($i = $firstIndex; $i < $len; $i++) {
        [$nextStart, $nextEnd] = $data[$i];
        $maxStart = max($start, $nextStart);
        $minEnd = min($end, $nextEnd);
        if ($maxStart < $minEnd) {
            return findPeakUsage($data, $i + 1, $maxStart, $minEnd) + 1;
        }
    }

    return 0;
}