<?php

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;

// Faces are personal data: only people who are logged in can see them.
Auth::requireUser();

$path = photos()->path((int) ($_GET['u'] ?? 0));
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
// URLs carry the photo version, so a cached copy is never stale.
header('Cache-Control: private, max-age=31536000, immutable');
readfile($path);
