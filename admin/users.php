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
        if ($action === 'save') {
            // Satu form untuk tambah (id=0) & edit (id>0)
            $id = (int)($_POST['id'] ?? 0);
            $u  = trim($_POST['username'] ?? '');
            $p  = (string)($_POST['password'] ?? '');
            $r  = ($_POST['role'] ?? 'admin') === 'ticketing' ? 'ticketing' : 'admin';
            if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $u)) {
                header('Location: users.php?msg=baduser'); exit;
            }

            if ($id === 0) {
                // Tambah akun baru — password wajib
                if (strlen($p) < 4) { header('Location: users.php?msg=badpass'); exit; }
                $chk = $pdo->prepare("SELECT 1 FROM admin_users WHERE username = ?");
                $chk->execute([$u]);
                if ($chk->fetchColumn()) { header('Location: users.php?msg=dupe'); exit; }
                $pdo->prepare("INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)")
                    ->execute([$u, password_hash($p, PASSWORD_DEFAULT), $r]);
                header('Location: users.php?msg=added'); exit;
            }

            // Edit akun — password opsional (kosong = tidak diganti)
            $s = $pdo->prepare("SELECT username, role FROM admin_users WHERE id = ?");
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) { header('Location: users.php?msg=err'); exit; }
            if ($p !== '' && strlen($p) < 4) { header('Location: users.php?msg=badpass'); exit; }
            if ($u !== $row['username']) {
                $chk = $pdo->prepare("SELECT 1 FROM admin_users WHERE username = ? AND id <> ?");
                $chk->execute([$u, $id]);
                if ($chk->fetchColumn()) { header('Location: users.php?msg=dupe'); exit; }
            }
            if ($row['username'] === $me && $r !== ($row['role'] ?: 'admin')) {
                header('Location: users.php?msg=selfrole'); exit;
            }
            $pdo->prepare("UPDATE admin_users SET username = ?, role = ? WHERE id = ?")->execute([$u, $r, $id]);
            if ($p !== '') {
                $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($p, PASSWORD_DEFAULT), $id]);
            }
            // Bila username sendiri diubah, sinkronkan session agar tidak logout paksa
            if ($row['username'] === $me && $u !== $me) $_SESSION['admin_user'] = $u;
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
            'updated' => 'Akun berhasil diperbarui.',
            'deleted' => 'Akun berhasil dihapus.',
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

        <!-- Tambah / Edit Akun (satu form) -->
        <div class="detail-card" style="max-width:520px;margin-bottom:1.5rem;" id="userFormCard">
            <h3 id="userFormTitle">Tambah Akun</h3>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="fId" value="0">
                <label class="adm-label">Username</label>
                <input type="text" name="username" id="fUsername" class="adm-input" autocomplete="off" required>
                <label class="adm-label">Password <small id="fPassHint" style="display:none;color:#888;font-weight:400;">(kosongkan jika tidak diganti)</small></label>
                <input type="password" name="password" id="fPassword" class="adm-input" autocomplete="new-password" required>
                <label class="adm-label">Role</label>
                <select name="role" id="fRole" class="adm-input">
                    <option value="admin">Admin</option>
                    <option value="ticketing">Ticketing</option>
                </select>
                <div style="display:flex;gap:0.6rem;margin-top:1rem;">
                    <button type="submit" class="adm-btn-primary" id="fSubmit">Save</button>
                    <button type="button" class="adm-btn-ghost" id="btnCancelEdit" style="display:none;" onclick="resetUserForm()">Batal</button>
                </div>
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
                        <td><?= ($u['role'] ?? 'admin') === 'ticketing' ? 'ticketing' : 'admin' ?></td>
                        <td><?= date('d M Y H:i', strtotime($u['created_at'])) ?></td>
                        <td class="col-aksi">
                            <div class="aksi-stack">
                                <button type="button" class="adm-btn-sm adm-btn-detail"
                                        onclick="editUser(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= ($u['role'] ?? 'admin') === 'ticketing' ? 'ticketing' : 'admin' ?>', <?= $u['username'] === $me ? 'true' : 'false' ?>)">Edit</button>
                                <form method="POST" onsubmit="return confirm('Hapus akun <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="adm-btn-sm adm-btn-danger" style="width:100%;">Delete</button>
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
        /* Isi form atas dengan data user untuk diedit */
        function editUser(id, username, role, isSelf) {
            document.getElementById('fId').value       = id;
            document.getElementById('fUsername').value = username;
            document.getElementById('fRole').value     = role;
            document.getElementById('fRole').disabled  = isSelf;   // role sendiri tidak bisa diubah
            var p = document.getElementById('fPassword');
            p.value = ''; p.required = false;
            document.getElementById('fPassHint').style.display  = 'inline';
            document.getElementById('userFormTitle').textContent = 'Edit Akun: ' + username;
            document.getElementById('btnCancelEdit').style.display = 'inline-flex';
            document.getElementById('userFormCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        function resetUserForm() {
            document.getElementById('fId').value       = '0';
            document.getElementById('fUsername').value = '';
            document.getElementById('fRole').value     = 'admin';
            document.getElementById('fRole').disabled  = false;
            var p = document.getElementById('fPassword');
            p.value = ''; p.required = true;
            document.getElementById('fPassHint').style.display  = 'none';
            document.getElementById('userFormTitle').textContent = 'Tambah Akun';
            document.getElementById('btnCancelEdit').style.display = 'none';
        }
        </script>

<?php require __DIR__ . '/partials/footer.php'; ?>
