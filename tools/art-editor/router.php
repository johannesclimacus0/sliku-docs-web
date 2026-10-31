<?php

declare(strict_types=1);

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/data.json') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $root = dirname(__DIR__, 2);
        require $root . '/app/Console/UI/MikuColors.php';
        require $root . '/app/Console/UI/Miku.php';
        $miku = new App\Console\UI\Miku();
        $arts = [];
        $errors = [];
        foreach ($miku->names() as $name) {
            try {
                $drawing = $miku->render($name, 4096, 4096);
            } catch (RuntimeException $error) {
                $errors[] = $name . ': ' . $error->getMessage();
                continue;
            }
            $lines = explode("\n", $drawing);
            $arts[] = [
                'name' => $name,
                'width' => max(array_map(mb_strlen(...), $lines)),
                'height' => count($lines),
                'lines' => $lines,
                'hash' => hash('sha256', $drawing),
                'grid' => App\Console\UI\MikuColors::GRIDS[$name] ?? null,
            ];
        }
        echo json_encode(['palette' => App\Console\UI\MikuColors::PALETTE, 'arts' => $arts, 'errors' => $errors], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    } catch (Throwable $error) {
        http_response_code(500);
        echo json_encode(['error' => 'Cannot read art data: ' . $error->getMessage()], JSON_THROW_ON_ERROR);
    }
    exit;
}
$files = ['/' => ['index.html', 'text/html'], '/index.html' => ['index.html', 'text/html'],
    '/editor.js' => ['editor.js', 'text/javascript'], '/editor.css' => ['editor.css', 'text/css']];
if (!isset($files[$path])) {
    http_response_code(404);
    exit;
}
[$file, $type] = $files[$path];
header('Content-Type: ' . $type . '; charset=utf-8');
if ($method !== 'HEAD') {
    readfile(__DIR__ . '/' . $file);
}
