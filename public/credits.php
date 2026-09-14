<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
LGFC\Web::start();$viewer=LGFC\Auth::requireUser(LGFC\Database::connect());
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Credits · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head><body><main><header><?php LGFC\Auth::accountBar($viewer); ?><h1>Credits</h1><a href="index.php">Voting and results</a></header><section><h2>Movie data and images</h2><a href="https://www.themoviedb.org" rel="noreferrer"><img src="images/tmdb-logo.svg" alt="The Movie Database (TMDB)" width="160"></a><p>This product uses the TMDB API but is not endorsed or certified by TMDB.</p></section></main></body></html>
