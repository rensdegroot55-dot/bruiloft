<?php
require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = get_db();

// ── Poll for changes (vervangt SSE, werkt op InfinityFree) ─────────────────
if ($action === 'poll') {
    $lastId = (int)($_GET['last_id'] ?? 0);
    $rows = $pdo->prepare(
        "SELECT id, action, payload FROM changelog WHERE id > ? ORDER BY id ASC LIMIT 50"
    );
    $rows->execute([$lastId]);
    $changes = $rows->fetchAll(PDO::FETCH_ASSOC);

    $result = array_map(fn($r) => [
        'id'      => (int)$r['id'],
        'action'  => $r['action'],
        'payload' => json_decode($r['payload'], true),
    ], $changes);

    $newLastId = count($result) ? max(array_column($result, 'id')) : $lastId;
    json_out(['changes' => $result, 'last_id' => $newLastId]);
}

// ── GET: full state ──────────────────────────────────────────────────────────
if ($action === 'get_all' || $_SERVER['REQUEST_METHOD'] === 'GET' && !$action) {
    $phases = $pdo->query(
        "SELECT * FROM phases ORDER BY sort_order"
    )->fetchAll(PDO::FETCH_ASSOC);

    $items = $pdo->query(
        "SELECT i.*, COALESCE(s.is_done,0) as is_done, COALESCE(s.note,'') as note,
                s.on_time, s.checked_at
         FROM items i
         LEFT JOIN state s ON s.item_id = i.id
         ORDER BY i.phase_id, i.time_start IS NULL, i.time_start, i.sort_order"
    )->fetchAll(PDO::FETCH_ASSOC);

    $corsages = $pdo->query(
        "SELECT * FROM corsages ORDER BY sort_order"
    )->fetchAll(PDO::FETCH_ASSOC);

    $lastChange = $pdo->query(
        "SELECT MAX(id) FROM changelog"
    )->fetchColumn() ?: 0;

    json_out(['phases' => $phases, 'items' => $items,
              'corsages' => $corsages, 'last_change_id' => $lastChange]);
}

// ── POST actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $action ?: ($body['action'] ?? '');

    switch ($action) {

        case 'toggle_item':
            $id        = $body['id'] ?? '';
            $done      = (int)($body['done'] ?? 0);
            $onTime    = isset($body['on_time']) && $body['on_time'] !== null ? (int)$body['on_time'] : null;
            $checkedAt = $done ? date('Y-m-d H:i:s') : null;
            $pdo->prepare(
                "INSERT INTO state (item_id, is_done, on_time, checked_at, note)
                 VALUES (?, ?, ?, ?, '')
                 ON DUPLICATE KEY UPDATE
                   is_done=VALUES(is_done),
                   on_time=VALUES(on_time),
                   checked_at=VALUES(checked_at)"
            )->execute([$id, $done, $onTime, $checkedAt]);
            log_change($pdo, 'toggle_item', ['id' => $id, 'done' => $done, 'on_time' => $onTime]);
            json_out(['ok' => true]);

        case 'save_note':
            $id   = $body['id'] ?? '';
            $note = $body['note'] ?? '';
            $pdo->prepare(
                "INSERT INTO state (item_id, note, is_done)
                 VALUES (?, ?, 0)
                 ON DUPLICATE KEY UPDATE note=VALUES(note)"
            )->execute([$id, $note]);
            log_change($pdo, 'save_note', ['id' => $id, 'note' => $note]);
            json_out(['ok' => true]);

        case 'toggle_corsage':
            $id   = $body['id'] ?? '';
            $done = (int)($body['done'] ?? 0);
            $pdo->prepare(
                "UPDATE corsages SET is_done=? WHERE id=?"
            )->execute([$done, $id]);
            log_change($pdo, 'toggle_corsage', ['id' => $id, 'done' => $done]);
            json_out(['ok' => true]);

        // ── Admin: item CRUD ─────────────────────────────────────────────────
        case 'admin_save_item':
            require_admin();
            $d = $body['item'] ?? [];
            $id = $d['id'] ?? '';
            if (!$id) { json_error('Missing id'); }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE id=?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn()) {
                $pdo->prepare(
                    "UPDATE items SET time_start=?,time_end=?,who=?,what=?,location=?,is_secret=? WHERE id=?"
                )->execute([
                    $d['time_start'] ?: null, $d['time_end'] ?: null,
                    $d['who'], $d['what'], $d['location'],
                    (int)($d['is_secret'] ?? 0), $id
                ]);
            } else {
                $pdo->prepare(
                    "INSERT INTO items (id,phase_id,sort_order,time_start,time_end,who,what,location,is_secret,date)
                     VALUES (?,?,?,?,?,?,?,?,?,?)"
                )->execute([
                    $id, $d['phase_id'], (int)($d['sort_order'] ?? 99),
                    $d['time_start'] ?: null, $d['time_end'] ?: null,
                    $d['who'], $d['what'], $d['location'],
                    (int)($d['is_secret'] ?? 0), $d['date'] ?? '2026-08-09'
                ]);
            }
            log_change($pdo, 'admin_save_item', ['id' => $id]);
            json_out(['ok' => true]);

        case 'admin_delete_item':
            require_admin();
            $id = $body['id'] ?? '';
            $pdo->prepare("DELETE FROM items WHERE id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM state WHERE item_id=?")->execute([$id]);
            log_change($pdo, 'admin_delete_item', ['id' => $id]);
            json_out(['ok' => true]);

        case 'admin_reorder':
            require_admin();
            foreach (($body['order'] ?? []) as $idx => $id) {
                $pdo->prepare("UPDATE items SET sort_order=? WHERE id=?")
                    ->execute([$idx, $id]);
            }
            log_change($pdo, 'admin_reorder', []);
            json_out(['ok' => true]);

        default:
            json_error('Unknown action');
    }
}

// Catch-all: onbekende GET action of leeg verzoek
json_error('Unknown action');

function require_admin(): void {
    $auth = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    if ($auth !== ADMIN_PASSWORD) {
        http_response_code(401);
        json_out(['error' => 'Unauthorized']);
        exit;
    }
}

function json_out(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_error(string $msg): void {
    http_response_code(400);
    json_out(['error' => $msg]);
}
