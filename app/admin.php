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

// ── Handle AJAX from admin panel ────────────────────────────────────────────
if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $pdo = get_db();
    $a = $_POST['ajax'];

    if ($a === 'save_item') {
        $id = trim($_POST['id']);
        $exists = $pdo->prepare("SELECT COUNT(*) FROM items WHERE id=?")->execute([$id])
            && $pdo->query("SELECT COUNT(*) FROM items WHERE id=" . $pdo->quote($id))->fetchColumn();

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

// ── Load data for display ────────────────────────────────────────────────────
$pdo = $loggedIn ? get_db() : null;
$phases = $loggedIn ? $pdo->query("SELECT * FROM phases ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC) : [];
$items  = $loggedIn ? $pdo->query(
    "SELECT i.*, COALESCE(s.is_done,0) as is_done FROM items i
     LEFT JOIN state s ON s.item_id=i.id ORDER BY i.phase_id, i.sort_order"
)->fetchAll(PDO::FETCH_ASSOC) : [];
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin · Draaiboek Danique & Rens</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<style>
  :root {
    --gold:#c9a96e; --ink:#2d2420; --soft:#7a6258;
    --surface:#fffcf9; --border:rgba(201,169,110,0.2);
    --red:#c0392b; --green:#4a7c59; --sage:#edf5f0;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:#f7f0e8;color:var(--ink);min-height:100vh}

  /* LOGIN */
  .login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .login-card{background:var(--surface);border-radius:20px;padding:36px 28px;width:100%;max-width:360px;
    box-shadow:0 8px 40px rgba(45,36,32,0.13)}
  .login-title{font-family:'Cormorant Garamond',serif;font-size:1.8rem;color:var(--ink);text-align:center}
  .login-sub{font-size:0.8rem;color:var(--soft);text-align:center;margin-top:6px;margin-bottom:28px}
  .form-group{margin-bottom:16px}
  label{font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--soft);display:block;margin-bottom:6px}
  input[type=password],input[type=text],input[type=time],select,textarea{
    width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px;
    font-family:'Inter',sans-serif;font-size:0.88rem;color:var(--ink);background:var(--surface);
    transition:border-color .2s}
  input:focus,select:focus,textarea:focus{outline:none;border-color:var(--gold)}
  .btn{width:100%;padding:12px;border-radius:10px;border:none;background:var(--ink);color:#f5ede0;
    font-family:'Inter',sans-serif;font-weight:700;font-size:0.9rem;cursor:pointer;transition:opacity .2s}
  .btn:hover{opacity:.85}
  .btn-danger{background:var(--red)}
  .btn-sm{width:auto;padding:6px 14px;font-size:.78rem;border-radius:8px}
  .btn-gold{background:var(--gold);color:white}
  .error{color:var(--red);font-size:.82rem;margin-bottom:14px;text-align:center}

  /* ADMIN */
  .admin-header{background:linear-gradient(135deg,#2d2420,#4a3020);color:#f5ede0;padding:16px 20px;
    display:flex;justify-content:space-between;align-items:center}
  .admin-header h1{font-family:'Cormorant Garamond',serif;font-size:1.5rem}
  .admin-header a{color:var(--gold);font-size:.8rem;text-decoration:none}

  .admin-body{max-width:900px;margin:24px auto;padding:0 16px}

  .card{background:var(--surface);border-radius:16px;box-shadow:0 2px 12px rgba(45,36,32,.07);
    border:1px solid var(--border);margin-bottom:20px;overflow:hidden}
  .card-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;
    justify-content:space-between;align-items:center}
  .card-head h2{font-size:.95rem;font-weight:700}
  .card-body{padding:16px 18px}

  table{width:100%;border-collapse:collapse;font-size:.82rem}
  th{text-align:left;padding:8px 10px;font-size:.68rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.08em;color:var(--soft);border-bottom:2px solid var(--border)}
  td{padding:8px 10px;border-bottom:1px solid var(--border);vertical-align:middle}
  tr:last-child td{border:none}
  tr.done td{opacity:.5;text-decoration:line-through}
  .badge-secret{background:#9b59b6;color:white;border-radius:6px;padding:1px 7px;
    font-size:.65rem;font-weight:700}

  .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;
    align-items:center;justify-content:center;padding:20px}
  .modal-bg.open{display:flex}
  .modal{background:var(--surface);border-radius:18px;width:100%;max-width:500px;
    padding:24px;box-shadow:0 16px 48px rgba(0,0,0,.2)}
  .modal h3{font-family:'Cormorant Garamond',serif;font-size:1.3rem;margin-bottom:18px}
  .row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .modal-actions{display:flex;gap:10px;margin-top:18px}

  .toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%) translateY(80px);
    background:var(--ink);color:#f5ede0;padding:10px 20px;border-radius:30px;
    font-size:.82rem;font-weight:600;transition:transform .3s ease;z-index:999;white-space:nowrap}
  .toast.show{transform:translateX(-50%) translateY(0)}

  .danger-zone{border:2px solid var(--red);border-radius:12px;padding:14px 16px;margin-top:8px}
  .danger-title{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
    color:var(--red);margin-bottom:8px}
</style>
</head>
<body>

<?php if (!$loggedIn): ?>
<!-- ── LOGIN ── -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-title">💍 Admin</div>
    <div class="login-sub">Draaiboek Danique & Rens · 9 augustus 2026</div>
    <?php if (!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif ?>
    <form method="POST">
      <div class="form-group">
        <label>Wachtwoord</label>
        <input type="password" name="password" autofocus placeholder="••••••••" required/>
      </div>
      <button class="btn" type="submit">Inloggen</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ── ADMIN PANEL ── -->
<div class="admin-header">
  <h1>⚙️ Draaiboek beheer</h1>
  <a href="?logout=1">Uitloggen</a>
</div>

<div class="admin-body">

  <!-- PER FASE -->
  <?php foreach ($phases as $phase):
    $phaseItems = array_filter($items, fn($i) => $i['phase_id'] === $phase['id']);
  ?>
  <div class="card">
    <div class="card-head">
      <h2><?= $phase['emoji'] ?> <?= htmlspecialchars($phase['label']) ?></h2>
      <button class="btn btn-sm btn-gold" onclick="openNew('<?= $phase['id'] ?>', '<?= $phase['date'] ?>')">+ Toevoegen</button>
    </div>
    <div class="card-body" style="padding:0">
      <table>
        <thead>
          <tr><th>Tijd</th><th>Wat</th><th>Wie</th><th>Locatie</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($phaseItems as $item): ?>
          <tr class="<?= $item['is_done'] ? 'done' : '' ?>">
            <td style="white-space:nowrap;color:#c9a96e;font-weight:700">
              <?= $item['time_start'] ? htmlspecialchars($item['time_start']) : '—' ?>
              <?= $item['time_end'] ? '–' . htmlspecialchars($item['time_end']) : '' ?>
            </td>
            <td>
              <?= htmlspecialchars($item['what']) ?>
              <?= $item['is_secret'] ? '<span class="badge-secret">🎺</span>' : '' ?>
            </td>
            <td style="color:#7a6258"><?= htmlspecialchars($item['who']) ?></td>
            <td style="color:#b8a89e;font-size:.75rem"><?= htmlspecialchars($item['location']) ?></td>
            <td style="white-space:nowrap">
              <button class="btn btn-sm" onclick="openEdit(<?= htmlspecialchars(json_encode($item)) ?>)">✏️</button>
              <button class="btn btn-sm btn-danger" onclick="deleteItem('<?= $item['id'] ?>')">🗑</button>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endforeach ?>

  <!-- DANGER ZONE -->
  <div class="card">
    <div class="card-head"><h2>⚠️ Beheer</h2></div>
    <div class="card-body">
      <div class="danger-zone">
        <div class="danger-title">Gevaarlijke acties</div>
        <p style="font-size:.82rem;color:#7a6258;margin-bottom:12px">
          Reset alle vinkjes en notities (handige herstart na een repetitie).
        </p>
        <button class="btn btn-sm btn-danger" onclick="resetState()">🔄 Alle voortgang resetten</button>
      </div>
    </div>
  </div>

</div>

<!-- ── EDIT MODAL ── -->
<div class="modal-bg" id="modal">
  <div class="modal">
    <h3 id="modalTitle">Item bewerken</h3>
    <form id="itemForm">
      <input type="hidden" id="fId" name="id"/>
      <input type="hidden" id="fPhase" name="phase_id"/>
      <input type="hidden" id="fDate" name="date"/>
      <div class="row-2">
        <div class="form-group">
          <label>Begintijd</label>
          <input type="time" id="fTimeStart" name="time_start"/>
        </div>
        <div class="form-group">
          <label>Eindtijd</label>
          <input type="time" id="fTimeEnd" name="time_end"/>
        </div>
      </div>
      <div class="form-group">
        <label>Wat</label>
        <textarea id="fWhat" name="what" rows="2" required></textarea>
      </div>
      <div class="form-group">
        <label>Wie</label>
        <input type="text" id="fWho" name="who" required/>
      </div>
      <div class="form-group">
        <label>Locatie</label>
        <input type="text" id="fLoc" name="location"/>
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" id="fSecret" name="is_secret" style="width:auto"/>
        <label for="fSecret" style="text-transform:none;letter-spacing:0;font-size:.85rem;margin:0">
          🎺 Verrassing voor gasten
        </label>
      </div>
      <div class="modal-actions">
        <button type="submit" class="btn" style="flex:1">Opslaan</button>
        <button type="button" class="btn" style="background:#e0d8d0;color:var(--ink);flex:0 0 auto;width:auto;padding:12px 20px"
          onclick="closeModal()">Annuleren</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
let editingId = null;

function openEdit(item) {
  editingId = item.id;
  document.getElementById('modalTitle').textContent = 'Item bewerken';
  document.getElementById('fId').value = item.id;
  document.getElementById('fPhase').value = item.phase_id;
  document.getElementById('fDate').value = item.date;
  document.getElementById('fTimeStart').value = item.time_start || '';
  document.getElementById('fTimeEnd').value = item.time_end || '';
  document.getElementById('fWhat').value = item.what;
  document.getElementById('fWho').value = item.who;
  document.getElementById('fLoc').value = item.location;
  document.getElementById('fSecret').checked = !!parseInt(item.is_secret);
  document.getElementById('modal').classList.add('open');
}

function openNew(phaseId, date) {
  editingId = null;
  document.getElementById('modalTitle').textContent = 'Nieuw item';
  document.getElementById('fId').value = 'item_' + Date.now();
  document.getElementById('fPhase').value = phaseId;
  document.getElementById('fDate').value = date;
  document.getElementById('fTimeStart').value = '';
  document.getElementById('fTimeEnd').value = '';
  document.getElementById('fWhat').value = '';
  document.getElementById('fWho').value = '';
  document.getElementById('fLoc').value = '';
  document.getElementById('fSecret').checked = false;
  document.getElementById('modal').classList.add('open');
}

function closeModal() {
  document.getElementById('modal').classList.remove('open');
}

document.getElementById('itemForm').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('ajax', 'save_item');
  if (document.getElementById('fSecret').checked) fd.set('is_secret','1');
  const r = await fetch('admin.php', { method:'POST', body: fd });
  const j = await r.json();
  if (j.ok) { showToast('Opgeslagen ✓'); setTimeout(() => location.reload(), 800); }
  else showToast('Fout: ' + j.error);
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

document.getElementById('modal').addEventListener('click', e => {
  if (e.target === document.getElementById('modal')) closeModal();
});
</script>

<?php endif ?>
</body>
</html>
