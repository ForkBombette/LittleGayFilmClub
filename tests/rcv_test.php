<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use LGFC\Rcv;

$cases = json_decode(file_get_contents(__DIR__ . '/fixtures/rcv_cases.json'), true, 512, JSON_THROW_ON_ERROR);
$failures = 0;

foreach ($cases as $case) {
    $result = Rcv::calculate($case['ballots'], $case['candidates']);
    $ok = $result['winner'] === $case['winner'];
    echo ($ok ? 'PASS' : 'FAIL') . ' - ' . $case['name'] . PHP_EOL;
    if (!$ok) {
        echo '  expected ' . $case['winner'] . ', got ' . var_export($result['winner'], true) . PHP_EOL;
        $failures++;
    }
}

exit($failures === 0 ? 0 : 1);
