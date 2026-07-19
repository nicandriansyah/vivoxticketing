<?php
require_once __DIR__ . '/auth.php';
requireAdminRole();   // khusus role admin
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/admin.php';

$dbReady = (bool)$pdo;
if ($dbReady) { try { ensureAdminUsersTable($pdo); } catch (Exception $e) { $dbReady = false; $dbErr = $e->getMessage(); } }

$me = $_SESSION['admin_user'] ?? '';

/* ---------- Handle POST (PRG) ---------- */
if ($dbReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $u = trim($_POST['username'] ?? '');
            $p = (string)($_POST['password'] ?? '');
            $r = ($_POST['role'] ?? 'admin') === 'ticketing' ? 'ticketing' : 'admin';
            if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $u)) {
                header('Location: users.php?msg=baduser'); exit;
            }
            if (strlen($p) < 4) {
                header('Location: users.php?msg=badpass'); exit;
            }
            $chk = $pdo->prepare("SELECT 1 FROM admin_users WHERE username = ?");
            $chk->execute([$u]);
            if ($chk->fetchColumn()) { header('Location: users.php?msg=dupe'); exit; }
            $pdo->prepare("INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)")
                ->execute([$u, password_hash($p, PASSWORD_DEFAULT), $r]);
            header('Location: users.php?msg=added'); exit;
        }
        if ($action === 'editrole') {
            $id = (int)($_POST['id'] ?? 0);
            $r  = ($_POST['role'] ?? 'admin') === 'ticketing' ? 'ticketing' : 'admin';
            $s = $pdo->prepare("SELECT username FROM admin_users WHERE id = ?");
            $s->execute([$id]);
            if ($s->fetchColumn() === $me) { header('Location: users.php?msg=selfrole'); exit; }
            $pdo->prepare("UPDATE admin_users SET role = ? WHERE id = ?")->execute([$r, $id]);
            header('Location: users.php?msg=role'); exit;
        }
        if ($action === 'editpass') {
            $id = (int)($_POST['id'] ?? 0);
            $p  = (string)($_POST['password'] ?? '');
            if (strlen($p) < 4) { header('Location: users.php?msg=badpass'); exit; }
            $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($p, PASSWORD_DEFAULT), $id]);
            header('Location: users.php?msg=updated'); exit;
        }
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            // Ambil username target
            $s = $pdo->prepare("SELECT username FROM admin_users WHERE id = ?");
            $s->execute([$id]);
            $target = $s->fetchColumn();
            $count  = (int)$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
            if ($target === $me)      { header('Location: users.php?msg=self');  exit; }
            if ($count <= 1)          { header('Location: users.php?msg=last');  exit; }
            $pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$id]);
            header('Location: users.php?msg=deleted'); exit;
        }
    } catch (Exception $e) {
        header('Location: users.php?msg=err'); exit;
    }
    header('Location: users.php'); exit;
}

$users = [];
if ($dbReady) {
    $users = $pdo->query("SELECT id, username, role, created_at FROM admin_users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle  = 'Setting User';
$activeMenu = 'users';
require __DIR__ . '/partials/header.php';
?>

        <?php
        $msg = $_GET['msg'] ?? '';
        $okMap = [
            'added'   => 'Akun berhasil ditambahkan.',
            'updated' => 'Password berhasil diperbarui.',
            'deleted' => 'Akun berhasil dihapus.',
            'role'    => 'Role berhasil diubah.',
        ];
        $errMap = [
            'baduser' => 'Username tidak valid (3-50 karakter: huruf, angka, . _ -).',
            'badpass' => 'Password minimal 4 karakter.',
            'dupe'    => 'Username sudah dipakai.',
            'self'    => 'Tidak bisa menghapus akun yang sedang Anda gunakan.',
            'selfrole'=> 'Tidak bisa mengubah role akun yang sedang Anda gunakan.',
            'last'    => 'Tidak bisa menghapus akun terakhir.',
            'err'     => 'Terjadi kesalahan.',
        ];
        if (isset($okMap[$msg])): ?>
            <div class="adm-success adm-flash">✓ <?= $okMap[$msg] ?></div>
        <?php elseif (isset($errMap[$msg])): ?>
            <div class="adm-alert adm-flash"><?= $errMap[$msg] ?></div>
        <?php endif; ?>

        <?php if (!$dbReady): ?>
            <div class="adm-alert">Koneksi database gagal<?= isset($dbErr) ? ': ' . htmlspecialchars($dbErr) : '' ?>.</div>
        <?php else: ?>

        <!-- Tambah Akun -->
        <div class="detail-card" style="max-width:520px;margin-bottom:1.5rem;">
            <h3>Tambah Akun</h3>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="add">
                <label class="adm-label">Username</label>
                <input type="text" name="username" class="adm-input" autocomplete="off" required>
                <label class="adm-label">Password</label>
                <input type="password" name="password" class="adm-input" autocomplete="new-password" required>
                <label class="adm-label">Role</label>
                <select name="role" class="adm-input">
                    <option value="admin">Admin — akses penuh</option>
                    <option value="ticketing">Ticketing — dashboard &amp; check-in saja</option>
                </select>
                <button type="submit" class="adm-btn-primary" style="margin-top:1rem;">Save</button>
            </form>
        </div>

        <!-- Daftar User -->
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr><th>#</th><th>Username</th><th>Role</th><th>Dibuat</th><th class="col-aksi">Aksi</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="adm-strong">
                            <?= htmlspecialchars($u['username']) ?>
                            <?php if ($u['username'] === $me): ?><small style="color:#888;">(Anda)</small><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['username'] === $me): ?>
                                <span class="badge-ok" style="font-weight:600;"><?= htmlspecialchars($u['role'] ?? 'admin') ?></span>
                            <?php else: ?>
                                <form method="POST" style="display:flex;gap:.35rem;align-items:center;"
                                      onsubmit="return confirm('Ubah role <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>?');">
                                    <input type="hidden" name="action" value="editrole">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <select name="role" class="adm-input" style="width:auto;font-size:.8rem;padding:4px 8px;">
                                        <option value="admin"     <?= ($u['role'] ?? 'admin') === 'admin'     ? 'selected' : '' ?>>admin</option>
                                        <option value="ticketing" <?= ($u['role'] ?? '')      === 'ticketing' ? 'selected' : '' ?>>ticketing</option>
                                    </select>
                                    <button type="submit" class="adm-btn-sm adm-btn-mail">✓</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M Y H:i', strtotime($u['created_at'])) ?></td>
                        <td class="col-aksi">
                            <div class="aksi-stack">
                                <button type="button" class="adm-btn-sm adm-btn-detail" onclick="togglePass(<?= (int)$u['id'] ?>)">Edit Password</button>
                                <form method="POST" onsubmit="return confirm('Hapus akun <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="adm-btn-sm adm-btn-danger" style="width:100%;">Delete</button>
                                </form>
                                <form method="POST" class="pass-form" id="pf-<?= (int)$u['id'] ?>" style="display:none;">
                                    <input type="hidden" name="action" value="editpass">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <input type="password" name="password" class="adm-input" placeholder="Password baru" required style="font-size:0.8rem;padding:5px 8px;">
                                    <button type="submit" class="adm-btn-sm adm-btn-mail">Simpan</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

        <script>
        function togglePass(id) {
            var f = document.getElementById('pf-' + id);
            f.style.display = (f.style.display === 'none' || !f.style.display) ? 'flex' : 'none';
        }
        </script>

<?php require __DIR__ . '/partials/footer.php'; ?>
