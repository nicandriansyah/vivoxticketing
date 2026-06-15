<?php
/* ============================================================
   Kredensial admin panel.
   Default di bawah untuk dev lokal (user: admin / pass: admin123).
   Di production, override lewat config/admin.local.php (gitignored):
       <?php
       $ADMIN_USER      = 'namauser';
       $ADMIN_PASS_HASH = '...';   // hasil password_hash('passwordbaru', PASSWORD_DEFAULT)
   ============================================================ */

$ADMIN_USER      = 'admin';
// hash dari 'admin123'
$ADMIN_PASS_HASH = '$2y$10$sp1MCFrcRB3RxhPiMumdXOeT/pZDeLuuLL5rfWRK/ulPZeEBnFSxq';

$localCfg = __DIR__ . '/admin.local.php';
if (file_exists($localCfg)) require_once $localCfg;

/** Buat tabel admin_users jika belum ada; seed akun default jika kosong. */
function ensureAdminUsersTable(PDO $pdo): void {
    global $ADMIN_USER, $ADMIN_PASS_HASH;
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        username      VARCHAR(50)  NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    if ($cnt === 0) {
        $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)")
            ->execute([$ADMIN_USER, $ADMIN_PASS_HASH]);
    }
}

/** Verifikasi login: cek tabel admin_users, fallback ke kredensial config. */
function verifyAdminLogin(?PDO $pdo, string $user, string $pass): bool {
    global $ADMIN_USER, $ADMIN_PASS_HASH;
    if ($pdo) {
        try {
            ensureAdminUsersTable($pdo);
            $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$user]);
            $hash = $stmt->fetchColumn();
            if ($hash && password_verify($pass, $hash)) return true;
        } catch (Exception $e) { /* fallback di bawah */ }
    }
    // Fallback master (selalu bisa login walau DB down / akun terhapus)
    return ($user === $ADMIN_USER && password_verify($pass, $ADMIN_PASS_HASH));
}
