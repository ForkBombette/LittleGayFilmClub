<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command-line only.'); }
require_once dirname(__DIR__) . '/src/bootstrap.php';
LGFC\MovieSchema::migrate(LGFC\Database::connect());
LGFC\Elections::migrate(LGFC\Database::connect());
LGFC\Watched::migrate(LGFC\Database::connect());
LGFC\Auth::migrate(LGFC\Database::connect());
echo "Auth, movie, watched and election storage is up to date. Existing data preserved.\n";
