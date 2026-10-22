<?php
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
chdir(__DIR__);
require __DIR__.'/db_connect.php';
require __DIR__.'/line_job_sync.php';
try { mbsSyncDispatch($conn,0,20); }
catch(Throwable $e) { error_log('LINE job retry worker failed: '.get_class($e)); exit(1); }
