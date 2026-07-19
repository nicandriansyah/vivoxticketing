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

    // Data arwah (1 registrasi bisa punya sampai 5 arwah)
    $pdo->exec("CREATE TABLE IF NOT EXISTS arwah (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        registration_id  INT          NOT NULL,
        urutan           TINYINT      NOT NULL DEFAULT 1,
        nama_arwah       VARCHAR(255) NOT NULL,
        tahun_lahir      SMALLINT     NULL,
        tahun_wafat      SMALLINT     NULL,
        hubungan_arwah   VARCHAR(50)  NULL,
        foto_arwah       VARCHAR(255) NULL,
        slide_layout     TEXT         NULL,
        INDEX idx_reg (registration_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migrasi sekali: ubah kolom hubungan_arwah dari ENUM lama ke VARCHAR
    // agar mendukung kategori baru (orang_tua_ayah, pasangan, dll).
    try {
        if ((int)getSetting($pdo, 'schema_version', '1') < 2) {
            $pdo->exec("ALTER TABLE registrations MODIFY hubungan_arwah VARCHAR(50) NULL");
            setSetting($pdo, 'schema_version', '2');
        }
        if ((int)getSetting($pdo, 'schema_version', '1') < 3) {
            // Kolom penyimpanan layout slide PPT (JSON)
            $col = $pdo->query("SHOW COLUMNS FROM registrations LIKE 'slide_layout'")->fetch();
            if (!$col) $pdo->exec("ALTER TABLE registrations ADD COLUMN slide_layout TEXT NULL");
            setSetting($pdo, 'schema_version', '3');
        }
    } catch (Exception $e) { /* abaikan bila tak ada akses ALTER */ }
}

/** Pilihan hubungan arwah: key => label tampilan. */
function hubunganOptions(): array {
    return [
        'orang_tua_ayah' => 'Orang Tua - Ayah',
        'orang_tua_ibu'  => 'Orang Tua - Ibu',
        'pasangan'       => 'Pasangan',
        'anak'           => 'Anak',
        'saudara'        => 'Saudara/Kerabat/Teman',
    ];
}

/** Label tampilan untuk sebuah key hubungan (termasuk nilai lama). */
function hubunganLabel(?string $key): string {
    if (!$key) return '-';
    $opt    = hubunganOptions();
    $legacy = ['orang_tua' => 'Orang Tua', 'anak' => 'Anak', 'saudara' => 'Saudara'];
    return $opt[$key] ?? $legacy[$key] ?? '-';
}

/** Parse kode tiket FOAS14-XXXXNNN → batch, nomor urut, kode utama. */
function parseTicketCode(string $code): ?array {
    $code = strtoupper(trim($code));
    if (!preg_match('/^FOAS14-([A-Z0-9]{4})([0-9]{3})$/', $code, $m)) return null;
    return [
        'full'       => $code,
        'batch'      => $m[1],
        'seq'        => (int)$m[2],
        'kode_utama' => 'FOAS14-' . $m[1] . '001',
    ];
}

/** Semua kode tiket (urut) dari kode utama + jumlah. */
function deriveAllCodes(string $kodeUtama, int $jumlah): array {
    $batch = substr($kodeUtama, 7, 4);
    $codes = [];
    for ($i = 0; $i < $jumlah; $i++) {
        $codes[] = 'FOAS14-' . $batch . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
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

/* ---------- Data arwah ---------- */

/** Semua arwah untuk banyak registrasi: registration_id => [rows]. */
function getArwahMap(PDO $pdo, array $regIds): array {
    if (!$regIds) return [];
    try {
        $ph   = implode(',', array_fill(0, count($regIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM arwah WHERE registration_id IN ($ph) ORDER BY registration_id, urutan, id");
        $stmt->execute($regIds);
        $map = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int)$r['registration_id']][] = $r;
        }
        return $map;
    } catch (Exception $e) { return []; }
}

/** Daftar arwah untuk satu registrasi (urut). */
function getArwahForReg(PDO $pdo, int $regId): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM arwah WHERE registration_id = ? ORDER BY urutan, id");
        $stmt->execute([$regId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return []; }
}

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

/* ---------- Rekening sumbangan ---------- */

/** Rekening sumbangan di form registrasi (default + override settings). */
function donationAccount(?PDO $pdo): array {
    $def = [
        'bank_short' => 'BCA',
        'bank_name'  => 'PT Bank Central Asia Tbk',
        'account'    => '12345678',
        'holder'     => 'Vita Voxa Choir',
    ];
    if (!$pdo) return $def;
    return [
        'bank_short' => getSetting($pdo, 'don_bank_short', $def['bank_short']) ?: $def['bank_short'],
        'bank_name'  => getSetting($pdo, 'don_bank_name',  $def['bank_name'])  ?: $def['bank_name'],
        'account'    => getSetting($pdo, 'don_account',    $def['account'])    ?: $def['account'],
        'holder'     => getSetting($pdo, 'don_holder',     $def['holder'])     ?: $def['holder'],
    ];
}

/* ---------- Kontak bantuan ---------- */

/** Kontak bantuan yang ditampilkan di halaman 404 (default + override settings).
    'wa' dipakai untuk tampilan sekaligus link wa.me — format internasional tanpa +, mis. 62812xxx. */
function helpContact(?PDO $pdo): array {
    $def = [
        'name' => 'Ocin',
        'wa'   => '6281289622858',
    ];
    if (!$pdo) return $def;
    return [
        'name' => getSetting($pdo, 'help_name', $def['name']) ?: $def['name'],
        'wa'   => getSetting($pdo, 'help_wa',   $def['wa'])   ?: $def['wa'],
    ];
}
