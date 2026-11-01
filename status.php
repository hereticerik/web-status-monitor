<?php
/**
 * Written By HereticErik - https://github.com/hereticerik
 * For educational/demonstration purposes, use with caution and secure this page if you use it for something.
 * Checks HTTP(S) endpoints (in parallel) and raw TCP ports
 * Auto-refreshes every 30 seconds
 * JSON output available at status.php?format=json
 */

// ---------- CONFIG ----------
$services = [
    // --- Web ---
    ['name' => 'Google',           'type' => 'http', 'target' => 'https://www.google.com'],
    ['name' => 'Microsoft',          'type' => 'http', 'target' => 'https://www.microsoft.com'],
    ['name' => 'Discord',           'type' => 'http', 'target' => 'https://discord.com'],
    ['name' => 'Stoat',       'type' => 'http', 'target' => 'https://stoat.chat'],
    ['name' => 'Fluxer',        'type' => 'http', 'target' => 'https://fluxer.app'],
    ['name' => 'Matrix',           'type' => 'http', 'target' => 'https://matrix.org'],
    ['name' => 'IRC',        'type' => 'http', 'target' => 'https://libera.chat'],
    ['name' => 'Arch Linux',            'type' => 'http', 'target' => 'https://archlinux.org'],
    ['name' => 'Github',        'type' => 'http', 'target' => 'https://github.com'],

    // --- Social / Media / Gaming ---
    ['name' => 'Tiktok',          'type' => 'http', 'target' => 'https://www.tiktok.com'],
    ['name' => 'Facebook',          'type' => 'http', 'target' => 'https://www.facebook.com'],
    ['name' => 'Bluesky',           'type' => 'http', 'target' => 'https://bsky.app'],
    ['name' => 'Twitter/X',           'type' => 'http', 'target' => 'https://x.com'],
    ['name' => 'Mastodon',      'type' => 'http', 'target' => 'https://mastodon.social'],
    ['name' => 'Instagram',          'type' => 'http', 'target' => 'https://www.instagram.com'],

    // --- Developer registries ---
    ['name' => 'IRC Server',     'type' => 'tcp', 'target' => 'irc.libera.chat', 'port' => 6697],
    ['name' => 'Spotify',             'type' => 'http', 'target' => 'https://open.spotify.com'],

    // --- TCP checks ---
    ['name' => 'Minecraft Server',       'type' => 'tcp',  'target' => 'mc.hypixel.net',    'port' => 25565],
    ['name' => 'Veloren Server',   'type' => 'tcp',  'target' => 'server.veloren.net',    'port' => 14004],
    ['name' => 'Reddit',       'type' => 'http',  'target' => 'https://www.reddit.com'],
];

$timeout = 5;          // seconds per check
$refreshSeconds = 30;  // page auto-refresh interval
$userAgent = 'Mozilla/5.0 (compatible; StatusPage/1.0)';
// ----------------------------

/**
 * Run many HTTP requests at once. $jobs is [index => url].
 * Returns [index => ['code' => int, 'error' => string, 'ms' => int]].
 */
function http_batch(array $jobs, int $timeout, string $ua, bool $headOnly): array
{
    $mh = curl_multi_init();
    $handles = [];

    foreach ($jobs as $i => $url) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => $ua,
        ];
        if ($headOnly) {
            $opts[CURLOPT_NOBODY] = true;
        }
        curl_setopt_array($ch, $opts);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }

    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running && $status === CURLM_OK);

    $out = [];
    foreach ($handles as $i => $ch) {
        $out[$i] = [
            'code'  => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'error' => curl_error($ch),
            'ms'    => (int) round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000),
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    return $out;
}

function check_tcp(string $host, int $port, int $timeout): array
{
    $start = microtime(true);
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $ms = (int) round((microtime(true) - $start) * 1000);

    if ($fp) {
        fclose($fp);
        return ['up' => true, 'ms' => $ms, 'details' => "Port $port open"];
    }
    return ['up' => false, 'ms' => $ms, 'details' => $errstr ?: "Port $port closed"];
}

// ---- Run checks ----
$results = [];

// HTTP: HEAD pass for everything, then GET retry for servers that reject HEAD
$httpJobs = [];
foreach ($services as $i => $svc) {
    if ($svc['type'] === 'http') {
        $httpJobs[$i] = $svc['target'];
    }
}
$httpRes = http_batch($httpJobs, $timeout, $userAgent, true);

$retry = [];
foreach ($httpRes as $i => $r) {
    if ($r['code'] === 405 || $r['code'] === 501) {
        $retry[$i] = $httpJobs[$i];
    }
}
if ($retry) {
    foreach (http_batch($retry, $timeout, $userAgent, false) as $i => $r) {
        $httpRes[$i] = $r;
    }
}

foreach ($services as $i => $svc) {
    if ($svc['type'] === 'tcp') {
        $r = check_tcp($svc['target'], (int) $svc['port'], $timeout);
        $target = "{$svc['target']}:{$svc['port']}";
    } else {
        $h = $httpRes[$i];
        $expect = $svc['expect'] ?? [];
        $up = $expect ? in_array($h['code'], $expect, true) : ($h['code'] >= 200 && $h['code'] < 400);
        $r = [
            'up'      => $up,
            'ms'      => $h['ms'],
            'details' => $h['code'] ? "HTTP {$h['code']}" : ($h['error'] ?: 'No response'),
        ];
        $target = $svc['target'];
    }

    $results[] = [
        'name'    => $svc['name'],
        'target'  => $target,
        'up'      => $r['up'],
        'ms'      => $r['ms'],
        'details' => $r['details'],
    ];
}

$upCount = count(array_filter($results, fn($r) => $r['up']));
$total   = count($results);
$allUp   = $upCount === $total;
$checked = gmdate('Y-m-d H:i:s') . ' UTC';

// JSON mode
if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'checked_at' => $checked,
        'all_up'     => $allUp,
        'services'   => $results,
    ], JSON_PRETTY_PRINT);
    exit;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="<?= (int) $refreshSeconds ?>">
<title>Services Status Monitor – <?= $allUp ? 'All systems operational' : 'Issues detected' ?></title>
<style>
     /* web page style/color can be adjusted here */
    a, a:link, a:visited, a:hover, a:active { color: #a00b91; } 

    :root {
        --bg: #f5f6f8; --card: #fff; --text: #1c1f24; --muted: #6b7280;
        --border: #e3e5e9; --up: #16a34a; --down: #dc2626;
    }
    @media (prefers-color-scheme: dark) {
        :root {
            --bg: #1c1c1c; --card: #181818; --text: #e8eaed; --muted: #9aa0a9;
            --border: #272b33; --up: #22c55e; --down: #f87171;
        }
    }
    * { box-sizing: border-box; }
    body {
        margin: 0; padding: 2rem 1rem; background: var(--bg); color: var(--text);
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    .wrap { max-width: 720px; margin: 0 auto; }
    h1 { font-size: 1.5rem; margin: 0 0 1rem; }
    .banner {
        padding: 1rem 1.25rem; border-radius: 10px; color: #fff; font-weight: 600;
        background: var(--up); margin-bottom: 1.25rem;
    }
    .banner.bad { background: var(--down); }
    .card {
        background: var(--card); border: 1px solid var(--border); border-radius: 10px;
        overflow: hidden;
    }
    .row {
        display: flex; align-items: center; gap: .85rem; padding: .9rem 1.25rem;
        border-bottom: 1px solid var(--border);
    }
    .row:last-child { border-bottom: 0; }
    .dot { width: 12px; height: 12px; border-radius: 50%; flex: none; background: var(--up); }
    .down .dot { background: var(--down); }
    .info { flex: 1; min-width: 0; }
    .name { font-weight: 600; }
    .target { color: var(--muted); font-size: .85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .right { text-align: right; font-size: .85rem; color: var(--muted); }
    .badge { font-weight: 700; color: var(--up); text-transform: uppercase; font-size: .8rem; }
    .down .badge { color: var(--down); }
    .foot { margin-top: 1rem; color: var(--muted); font-size: .8rem; text-align: center; }
</style>
</head>
<body>
<div class="wrap">
    <h1>SERVER/SERVICES STATUS MONITOR</h1>

    <div class="banner <?= $allUp ? '' : 'bad' ?>">
        <?= $allUp ? 'All systems operational' : "$upCount of $total services up" ?>
    </div>

    <div class="card">
        <?php foreach ($results as $r): ?>
            <div class="row <?= $r['up'] ? 'up' : 'down' ?>">
                <span class="dot"></span>
                <div class="info">
                    <div class="name"><?= h($r['name']) ?></div>
                    <div class="target"><?= h($r['target']) ?></div>
                </div>
                <div class="right">
                    <div class="badge"><?= $r['up'] ? 'Up' : 'Down' ?></div>
                    <div><?= h($r['details']) ?> · <?= (int) $r['ms'] ?> ms</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="foot">
        Last checked <?= h($checked) ?> · refreshes every <?= (int) $refreshSeconds ?>s ·
        <a href="?format=json" style="color:inherit">JSON</a> | Powered by <a href="https://github.com/hereticerik">Github</a>
    </div>
</div>
</body>
</html>
