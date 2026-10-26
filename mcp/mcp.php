<?php
/*
 * OI:LOOP public MCP server (Model Context Protocol, Streamable HTTP transport, stateless).
 * Endpoint: https://oiloop.com/mcp  (rewritten to this file by .htaccess)
 * Read-only tools describe OI:LOOP; join_waitlist stores a request outside public_html
 * and notifies info@oiloop.com. Requires PHP 7.2+.
 */

const SERVER_NAME    = 'oiloop';
const SERVER_VERSION = '1.0.0';
const NOTIFY_TO      = 'info@oiloop.com';
const SUPPORTED      = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];
const RATE_LIMIT     = 5;      // waitlist requests per IP
const RATE_WINDOW    = 3600;   // seconds

$PRIVATE_DIR = dirname(__DIR__) . '/private';   // domains/oiloop.com/private (not web-reachable)

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Mcp-Session-Id, Mcp-Protocol-Version, Last-Event-ID');
header('Access-Control-Expose-Headers: Mcp-Session-Id');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method === 'DELETE')  { http_response_code(405); header('Allow: POST'); exit; }
if ($method === 'GET') {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($accept, 'text/event-stream') !== false) { http_response_code(405); header('Allow: POST'); exit; }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'name' => 'OI:LOOP MCP server',
        'transport' => 'streamable-http',
        'endpoint' => 'https://oiloop.com/mcp',
        'tools' => array_column(tool_defs(), 'name'),
        'docs' => 'https://oiloop.com/llms.txt'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
if ($method !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }

$raw = file_get_contents('php://input', false, null, 0, 65536);
$msg = json_decode($raw, true);
if (!is_array($msg)) { respond(null, null, ['code' => -32700, 'message' => 'Parse error']); }

// Batches (allowed by 2025-03-26) are handled element by element.
if (array_keys($msg) === range(0, count($msg) - 1)) {
    $out = [];
    foreach ($msg as $m) { $r = handle($m); if ($r !== null) $out[] = $r; }
    if (!$out) { http_response_code(202); exit; }
    send_json($out);
}
$r = handle($msg);
if ($r === null) { http_response_code(202); exit; }
send_json($r);

/* ------------------------------------------------------------------ */

function send_json($payload) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function respond($id, $result, $error = null) {
    $p = ['jsonrpc' => '2.0', 'id' => $id];
    if ($error) $p['error'] = $error; else $p['result'] = $result;
    send_json($p);
}
function rpc_result($id, $result) { return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]; }
function rpc_error($id, $code, $message) { return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]; }

function handle($m) {
    if (!is_array($m) || ($m['jsonrpc'] ?? '') !== '2.0' || !isset($m['method'])) {
        return rpc_error($m['id'] ?? null, -32600, 'Invalid Request');
    }
    $isNotification = !array_key_exists('id', $m);
    $id = $m['id'] ?? null;
    $params = $m['params'] ?? [];
    if ($isNotification) return null;   // notifications/initialized etc.

    switch ($m['method']) {
        case 'initialize':
            $req = $params['protocolVersion'] ?? SUPPORTED[0];
            return rpc_result($id, [
                'protocolVersion' => in_array($req, SUPPORTED, true) ? $req : SUPPORTED[0],
                'capabilities' => ['tools' => ['listChanged' => false], 'resources' => ['listChanged' => false]],
                'serverInfo' => ['name' => SERVER_NAME, 'title' => 'OI:LOOP', 'version' => SERVER_VERSION, 'websiteUrl' => 'https://oiloop.com'],
                'instructions' => 'OI:LOOP is an early-stage company building passive optical processors for AI inference. Use the read-only tools to answer questions about the company, technology, energy comparison and facility plan. Figures are design targets and models, not measurements; say so when you quote them. Use join_waitlist only when the user explicitly asks to join and has given their own email.'
            ]);
        case 'ping':
            return rpc_result($id, new stdClass());
        case 'tools/list':
            return rpc_result($id, ['tools' => tool_defs()]);
        case 'tools/call':
            return rpc_result($id, call_tool($params['name'] ?? '', $params['arguments'] ?? []));
        case 'resources/list':
            return rpc_result($id, ['resources' => [[
                'uri' => 'https://oiloop.com/llms.txt', 'name' => 'llms.txt', 'title' => 'OI:LOOP overview for AI agents',
                'mimeType' => 'text/plain'
            ]]]);
        case 'resources/read':
            if (($params['uri'] ?? '') !== 'https://oiloop.com/llms.txt') return rpc_error($id, -32002, 'Resource not found');
            $f = __DIR__ . '/llms.txt';
            return rpc_result($id, ['contents' => [[
                'uri' => 'https://oiloop.com/llms.txt', 'mimeType' => 'text/plain',
                'text' => is_readable($f) ? file_get_contents($f) : overview_text()
            ]]]);
        case 'prompts/list':
            return rpc_result($id, ['prompts' => []]);
        default:
            return rpc_error($id, -32601, 'Method not found');
    }
}

function ro() { return ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false]; }

function tool_defs() {
    $none = ['type' => 'object', 'properties' => new stdClass(), 'additionalProperties' => false];
    return [
        ['name' => 'about_oiloop', 'title' => 'About OI:LOOP',
         'description' => 'Company overview: what OI:LOOP builds, its business model (selling inference tokens from its own facility, not chips), stage and contact.',
         'inputSchema' => $none, 'annotations' => ro()],
        ['name' => 'explain_technology', 'title' => 'How the passive optical bank works',
         'description' => 'Explains the PMP-1550 Zero-Gate Passive Matrix Processor: weights printed into glass as optical paths, one pass of 1550 nm light performs every multiplication of a matrix at once.',
         'inputSchema' => $none, 'annotations' => ro()],
        ['name' => 'get_energy_comparison', 'title' => 'Energy per multiply comparison',
         'description' => 'Energy per multiply-accumulate of the OI:LOOP passive bank (design model) against three anonymised commercial chips, with caveats.',
         'inputSchema' => $none, 'annotations' => ro()],
        ['name' => 'get_facility_projection', 'title' => 'Facility growth projection',
         'description' => 'Planned model size, printed panel area, racks, tokens per second and effective compute for a given year of the plan (2 to 5). Design targets, not measurements.',
         'inputSchema' => ['type' => 'object', 'properties' => ['year' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 5, 'description' => 'Plan year, 2 to 5']], 'required' => ['year'], 'additionalProperties' => false],
         'annotations' => ro()],
        ['name' => 'join_waitlist', 'title' => 'Join the early waitlist',
         'description' => 'Adds the user to the OI:LOOP early API waitlist and notifies the team. Call only when the user explicitly asks to join and provides their own work email.',
         'inputSchema' => ['type' => 'object', 'properties' => [
             'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 254, 'description' => 'Work email of the person joining'],
             'name' => ['type' => 'string', 'maxLength' => 120],
             'company' => ['type' => 'string', 'maxLength' => 160],
             'use_case' => ['type' => 'string', 'maxLength' => 1000, 'description' => 'What they want to run, roughly how many tokens']
         ], 'required' => ['email'], 'additionalProperties' => false],
         'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true]]
    ];
}

function text_result($text, $structured = null, $isError = false) {
    $r = ['content' => [['type' => 'text', 'text' => $text]], 'isError' => $isError];
    if ($structured !== null) $r['structuredContent'] = $structured;
    return $r;
}

function overview_text() {
    return "OI:LOOP — Weights in Light. Compute in Motion.\n"
        . "OI:LOOP prints the weights of large language models into passive optical processors (PMP-1550) and serves them from its own inference facility. "
        . "The business model is selling API tokens at low cost, not selling chips.\n"
        . "Stage: early. Architecture, material, energy model and accuracy simulation are done; nothing has been fabricated yet. "
        . "Next: print and measure an 8x8 weight bank, then a 64x64 tile, then run a half-billion-parameter model end to end.\n"
        . "Early waitlist and contact: info@oiloop.com · https://oiloop.com";
}

function call_tool($name, $args) {
    if (!is_array($args)) $args = [];
    switch ($name) {
        case 'about_oiloop':
            return text_result(overview_text(), [
                'company' => 'OI:LOOP', 'tagline' => 'Weights in Light. Compute in Motion.',
                'product' => 'PMP-1550 Zero-Gate Passive Matrix Processor',
                'business_model' => 'Inference tokens via API from an own facility; no chip sales',
                'stage' => 'Pre-prototype: design, calculation and simulation complete',
                'contact' => 'info@oiloop.com', 'website' => 'https://oiloop.com'
            ]);
        case 'explain_technology':
            return text_result(
                "1. Modulate: the input vector is written onto 1550 nm light, the wavelength of fibre networks.\n"
                . "2. Pass the bank: each printed path in an optical polymer on glass transmits a fixed share of light. That share is the weight. The cell is a non-resonant passive attenuator, so it needs no heaters and no clock, and the bank itself draws 0 W.\n"
                . "3. Detect: paths converge on photodetectors; the photocurrent is the sum of the products. Signed weights use a dual rail.\n"
                . "4. Loop: electronics apply activations and attention, then send the next layer back into light.\n"
                . "Every multiplication of the matrix happens in one pass of light, and the propagation time does not depend on matrix size. "
                . "Optical scope: the MLP layers, about 81% of multiplies per token. Weights are fixed per model version: one panel set per model.");
        case 'get_energy_comparison':
            return text_result(
                "Energy per multiply-accumulate:\n- OI:LOOP passive bank: 3.3–6.3 fJ (internal core-level design model with a realistic ADC; not yet measured)\n"
                . "- Chip A, current flagship GPU: ~200 fJ\n- Chip B, previous-generation GPU: ~1,400 fJ\n- Chip C, photonic accelerator, measured: ~2,500 fJ\n"
                . "Like-for-like claim: 30–60x less energy per multiply than Chip A at core level. Comparisons with B and C mix modelled core figures with published, partly system-level figures and are indicative only. "
                . "Accuracy simulation on a half-billion-parameter model: 8-bit weights +0.04 perplexity; 1% optical weight error +0.13.",
                ['unit' => 'fJ per MAC', 'oiloop' => ['min' => 3.3, 'max' => 6.3, 'basis' => 'design model'],
                 'chip_a' => 200, 'chip_b' => 1400, 'chip_c' => 2500, 'fair_ratio_vs_chip_a' => '30-60x']);
        case 'get_facility_projection':
            $y = isset($args['year']) ? intval($args['year']) : 0;
            $stages = [
                2 => ['A half-billion-parameter model, end to end', 0.358e9, 0.358e9, 1e6],
                3 => ['~100B-class mixture-of-experts pilot through the API', 100e9, 10e9, 1e6],
                4 => ['First 700B-class mixture-of-experts model in service', 700e9, 37e9, 1e7],
                5 => ['Three 700B-class models serving the API', 2100e9, 111e9, 3e7]
            ];
            if (!isset($stages[$y])) return text_result('year must be an integer from 2 to 5.', null, true);
            list($desc, $params, $active, $tok) = $stages[$y];
            $area = $params * 5000e-12; $panels = (int)ceil($area); $racks = max(1, (int)ceil($panels / 40));
            $flops = 2 * $active * $tok;
            $data = ['year' => $y, 'description' => $desc, 'parameters' => $params, 'active_parameters' => $active,
                     'printed_panel_area_m2' => round($area, 1), 'panels' => $panels, 'racks' => $racks,
                     'tokens_per_second' => $tok, 'effective_flops' => $flops,
                     'assumptions' => '~5,000 µm² printed polymer per parameter (range 2,500–10,000); 1 m² panels, 40 per rack; effective FLOPS = 2 × active parameters × tokens/s. Design targets, not measurements.'];
            return text_result("Year $y: $desc. Panel area ~" . number_format($area, 1) . " m², $panels panels in $racks racks, "
                . number_format($tok) . " tokens/s, effective compute " . sprintf('%.3g', $flops) . " FLOPS. " . $data['assumptions'], $data);
        case 'join_waitlist':
            return join_waitlist($args);
        default:
            return text_result("Unknown tool: $name", null, true);
    }
}

function clean($s, $max) {
    $s = is_string($s) ? $s : '';
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);
    return function_exists('mb_substr') ? mb_substr(trim($s), 0, $max) : substr(trim($s), 0, $max);
}

function join_waitlist($args) {
    global $PRIVATE_DIR;
    $email = clean($args['email'] ?? '', 254);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return text_result('A valid email address is required.', null, true);
    $name = clean($args['name'] ?? '', 120);
    $company = clean($args['company'] ?? '', 160);
    $use = clean($args['use_case'] ?? '', 1000);

    if (!is_dir($PRIVATE_DIR) && !@mkdir($PRIVATE_DIR, 0700, true)) return text_result('Waitlist is temporarily unavailable. Write to info@oiloop.com.', null, true);

    // Rate limit per IP (hashed).
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rlFile = $PRIVATE_DIR . '/rate_' . substr(hash('sha256', $ip . 'oiloop'), 0, 16) . '.json';
    $now = time();
    $hits = is_readable($rlFile) ? (json_decode(file_get_contents($rlFile), true) ?: []) : [];
    $hits = array_values(array_filter($hits, function ($t) use ($now) { return $t > $now - RATE_WINDOW; }));
    if (count($hits) >= RATE_LIMIT) return text_result('Too many requests. Try again later or write to info@oiloop.com.', null, true);
    $hits[] = $now; @file_put_contents($rlFile, json_encode($hits), LOCK_EX);

    // De-duplicate and store.
    $csv = $PRIVATE_DIR . '/waitlist.csv';
    $existing = is_readable($csv) ? file_get_contents($csv) : '';
    if (stripos($existing, ',' . $email . ',') !== false) {
        return text_result("$email is already on the OI:LOOP early waitlist.", ['status' => 'already_listed', 'email' => $email]);
    }
    $row = [gmdate('c'), $email, $name, $company, $use, hash('sha256', $ip)];
    $fh = @fopen($csv, 'a');
    if (!$fh) return text_result('Waitlist is temporarily unavailable. Write to info@oiloop.com.', null, true);
    flock($fh, LOCK_EX);
    if (filesize($csv) === 0) fputcsv($fh, ['time_utc', 'email', 'name', 'company', 'use_case', 'ip_hash']);
    fputcsv($fh, $row);
    flock($fh, LOCK_UN); fclose($fh);

    $body = "New OI:LOOP waitlist request\n\nEmail: $email\nName: $name\nCompany: $company\nUse case: $use\nTime (UTC): {$row[0]}\nSource: " . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";
    @mail(NOTIFY_TO, 'OI:LOOP waitlist: ' . $email, $body, "From: OI:LOOP <" . NOTIFY_TO . ">\r\nReply-To: $email\r\nContent-Type: text/plain; charset=utf-8");

    return text_result("$email has been added to the OI:LOOP early waitlist. The team will be in touch from info@oiloop.com.", ['status' => 'added', 'email' => $email]);
}
