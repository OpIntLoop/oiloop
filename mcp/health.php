<?php
/* OI:LOOP health check: https://oiloop.com/health */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
$mcpOk = is_readable(__DIR__ . '/mcp.php');
http_response_code($mcpOk ? 200 : 503);
echo json_encode([
    'status' => $mcpOk ? 'ok' : 'degraded',
    'service' => 'oiloop-mcp',
    'time' => gmdate('c')
], JSON_UNESCAPED_SLASHES);
