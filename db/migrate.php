<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command-line only.'); }
require_once dirname(__DIR__) . '/src/bootstrap.php';
LGFC\MovieSchema::migrate(LGFC\Database::connect());
echo "Movie fields are up to date. Existing movies and ballots preserved.\n";
