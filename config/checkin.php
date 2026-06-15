<?php
/* ============================================================
   Logic tiket bersama: check-in, pembatalan, kuota/settings.
   Dipakai oleh admin & ticket.php.
   ============================================================ */

/** Buat semua tabel pendukung jika belum ada. */
function ensureTicketTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS checkins (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        registration_id  INT          NOT NULL,
        kode_tiket       VARCHAR(30)  NOT NULL UNIQUE,
        checked_in_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reg (registration_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cancelled_tickets (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        registration_id  INT          NOT NULL,
        kode_tiket       VARCHAR(30)  NOT NULL UNIQUE,
        cancelled_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reg (registration_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        skey VARCHAR(50)  PRIMARY KEY,
        sval VARCHAR(255) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Parse kode tiket FOAS13-XXXXNNN → batch, nomor urut, kode utama. */
function parseTicketCode(string $code): ?array {
    $code = strtoupper(trim($code));
    if (!preg_match('/^FOAS13-([A-Z0-9]{4})([0-9]{3})$/', $code, $m)) return null;
    return [
        'full'       => $code,
        'batch'      => $m[1],
        'seq'        => (int)$m[2],
        'kode_utama' => 'FOAS13-' . $m[1] . '001',
    ];
}

/** Semua kode tiket (urut) dari kode utama + jumlah. */
function deriveAllCodes(string $kodeUtama, int $jumlah): array {
    $batch = substr($kodeUtama, 7, 4);
    $codes = [];
    for ($i = 0; $i < $jumlah; $i++) {
        $codes[] = 'FOAS13-' . $batch . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
    }
    return $codes;
}

/* ---------- Map per registrasi ---------- */
function mapByReg(PDO $pdo, string $table, array $regIds): array {
    if (!$regIds) return [];
    try {
        $ph   = implode(',', array_fill(0, count($regIds), '?'));
        $stmt = $pdo->prepare("SELECT registration_id, kode_tiket FROM $table WHERE registration_id IN ($ph)");
        $stmt->execute($regIds);
        $map = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int)$r['registration_id']][] = $r['kode_tiket'];
        }
        return $map;
    } catch (Exception $e) { return []; }
}
function getCheckedMap(PDO $pdo, array $regIds): array   { return mapByReg($pdo, 'checkins', $regIds); }
function getCancelledMap(PDO $pdo, array $regIds): array  { return mapByReg($pdo, 'cancelled_tickets', $regIds); }

function countChecked(PDO $pdo, int $regId): int {
    try { $s = $pdo->prepare("SELECT COUNT(*) FROM checkins WHERE registration_id = ?"); $s->execute([$regId]); return (int)$s->fetchColumn(); }
    catch (Exception $e) { return 0; }
}
function countCancelled(PDO $pdo, int $regId): int {
    try { $s = $pdo->prepare("SELECT COUNT(*) FROM cancelled_tickets WHERE registration_id = ?"); $s->execute([$regId]); return (int)$s->fetchColumn(); }
    catch (Exception $e) { return 0; }
}

/* ---------- Kuota ---------- */

/** Total tiket aktif terjual = SUM(jumlah_tiket) - jumlah dibatalkan. */
function getTotalSold(PDO $pdo): int {
    try {
        $sum  = (int)$pdo->query("SELECT COALESCE(SUM(jumlah_tiket),0) FROM registrations")->fetchColumn();
        $canc = (int)$pdo->query("SELECT COUNT(*) FROM cancelled_tickets")->fetchColumn();
        return max(0, $sum - $canc);
    } catch (Exception $e) { return 0; }
}

function getSetting(PDO $pdo, string $key, $default = null) {
    try { $s = $pdo->prepare("SELECT sval FROM settings WHERE skey = ?"); $s->execute([$key]);
          $v = $s->fetchColumn(); return ($v === false) ? $default : $v; }
    catch (Exception $e) { return $default; }
}
function setSetting(PDO $pdo, string $key, string $val): void {
    $pdo->prepare("INSERT INTO settings (skey, sval) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE sval = VALUES(sval)")->execute([$key, $val]);
}
