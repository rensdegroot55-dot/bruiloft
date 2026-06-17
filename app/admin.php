<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ── Auth ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
    } else {
        $error = 'Ongeldig wachtwoord.';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
$loggedIn = !empty($_SESSION['admin']);

// ── Handle AJAX ─────────────────────────────────────────────────────────────
if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $pdo = get_db();
    $a = $_POST['ajax'];

    if ($a === 'save_item') {
        $id = trim($_POST['id']);
        $exists = $pdo->query("SELECT COUNT(*) FROM items WHERE id=" . $pdo->quote($id))->fetchColumn();
        if ($exists) {
            $pdo->prepare(
                "UPDATE items SET time_start=?,time_end=?,who=?,what=?,location=?,is_secret=? WHERE id=?"
            )->execute([
                $_POST['time_start'] ?: null, $_POST['time_end'] ?: null,
                $_POST['who'], $_POST['what'], $_POST['location'],
                (int)isset($_POST['is_secret']), $id
            ]);
        } else {
            $pdo->prepare(
                "INSERT INTO items (id,phase_id,sort_order,time_start,time_end,who,what,location,is_secret,date)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $id, $_POST['phase_id'],
                $pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM items WHERE phase_id="
                    . $pdo->quote($_POST['phase_id']))->fetchColumn(),
                $_POST['time_start'] ?: null, $_POST['time_end'] ?: null,
                $_POST['who'], $_POST['what'], $_POST['location'],
                (int)isset($_POST['is_secret']), $_POST['date'] ?? '2026-08-09'
            ]);
        }
        log_change($pdo, 'admin_save_item', ['id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($a === 'delete_item') {
        $id = trim($_POST['id']);
        $pdo->prepare("DELETE FROM items WHERE id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM state WHERE item_id=?")->execute([$id]);
        log_change($pdo, 'admin_delete_item', ['id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($a === 'reset_state') {
        $pdo->exec("DELETE FROM state");
        $pdo->exec("UPDATE corsages SET is_done=0");
        log_change($pdo, 'admin_reset', []);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['error' => 'unknown']);
    exit;
}

// ── Load data ────────────────────────────────────────────────────────────────
$pdo    = $loggedIn ? get_db() : null;
$phases = $loggedIn ? $pdo->query("SELECT * FROM phases ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC) : [];
$items  = $loggedIn ? $pdo->query(
    "SELECT i.*, COALESCE(s.is_done,0) as is_done FROM items i
     LEFT JOIN state s ON s.item_id=i.id ORDER BY i.phase_id, i.sort_order"
)->fetchAll(PDO::FETCH_ASSOC) : [];

// items hersorteren op tijdstip voor weergave
usort($items, fn($a,$b) => [
    $a['phase_id'],
    ($a['time_start'] === null ? 1 : 0),
    $a['time_start'] ?? '',
    $a['sort_order'],
] <=> [
    $b['phase_id'],
    ($b['time_start'] === null ? 1 : 0),
    $b['time_start'] ?? '',
    $b['sort_order'],
]);
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"/>
<title>Admin · Draaiboek Danique & Rens</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<style>
  :root{
    --gold:#c9a96e;--ink:#2d2420;--soft:#7a6258;
    --surface:#fffcf9;--border:rgba(201,169,110,.22);
    --red:#c0392b;--green:#4a7c59;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html,body{height:100%;font-family:'Inter',sans-serif;background:#f7f0e8;color:var(--ink)}

  /* ── LOGIN ── */
  .login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .login-card{background:var(--surface);border-radius:20px;padding:36px 24px;width:100%;max-width:360px;
    box-shadow:0 8px 40px rgba(45,36,32,.13)}
  .login-title{font-family:'Cormorant Garamond',serif;font-size:1.9rem;text-align:center}
  .login-sub{font-size:.8rem;color:var(--soft);text-align:center;margin:6px 0 28px}
  .error-msg{color:var(--red);font-size:.82rem;margin-bottom:14px;text-align:center}

  /* ── FORMS ── */
  .fg{margin-bottom:14px}
  label{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.08em;color:var(--soft);margin-bottom:5px}
  input[type=password],input[type=text],input[type=time],select,textarea{
    width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px;
    font-family:'Inter',sans-serif;font-size:.88rem;color:var(--ink);background:var(--surface);
    transition:border-color .2s;-webkit-appearance:none}
  input:focus,select:focus,textarea:focus{outline:none;border-color:var(--gold)}
  textarea{resize:vertical;min-height:64px}

  /* ── BUTTONS ── */
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;
    padding:11px 18px;border-radius:10px;border:none;background:var(--ink);color:#f5ede0;
    font-family:'Inter',sans-serif;font-weight:700;font-size:.88rem;cursor:pointer;
    transition:opacity .2s;-webkit-tap-highlight-color:transparent;touch-action:manipulation}
  .btn:active{opacity:.75}
  .btn-block{width:100%}
  .btn-sm{padding:7px 13px;font-size:.76rem;border-radius:8px}
  .btn-gold{background:var(--gold);color:#fff}
  .btn-danger{background:var(--red);color:#fff}
  .btn-ghost{background:rgba(45,36,32,.08);color:var(--ink)}

  /* ── ADMIN HEADER ── */
  .admin-hdr{background:linear-gradient(135deg,#2d2420,#4a3020);color:#f5ede0;
    padding:14px 16px;display:flex;justify-content:space-between;align-items:center;
    position:sticky;top:0;z-index:100}
  .admin-hdr h1{font-family:'Cormorant Garamond',serif;font-size:1.4rem}
  .admin-hdr a{color:var(--gold);font-size:.8rem;text-decoration:none;padding:6px 10px;
    border-radius:8px;border:1px solid rgba(201,169,110,.4)}

  /* ── PAGE BODY ── */
  .admin-body{padding:16px;max-width:700px;margin:0 auto;padding-bottom:40px}

  /* ── PHASE SECTION ── */
  .phase-section{margin-bottom:24px}
  .phase-hdr{display:flex;justify-content:space-between;align-items:center;
    margin-bottom:10px;padding:0 2px}
  .phase-hdr h2{font-family:'Cormorant Garamond',serif;font-size:1.2rem}

  /* ── ITEM CARD ── */
  .item-card{background:var(--surface);border-radius:14px;border:1px solid var(--border);
    margin-bottom:8px;overflow:hidden;box-shadow:0 1px 6px rgba(45,36,32,.05)}
  .item-card.done{opacity:.5}
  .item-main{padding:12px 14px}
  .item-time{font-size:.72rem;font-weight:700;color:var(--gold);margin-bottom:3px;letter-spacing:.03em}
  .item-what{font-size:.9rem;font-weight:600;color:var(--ink);margin-bottom:3px;line-height:1.35}
  .item-who{font-size:.78rem;color:var(--soft)}
  .item-loc{font-size:.73rem;color:#b8a89e;margin-top:2px}
  .badge-secret{background:#9b59b6;color:#fff;border-radius:6px;padding:1px 7px;
    font-size:.62rem;font-weight:700;vertical-align:middle;margin-left:5px}
  .item-actions{border-top:1px solid var(--border);display:flex;background:#faf6f0}
  .item-actions button{flex:1;border:none;background:none;padding:10px;cursor:pointer;
    font-size:.78rem;font-weight:600;color:var(--soft);transition:background .15s;
    -webkit-tap-highlight-color:transparent}
  .item-actions button:first-child{border-right:1px solid var(--border)}
  .item-actions button:hover,.item-actions button:active{background:rgba(201,169,110,.12)}
  .item-actions .del-btn{color:var(--red)}

  /* ── DANGER ZONE ── */
  .danger-card{border:2px solid var(--red);border-radius:14px;padding:16px;margin-top:8px;
    background:var(--surface)}
  .danger-title{font-size:.72rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.08em;color:var(--red);margin-bottom:8px}

  /* ── MODAL (bottom sheet on mobile) ── */
  .modal-bg{display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.5);
    align-items:flex-end;justify-content:center}
  .modal-bg.open{display:flex}
  .modal{background:var(--surface);width:100%;max-width:540px;
    border-radius:22px 22px 0 0;padding:24px 20px 32px;
    max-height:92vh;overflow-y:auto;-webkit-overflow-scrolling:touch}
  @media(min-width:600px){
    .modal-bg{align-items:center}
    .modal{border-radius:18px;max-height:90vh}
  }
  .modal-drag{width:40px;height:4px;background:var(--border);border-radius:2px;
    margin:0 auto 18px}
  .modal h3{font-family:'Cormorant Garamond',serif;font-size:1.3rem;margin-bottom:18px}
  .row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .modal-actions{display:flex;gap:10px;margin-top:20px}
  .check-row{display:flex;align-items:center;gap:10px;padding:6px 0}
  .check-row input[type=checkbox]{width:20px;height:20px;accent-color:var(--gold);flex-shrink:0}
  .check-row label{text-transform:none;letter-spacing:0;font-size:.88rem;font-weight:500;margin:0}

  /* ── TOAST ── */
  .toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);
    background:var(--ink);color:#f5ede0;padding:10px 22px;border-radius:30px;
    font-size:.82rem;font-weight:600;transition:transform .3s ease;z-index:999;white-space:nowrap;
    pointer-events:none}
  .toast.show{transform:translateX(-50%) translateY(0)}
</style>
</head>
<body>

<?php if (!$loggedIn): ?>
<!-- ── LOGIN ── -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-title">💍 Admin</div>
    <div class="login-sub">Draaiboek Danique &amp; Rens · 9 augustus 2026</div>
    <?php if (!empty($error)): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif ?>
    <form method="POST">
      <div class="fg">
        <label>Wachtwoord</label>
        <input type="password" name="password" autofocus placeholder="••••••••" required/>
      </div>
      <button class="btn btn-block" type="submit">Inloggen</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ── ADMIN PANEL ── -->
<div class="admin-hdr">
  <h1>⚙️ Beheer</h1>
  <a href="?logout=1">Uitloggen</a>
</div>

<div class="admin-body">

  <?php foreach ($phases as $phase):
    $phaseItems = array_values(array_filter($items, fn($i) => $i['phase_id'] === $phase['id']));
  ?>
  <div class="phase-section">
    <div class="phase-hdr">
      <h2><?= $phase['emoji'] ?> <?= htmlspecialchars($phase['label']) ?></h2>
      <button class="btn btn-sm btn-gold" onclick="openNew('<?= $phase['id'] ?>','<?= $phase['date'] ?>')">+ Nieuw</button>
    </div>

    <?php foreach ($phaseItems as $item): ?>
    <div class="item-card<?= $item['is_done'] ? ' done' : '' ?>">
      <div class="item-main">
        <div class="item-time">
          <?= $item['time_start'] ? htmlspecialchars($item['time_start']) : '—' ?>
          <?= $item['time_end'] ? ' – ' . htmlspecialchars($item['time_end']) : '' ?>
        </div>
        <div class="item-what">
          <?= htmlspecialchars($item['what']) ?>
          <?= $item['is_secret'] ? '<span class="badge-secret">🎺 verrassing</span>' : '' ?>
        </div>
        <div class="item-who"><?= htmlspecialchars($item['who']) ?></div>
        <?php if ($item['location']): ?>
        <div class="item-loc">📍 <?= htmlspecialchars($item['location']) ?></div>
        <?php endif ?>
      </div>
      <div class="item-actions">
        <button onclick="openEdit(this)" data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES) ?>'>✏️ Bewerken</button>
        <button class="del-btn" onclick="deleteItem('<?= htmlspecialchars($item['id']) ?>')">🗑 Verwijderen</button>
      </div>
    </div>
    <?php endforeach ?>

    <?php if (empty($phaseItems)): ?>
    <p style="font-size:.8rem;color:var(--soft);padding:8px 2px">Geen items in deze fase.</p>
    <?php endif ?>
  </div>
  <?php endforeach ?>

  <!-- DANGER ZONE -->
  <div class="danger-card">
    <div class="danger-title">⚠️ Gevaarlijke acties</div>
    <p style="font-size:.82rem;color:var(--soft);margin-bottom:14px">
      Reset alle vinkjes en notities. Handig na een repetitie.
    </p>
    <button class="btn btn-sm btn-danger" onclick="resetState()">🔄 Alles resetten</button>
  </div>

</div>

<!-- ── EDIT / NEW MODAL ── -->
<div class="modal-bg" id="modal">
  <div class="modal" id="modalInner">
    <div class="modal-drag"></div>
    <h3 id="modalTitle">Item bewerken</h3>
    <form id="itemForm">
      <input type="hidden" id="fId" name="id"/>
      <input type="hidden" id="fPhase" name="phase_id"/>
      <input type="hidden" id="fDate" name="date"/>
      <div class="row-2">
        <div class="fg">
          <label>Begintijd</label>
          <input type="time" id="fTimeStart" name="time_start"/>
        </div>
        <div class="fg">
          <label>Eindtijd</label>
          <input type="time" id="fTimeEnd" name="time_end"/>
        </div>
      </div>
      <div class="fg">
        <label>Wat</label>
        <textarea id="fWhat" name="what" rows="2" required></textarea>
      </div>
      <div class="fg">
        <label>Wie</label>
        <input type="text" id="fWho" name="who" required/>
      </div>
      <div class="fg">
        <label>Locatie</label>
        <input type="text" id="fLoc" name="location"/>
      </div>
      <div class="check-row">
        <input type="checkbox" id="fSecret" name="is_secret"/>
        <label for="fSecret">🎺 Verrassing voor gasten</label>
      </div>
      <div class="modal-actions">
        <button type="submit" class="btn" style="flex:1">Opslaan</button>
        <button type="button" class="btn btn-ghost" style="flex:0 0 auto" onclick="closeModal()">Annuleren</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
function openEdit(btn) {
  const item = JSON.parse(btn.dataset.item);
  document.getElementById('modalTitle').textContent = 'Item bewerken';
  document.getElementById('fId').value        = item.id;
  document.getElementById('fPhase').value     = item.phase_id;
  document.getElementById('fDate').value      = item.date;
  document.getElementById('fTimeStart').value = item.time_start || '';
  document.getElementById('fTimeEnd').value   = item.time_end   || '';
  document.getElementById('fWhat').value      = item.what;
  document.getElementById('fWho').value       = item.who;
  document.getElementById('fLoc').value       = item.location;
  document.getElementById('fSecret').checked  = !!parseInt(item.is_secret);
  document.getElementById('modal').classList.add('open');
}

function openNew(phaseId, date) {
  document.getElementById('modalTitle').textContent = 'Nieuw item';
  document.getElementById('fId').value        = 'item_' + Date.now();
  document.getElementById('fPhase').value     = phaseId;
  document.getElementById('fDate').value      = date;
  document.getElementById('fTimeStart').value = '';
  document.getElementById('fTimeEnd').value   = '';
  document.getElementById('fWhat').value      = '';
  document.getElementById('fWho').value       = '';
  document.getElementById('fLoc').value       = '';
  document.getElementById('fSecret').checked  = false;
  document.getElementById('modal').classList.add('open');
}

function closeModal() {
  document.getElementById('modal').classList.remove('open');
}

document.getElementById('modal').addEventListener('click', e => {
  if (e.target === document.getElementById('modal')) closeModal();
});

document.getElementById('itemForm').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('ajax', 'save_item');
  if (!document.getElementById('fSecret').checked) fd.delete('is_secret');
  const r = await fetch('admin.php', { method:'POST', body: fd });
  const j = await r.json();
  if (j.ok) { showToast('Opgeslagen ✓'); setTimeout(() => location.reload(), 800); }
  else showToast('Fout: ' + (j.error || '?'));
});

async function deleteItem(id) {
  if (!confirm('Dit item verwijderen?')) return;
  const fd = new FormData();
  fd.append('ajax', 'delete_item');
  fd.append('id', id);
  const r = await fetch('admin.php', { method:'POST', body: fd });
  const j = await r.json();
  if (j.ok) { showToast('Verwijderd'); setTimeout(() => location.reload(), 600); }
}

async function resetState() {
  if (!confirm('Alle vinkjes en notities resetten? Dit kan niet ongedaan worden gemaakt.')) return;
  const fd = new FormData();
  fd.append('ajax', 'reset_state');
  await fetch('admin.php', { method:'POST', body: fd });
  showToast('Voortgang gereset ✓');
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}
</script>

<?php endif ?>
</body>
</html>
