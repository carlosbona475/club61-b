<?php

declare(strict_types=1);

require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/geo.php';
require_once __DIR__ . '/../../config/followers.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/online.php';

$me = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
if ($me === '') {
    header('Location: /features/auth/login.php');

    exit;
}

$radiusKm = isset($_GET['radius']) ? (float) $_GET['radius'] : 10.0;
if ($radiusKm < 0.5) {
    $radiusKm = 0.5;
}
if ($radiusKm > 100.0) {
    $radiusKm = 100.0;
}

$csrf = csrf_token();
$serviceOk = defined('SUPABASE_SERVICE_KEY') && SUPABASE_SERVICE_KEY !== '';

$myLat = null;
$myLng = null;
$nearby = [];
$fetchError = false;
$hasMyCoords = false;

if ($serviceOk) {
    $myUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($me) . '&select=' . rawurlencode('latitude,longitude');
    $ch = curl_init($myUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Accept: application/json',
        ],
    ]);
    $myRaw = curl_exec($ch);
    $myCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($myRaw !== false && $myCode >= 200 && $myCode < 300) {
        $myRows = json_decode((string) $myRaw, true);
        if (is_array($myRows) && isset($myRows[0])) {
            $ml = $myRows[0]['latitude'] ?? null;
            $mg = $myRows[0]['longitude'] ?? null;
            if ($ml !== null && $mg !== null && is_numeric($ml) && is_numeric($mg)) {
                $myLat = (float) $ml;
                $myLng = (float) $mg;
                $hasMyCoords = true;
            }
        }
    }
}

$followingSet = followers_get_following_id_set($me);

if ($serviceOk && $hasMyCoords && $myLat !== null && $myLng !== null) {
    $candidatesUrl = SUPABASE_URL . '/rest/v1/profiles?'
        . 'select=' . rawurlencode('id,username,avatar_url,bairro,cidade,latitude,longitude,last_seen')
        . '&id=neq.' . urlencode($me)
        . '&latitude=not.is.null'
        . '&longitude=not.is.null'
        . '&limit=200'
        . '&order=id.asc';

    $ch = curl_init($candidatesUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Accept: application/json',
        ],
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $rows = [];
    if ($raw !== false && $code >= 200 && $code < 300) {
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            $rows = $decoded;
        }
    } else {
        $fetchError = true;
        $fallbackUrl = SUPABASE_URL . '/rest/v1/profiles?'
            . 'select=' . rawurlencode('id,username,avatar_url,bairro,cidade,latitude,longitude,last_seen')
            . '&id=neq.' . urlencode($me)
            . '&limit=200'
            . '&order=id.asc';
        $ch2 = curl_init($fallbackUrl);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_SERVICE_KEY,
                'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
                'Accept: application/json',
            ],
        ]);
        $raw2 = curl_exec($ch2);
        $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        if ($raw2 !== false && $code2 >= 200 && $code2 < 300) {
            $decoded2 = json_decode((string) $raw2, true);
            if (is_array($decoded2)) {
                $rows = $decoded2;
            }
        }
    }

    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $oid = isset($r['id']) ? (string) $r['id'] : '';
        $lat = $r['latitude'] ?? null;
        $lng = $r['longitude'] ?? null;
        if ($oid === '' || $lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
            continue;
        }
        $dist = distanceKm($myLat, $myLng, (float) $lat, (float) $lng);
        if ($dist <= $radiusKm) {
            $nearby[] = ['row' => $r, 'km' => $dist];
        }
    }

    usort($nearby, static function (array $a, array $b): int {
        return $a['km'] <=> $b['km'];
    });
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perto — Club61</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html { height: 100%; }
        body.nearby-page {
            margin: 0;
            min-height: 100%;
            background: #0A0A0A;
            color: #fff;
            font-family: 'Segoe UI', system-ui, -apple-system, Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        nav {
            background: #111111;
            border-bottom: 1px solid #222222;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 60px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-brand {
            font-size: 1.3rem;
            font-weight: 800;
            color: #C9A84C;
            letter-spacing: 2px;
            text-decoration: none;
        }
        .nav-links {
            display: flex;
            gap: 24px;
            list-style: none;
            margin: 0;
            padding: 0;
            flex-wrap: wrap;
        }
        .nav-links a {
            color: #888;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.15s ease;
        }
        .nav-links a:hover { color: #C9A84C; }
        .page { padding: 24px 16px 48px; }
        .auth-wrap {
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
        }
        @media (max-width: 480px) {
            nav { padding: 0 16px; }
            .page { padding: 20px 14px 40px; }
        }
        .auth-card {
            background: #111111;
            border: 1px solid #222222;
            border-radius: 4px;
            padding: 40px 32px 36px;
            margin-bottom: 20px;
        }
        @media (max-width: 480px) {
            .auth-card { padding: 28px 20px 24px; }
        }
        .auth-brand {
            margin: 0 0 8px;
            font-size: clamp(1.5rem, 4vw, 1.85rem);
            font-weight: 600;
            letter-spacing: 0.06em;
            text-align: center;
            color: #C9A84C;
        }
        .auth-sub {
            margin: 0 0 20px;
            font-size: 0.8125rem;
            text-align: center;
            color: #888;
            line-height: 1.45;
        }
        .alert.error {
            margin-bottom: 16px;
            padding: 12px 14px;
            font-size: 0.8125rem;
            line-height: 1.45;
            border-radius: 4px;
            color: #FF6B6B;
            background: rgba(255, 107, 107, 0.08);
            border: 1px solid rgba(255, 107, 107, 0.25);
        }
        .nearby-list { display: flex; flex-direction: column; gap: 12px; }
        .nearby-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: #1A1A1A;
            border: 1px solid #333333;
            border-radius: 4px;
            text-decoration: none;
            color: inherit;
        }
        .nearby-card__main { flex: 1; min-width: 0; display: flex; align-items: center; gap: 12px; }
        .nearby-card__avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #333;
            background: #111;
            flex-shrink: 0;
        }
        .avatar-wrapper{position:relative;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
        .online-dot{position:absolute;bottom:2px;right:2px;width:10px;height:10px;background:#00ff88;border-radius:50%;border:2px solid #111}
        .nearby-card__fallback {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: 2px solid #333;
            background: #111;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            color: #7B2EFF;
            flex-shrink: 0;
        }
        .nearby-card__text { min-width: 0; }
        .nearby-card__user {
            font-weight: 600;
            font-size: 0.95rem;
            color: #fff;
            margin: 0 0 4px;
            word-break: break-word;
        }
        .nearby-card__bairro {
            margin: 0;
            font-size: 0.8rem;
            color: #888;
        }
        .nearby-card__dist {
            font-size: 0.78rem;
            color: #C9A84C;
            font-weight: 600;
            white-space: nowrap;
        }
        .nearby-card__actions {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #fff;
            background: #7B2EFF;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: box-shadow 0.25s ease, background 0.15s ease;
            font-family: inherit;
        }
        .btn:hover {
            box-shadow: 0 0 24px rgba(123, 46, 255, 0.55), 0 0 48px rgba(123, 46, 255, 0.2);
        }
        .btn-follow {
            background: rgba(123, 46, 255, 0.25);
            color: #e9e0ff;
            border: 1px solid rgba(123, 46, 255, 0.5);
        }
        .btn-follow:hover {
            background: #7B2EFF;
            color: #fff;
            border-color: #7B2EFF;
        }
        .btn-follow.is-following {
            background: #1a1a1a;
            color: #b0b0b0;
            border-color: #444;
        }
        .btn-follow.is-following:hover {
            background: #252525;
            color: #e0e0e0;
            border-color: #555;
        }
        .empty {
            text-align: center;
            color: #888;
            font-size: 0.8125rem;
            padding: 16px 14px;
            border: 1px solid #333333;
            border-radius: 4px;
            background: #1A1A1A;
        }
        .nearby-toolbar { margin: 0 0 14px; display: flex; justify-content: flex-end; }
        .nearby-toolbar select {
            background: #1a1a1a; color: #ddd; border: 1px solid #333; border-radius: 6px;
            padding: 8px 10px; font-size: 0.82rem;
        }
        #nearby-map { width: 100%; height: 260px; border: 1px solid #2a2a2a; border-radius: 8px; margin-bottom: 14px; }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body class="nearby-page">
    <nav>
        <a class="nav-brand" href="/features/feed/index.php">Club61</a>
        <ul class="nav-links">
            <li><a href="/features/feed/index.php">Feed</a></li>
            <li><a href="/features/profile/index.php">Perfil</a></li>
            <li><a href="/features/auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="page">
        <div class="auth-wrap">
            <div class="auth-card">
                <h1 class="auth-brand">Membros perto</h1>
                <p class="auth-sub">Até <?= htmlspecialchars((string) round($radiusKm, 1), ENT_QUOTES, 'UTF-8') ?> km de distância (linha reta). Ordenado do mais próximo.</p>
                <?php if ($hasMyCoords): ?>
                    <form class="nearby-toolbar" method="get" action="/features/explore/nearby.php">
                        <select name="radius" onchange="this.form.submit()">
                            <?php foreach ([5, 10, 25, 50, 100] as $rk): ?>
                                <option value="<?= $rk ?>"<?= (int) round($radiusKm) === $rk ? ' selected' : '' ?>>Raio: <?= $rk ?> km</option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <div id="nearby-map" aria-label="Mapa de membros próximos"></div>
                <?php endif; ?>

                <?php if (!$serviceOk): ?>
                    <div class="alert error">Serviço temporariamente indisponível.</div>
                <?php elseif (!$hasMyCoords): ?>
                    <div class="empty">
                        Para ver pessoas por perto, defina a sua localização em
                        <a href="/features/profile/index.php#form-perfil" style="color:#C9A84C;">Perfil</a>
                        → <strong>Usar minha localização</strong>.
                    </div>
                <?php else: ?>
                    <?php if ($fetchError): ?>
                        <p class="auth-sub" style="margin-top:0;">Lista carregada com filtro alternativo (coordenadas validadas no servidor).</p>
                    <?php endif; ?>
                    <?php if (empty($nearby)): ?>
                        <div class="empty">Nenhum membro com localização neste raio (máx. 200 perfis analisados).</div>
                    <?php else: ?>
                        <div class="nearby-list">
                            <?php foreach ($nearby as $item): ?>
                                <?php
                                $r = $item['row'];
                                $km = $item['km'];
                                $uid = isset($r['id']) ? (string) $r['id'] : '';
                                $uname = isset($r['username']) ? trim((string) $r['username']) : '';
                                $bairro = isset($r['bairro']) ? trim((string) $r['bairro']) : '';
                                if ($bairro === '' && isset($r['cidade'])) {
                                    $bairro = trim((string) $r['cidade']);
                                }
                                if ($bairro === '') {
                                    $bairro = '—';
                                }
                                $av = isset($r['avatar_url']) ? trim((string) $r['avatar_url']) : '';
                                $lastSeen = isset($r['last_seen']) ? (string) $r['last_seen'] : null;
                                $isOnline = isUserOnline($lastSeen);
                                $isFollowing = $uid !== '' && isset($followingSet[$uid]);
                                ?>
                                <div class="nearby-card">
                                    <a class="nearby-card__main" href="/features/profile/view.php?id=<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if ($av !== ''): ?>
                                            <span class="avatar-wrapper"><img class="nearby-card__avatar" src="<?= htmlspecialchars($av, ENT_QUOTES, 'UTF-8') ?>" alt=""><?php if ($isOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?></span>
                                        <?php else: ?>
                                            <div class="nearby-card__fallback avatar-wrapper" aria-hidden="true">&#128100;<?php if ($isOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?></div>
                                        <?php endif; ?>
                                        <div class="nearby-card__text">
                                            <p class="nearby-card__user"><?= htmlspecialchars($uname !== '' ? '@' . $uname : 'Membro', ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="nearby-card__bairro"><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= htmlspecialchars($bairro, ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                    </a>
                                    <div class="nearby-card__actions">
                                        <span class="nearby-card__dist"><?= htmlspecialchars(number_format($km, 1, '.', ''), ENT_QUOTES, 'UTF-8') ?> km</span>
                                        <button type="button" class="btn btn-follow js-follow-toggle<?= $isFollowing ? ' is-following' : '' ?>"
                                            data-following-id="<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>"
                                            data-following="<?= $isFollowing ? '1' : '0' ?>"
                                            aria-pressed="<?= $isFollowing ? 'true' : 'false' ?>">
                                            <?= $isFollowing ? 'Seguindo' : 'Seguir' ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    (function () {
        var NEARBY_CSRF = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var ME = {
            lat: <?= json_encode($myLat, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            lng: <?= json_encode($myLng, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
        };
        var POINTS = <?= json_encode(array_map(static function (array $it): array {
            $r = $it['row'];
            return [
                'id' => (string) ($r['id'] ?? ''),
                'lat' => isset($r['latitude']) ? (float) $r['latitude'] : null,
                'lng' => isset($r['longitude']) ? (float) $r['longitude'] : null,
                'username' => isset($r['username']) ? (string) $r['username'] : 'Membro',
            ];
        }, $nearby), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        if (window.L && ME.lat !== null && ME.lng !== null) {
            var mapEl = document.getElementById('nearby-map');
            if (mapEl) {
                var map = L.map(mapEl).setView([Number(ME.lat), Number(ME.lng)], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
                var meIcon = L.divIcon({ className: '', html: '<div style="width:14px;height:14px;background:#3da5ff;border:2px solid #111;border-radius:50%"></div>', iconSize: [14,14] });
                var nearIcon = L.divIcon({ className: '', html: '<div style="width:14px;height:14px;background:#7B2EFF;border:2px solid #111;border-radius:50%"></div>', iconSize: [14,14] });
                L.marker([Number(ME.lat), Number(ME.lng)], { icon: meIcon }).addTo(map).bindPopup('Você');
                POINTS.forEach(function (p) {
                    if (typeof p.lat !== 'number' || typeof p.lng !== 'number') return;
                    var href = '/features/profile/view.php?id=' + encodeURIComponent(p.id || '');
                    L.marker([p.lat, p.lng], { icon: nearIcon }).addTo(map).bindPopup('<a href="' + href + '" style="color:#7B2EFF;text-decoration:none">@' + String(p.username || 'Membro') + '</a>');
                });
            }
        }
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.js-follow-toggle');
            if (!btn || !document.body.classList.contains('nearby-page')) return;
            e.preventDefault();
            var fid = btn.getAttribute('data-following-id');
            if (!fid) return;
            btn.disabled = true;
            var fd = new FormData();
            fd.append('following_id', fid);
            fd.append('csrf', NEARBY_CSRF);
            fetch('/features/profile/follow_toggle.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.csrf) NEARBY_CSRF = d.csrf;
                    if (!d.ok) return;
                    var follow = !!d.following;
                    btn.setAttribute('data-following', follow ? '1' : '0');
                    btn.setAttribute('aria-pressed', follow ? 'true' : 'false');
                    btn.textContent = follow ? 'Seguindo' : 'Seguir';
                    btn.classList.toggle('is-following', follow);
                })
                .catch(function () {})
                .finally(function () { btn.disabled = false; });
        });
    })();
    </script>
</body>
</html>
