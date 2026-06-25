<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method']);
if (!$pdo) out(['ok' => false, 'error' => 'no-db']);
try { ensureTicketTables($pdo); } catch (Exception $e) {}

// Judul slide global → settings
if (isset($_POST['title'])) {
    try { setSetting($pdo, 'ppt_title', substr((string)$_POST['title'], 0, 200)); } catch (Exception $e) {}
}

$slides = json_decode($_POST['slides'] ?? '[]', true);
if (!is_array($slides)) out(['ok' => false, 'error' => 'bad-data']);

$saved = 0;
try {
    $stmt = $pdo->prepare("UPDATE registrations SET slide_layout = ? WHERE id = ?");
    foreach ($slides as $s) {
        $id = (int)($s['id'] ?? 0);
        if (!$id) continue;
        // Simpan hanya field yang relevan
        $layout = [
            'nama'  => (string)($s['nama']  ?? ''),
            'years' => (string)($s['years'] ?? ''),
            'ph'    => $s['ph'] ?? null,
            'na'    => $s['na'] ?? null,
            'yr'    => $s['yr'] ?? null,
        ];
        $stmt->execute([json_encode($layout, JSON_UNESCAPED_UNICODE), $id]);
        $saved++;
    }
} catch (Exception $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
}

out(['ok' => true, 'saved' => $saved]);
