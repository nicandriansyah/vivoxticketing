<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
require_once __DIR__ . '/helpers.php';

$dbReady = (bool)$pdo;
if ($dbReady) { try { ensureTicketTables($pdo); } catch (Exception $e) {} }

$isTicketing = adminRole() === 'ticketing';   // role terbatas: tanpa kartu/modal setting

// Kontak bantuan halaman 404 (editable via modal)
$helpContact = helpContact($dbReady ? $pdo : null);
// Rekening sumbangan di form registrasi (editable via modal)
$donation = donationAccount($dbReady ? $pdo : null);

$stats = ['reg' => 0, 'tiket' => 0, 'sumbangan' => 0, 'email' => 0, 'checkin' => 0];
$quota = 0; $sold = 0; $remaining = 0;
$salesOpen = true; $quotaAvailable = true; $isOpen = true;
$rows  = [];
$total = 0;

$q       = trim($_GET['q'] ?? '');
$filter  = $_GET['filter'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// Query string untuk mempertahankan filter & q pada link (pagination, dll)
function qs(array $extra = []): string {
    global $q, $filter;
    $p = array_filter(['q' => $q, 'filter' => $filter]);
    $p = array_merge($p, $extra);
    return $p ? '?' . http_build_query($p) : '';
}

if ($dbReady) {
    try {
        $s = $pdo->query("SELECT
                COUNT(*)                          AS reg,
                COALESCE(SUM(jumlah_tiket),0)     AS tiket,
                COALESCE(SUM(sumbangan_amount),0) AS sumbangan,
                COALESCE(SUM(email_sent),0)       AS email
            FROM registrations")->fetch(PDO::FETCH_ASSOC);
        $stats['reg']       = (int)$s['reg'];
        $stats['tiket']     = (int)$s['tiket'];
        $stats['sumbangan'] = (float)$s['sumbangan'];
        $stats['email']     = (int)$s['email'];
        try { $stats['checkin'] = (int)$pdo->query("SELECT COUNT(*) FROM checkins")->fetchColumn(); } catch (Exception $e) {}

        // Kuota & tiket aktif terjual
        $quota     = (int)getSetting($pdo, 'ticket_quota', '0');
        $sold      = getTotalSold($pdo);
        $stats['tiket'] = $sold;                        // tampilkan tiket aktif
        $remaining = $quota > 0 ? max(0, $quota - $sold) : 0;
        $salesOpen      = ((int)getSetting($pdo, 'sales_open', '1') === 1);
        $quotaAvailable = ($quota <= 0) || ($sold < $quota);
        $isOpen         = $salesOpen && $quotaAvailable;

        $conds  = [];
        $params = [];
        list($sc, $sp) = buildSearchClause($q);
        if ($sc) { $conds[] = $sc; $params = array_merge($params, $sp); }
        if ($filter === 'email_fail') { $conds[] = 'email_sent = 0'; }
        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

        $cstmt = $pdo->prepare("SELECT COUNT(*) FROM registrations $where");
        $cstmt->execute($params);
        $total = (int)$cstmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM registrations $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $dbReady = false;
        $dbErrMsg = $e->getMessage();
    }
}

$totalPages = max(1, (int)ceil($total / $perPage));
function rp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

function deriveCodes(string $kodeUtama, int $jumlah): array {
    $batch = substr($kodeUtama, 7, 4);
    $codes = [];
    for ($i = 0; $i < $jumlah; $i++) {
        $codes[] = 'FOAS14-' . $batch . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
    }
    return $codes;
}

// Map check-in & pembatalan per registrasi
$regIds       = array_map(fn($r) => (int)$r['id'], $rows);
$checkedMap   = $dbReady ? getCheckedMap($pdo, $regIds) : [];
$cancelledMap = $dbReady ? getCancelledMap($pdo, $regIds) : [];
$arwahMap     = $dbReady ? getArwahMap($pdo, $regIds) : [];


$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
$mainClass  = 'adm-main-full';
require __DIR__ . '/partials/header.php';
?>

        <?php
        $msg = $_GET['msg'] ?? '';
        if ($msg === 'sent'): ?>
            <div class="adm-success adm-flash">✓ Email berhasil dikirim ulang.</div>
        <?php elseif ($msg === 'quota'): ?>
            <div class="adm-success adm-flash">✓ Kuota tiket berhasil diperbarui.</div>
        <?php elseif ($msg === 'opened'): ?>
            <div class="adm-success adm-flash">✓ Penjualan tiket DIBUKA.</div>
        <?php elseif ($msg === 'closed'): ?>
            <div class="adm-success adm-flash">✓ Penjualan tiket DITUTUP.</div>
        <?php elseif ($msg === 'fail'): ?>
            <div class="adm-alert adm-flash">Gagal mengirim email: <?= htmlspecialchars($_SESSION['resend_error'] ?? 'unknown') ?><?php unset($_SESSION['resend_error']); ?></div>
        <?php elseif ($msg === 'err'): ?>
            <div class="adm-alert adm-flash">Terjadi kesalahan.</div>
        <?php endif; ?>

        <?php if (!$dbReady): ?>
            <div class="adm-alert">
                Koneksi database gagal<?= isset($dbErrMsg) ? ': ' . htmlspecialchars($dbErrMsg) : '' ?>.
                Pastikan <code>config/db.local.php</code> sudah benar.
            </div>
        <?php endif; ?>

        <!-- Stat cards -->
        <div class="stat-grid">
            <?php
                $statusLabel = $isOpen ? 'DIBUKA'
                    : (!$salesOpen ? 'DITUTUP (manual)' : 'DITUTUP (Penuh)');
            ?>
            <button type="button" class="stat-card stat-card-link stat-card-user stat-card-btn stat-card-quota <?= $isOpen ? 'q-open' : 'q-closed' ?>"<?= $isTicketing ? ' style="cursor:default;"' : ' onclick="openQuotaModal()"' ?>>
                <div class="stat-label">Registrasi <?= $statusLabel ?></div>
                <div class="stat-quota-mini">
                    Booking <?= number_format($stats['tiket'], 0, ',', '.') ?> &middot;
                    Kuota <?= $quota > 0 ? number_format($quota, 0, ',', '.') : '∞' ?> &middot;
                    Sisa <?= $quota > 0 ? number_format($remaining, 0, ',', '.') : '∞' ?>
                </div>
            </button>
            <div class="stat-card">
                <div class="stat-label">Total Registrasi</div>
                <div class="stat-value"><?= number_format($stats['reg'], 0, ',', '.') ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Sudah Check-in</div>
                <div class="stat-value"><?= number_format($stats['checkin'], 0, ',', '.') ?> <small>/ <?= number_format($stats['tiket'], 0, ',', '.') ?></small></div>
            </div>
            <?php if (!$isTicketing): ?>
            <div class="stat-card">
                <div class="stat-label">Total Sumbangan</div>
                <div class="stat-value"><?= rp($stats['sumbangan']) ?></div>
            </div>
            <?php endif; ?>
            <?php $emailFail = max(0, $stats['reg'] - $stats['email']); ?>
            <div class="stat-card">
                <div class="stat-label">Email Terkirim</div>
                <div class="stat-value">
                    <span class="badge-ok"><?= number_format($stats['email'], 0, ',', '.') ?></span>
                    <small style="margin-right:.4rem;">berhasil</small>
                    <a href="?filter=email_fail" class="stat-fail-link" title="Lihat penonton yang gagal kirim email">
                        <span class="badge-no"><?= number_format($emailFail, 0, ',', '.') ?></span> <small>gagal</small>
                    </a>
                </div>
            </div>
            <?php if (!$isTicketing): ?>
            <a href="users.php" class="stat-card stat-card-link stat-card-user">
                <div class="stat-user-emoji">👥</div>
                <div class="stat-label">Setting User</div>
            </a>
            <button type="button" class="stat-card stat-card-link stat-card-user stat-card-btn" onclick="openHelpContact()">
                <div class="stat-user-emoji">☎️</div>
                <div class="stat-label">Kontak Bantuan</div>
            </button>
            <button type="button" class="stat-card stat-card-link stat-card-user stat-card-btn" onclick="openDonation()">
                <div class="stat-user-emoji">🏦</div>
                <div class="stat-label">Rekening Sumbangan</div>
            </button>
            <?php endif; ?>
        </div>

        <?php if (!$isTicketing): ?>
        <!-- Modal kuota & buka/tutup penjualan -->
        <div id="quotaModal" class="modal-overlay" onclick="if(event.target===this)closeQuotaModal()">
            <div class="modal-box" style="max-width:420px;">
                <button class="modal-close" onclick="closeQuotaModal()">✕</button>
                <h3 class="m-title">Kuota &amp; Penjualan</h3>
                <div class="quota-modal-status <?= $isOpen ? '' : 'closed' ?>">
                    <span class="quota-dot"></span>
                    Registrasi <strong><?= $statusLabel ?></strong>
                </div>
                <div class="quota-numbers" style="margin:0.6rem 0 1.1rem;">
                    <span>Terjual <strong><?= number_format($sold, 0, ',', '.') ?></strong></span>
                    <span>Kuota <strong><?= $quota > 0 ? number_format($quota, 0, ',', '.') : '∞' ?></strong></span>
                    <span>Sisa <strong><?= $quota > 0 ? number_format($remaining, 0, ',', '.') : '∞' ?></strong></span>
                </div>
                <form method="POST" action="quota_save.php" class="quota-form" onsubmit="return confirm('Yakin mengganti kuota tiket menjadi ' + document.getElementById('quotaInput').value + '?');">
                    <label>Kuota</label>
                    <input type="number" id="quotaInput" name="quota" min="0" value="<?= $quota ?>" class="adm-input" style="width:95px;" disabled>
                    <button type="button" id="btnEditQuota" class="adm-btn-ghost" onclick="enableQuotaEdit()">Edit</button>
                    <button type="submit" id="btnSaveQuota" class="adm-btn-primary" style="display:none;">Simpan</button>
                </form>
                <form method="POST" action="sales_toggle.php" id="salesForm" style="margin-top:0.9rem;">
                    <input type="hidden" name="open" value="<?= $salesOpen ? '0' : '1' ?>">
                    <button type="button" style="width:100%;" class="<?= $salesOpen ? 'adm-btn-danger' : 'adm-btn-secondary' ?>"
                            onclick="confirmSales('<?= $salesOpen ? 'Tutup' : 'Buka' ?>')">
                        <?= $salesOpen ? '⏸ Tutup Penjualan' : '▶ Buka Penjualan' ?>
                    </button>
                </form>
                <p class="quota-hint" style="margin:0.9rem 0 0;">Kuota <strong>0</strong> = tanpa batas. Saat penjualan ditutup atau kuota penuh, tombol di halaman depan jadi "Coming Soon".</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="adm-toolbar">
            <form method="GET" class="adm-search">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama, email, no HP, atau kode tiket (mis. UGBQ001)..." class="adm-input">
                <?php if ($filter !== ''): ?><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
                <button type="submit" class="adm-btn-primary">Cari</button>
                <?php if ($q !== '' || $filter !== ''): ?><a href="index.php" class="adm-btn-ghost">Reset</a><?php endif; ?>
            </form>
            <a href="export.php<?= qs() ?>" class="adm-btn-secondary">⬇ Export Excel</a>
        </div>

        <?php if ($filter === 'email_fail'): ?>
        <div class="adm-filter-bar">
            Menampilkan <strong>penonton yang gagal kirim email</strong> (<?= number_format($total, 0, ',', '.') ?>).
        </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th class="col-tiket">Tiket</th>
                        <th>Tanggal</th>
                        <th>Nama</th>
                        <th>No. WhatsApp</th>
                        <th>Email</th>
                        <?php if (!$isTicketing): ?><th>Sumbangan</th><?php endif; ?>
                        <th>Arwah</th>
                        <th>Email</th>
                        <th class="col-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="<?= $isTicketing ? 8 : 9 ?>" class="adm-empty"><?= $q !== '' ? 'Tidak ada hasil untuk "' . htmlspecialchars($q) . '"' : 'Belum ada registrasi.' ?></td></tr>
                    <?php else: foreach ($rows as $r):
                        $id        = (int)$r['id'];
                        $codes     = deriveCodes($r['kode_tiket'], (int)$r['jumlah_tiket']);
                        $checked   = $checkedMap[$id] ?? [];
                        $cancelled = $cancelledMap[$id] ?? [];
                        $waUrl     = waLink($r['no_hp'], adminTicketUrl($r['kode_tiket']));
                        $retUrl    = 'index.php' . qs(['page' => $page]);
                    ?>
                        <tr>
                            <td class="col-tiket">
                                <div class="ticket-tree">
                                    <?php foreach ($codes as $idx => $c):
                                        $isIn   = in_array($c, $checked, true);
                                        $isCanc = in_array($c, $cancelled, true);
                                    ?>
                                        <div class="tt-item <?= $isCanc ? 'cancelled' : '' ?>">
                                            <span class="tt-n"><?= $idx + 1 ?></span>
                                            <code><?= htmlspecialchars($c) ?></code>
                                            <?php if ($isCanc): ?><span class="tt-cancel" title="Dibatalkan">batal</span>
                                            <?php elseif ($isIn): ?><span class="tt-check" title="Sudah check-in">✓</span><?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
                            <td class="adm-strong"><?= htmlspecialchars($r['nama']) ?></td>
                            <td><?= htmlspecialchars(phoneDisplay($r['no_hp'])) ?></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <?php if (!$isTicketing): ?><td><?= (float)$r['sumbangan_amount'] > 0 ? rp($r['sumbangan_amount']) : '—' ?></td><?php endif; ?>
                            <td style="text-align:center;"><?= $r['upload_arwah'] ? '🕊️' : '—' ?></td>
                            <td style="text-align:center;"><?= $r['email_sent'] ? '<span class="badge-ok">✓</span>' : '<span class="badge-no">✗</span>' ?></td>
                            <td class="col-aksi">
                                <div class="aksi-stack">
                                    <a href="edit.php?id=<?= $id ?>" class="adm-btn-sm adm-btn-detail" style="text-align:center;">✎ Edit</a>
                                    <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener" class="adm-btn-sm adm-btn-wa-sm">Resend link ke WA</a>
                                    <form method="POST" action="resend.php">
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <input type="hidden" name="return" value="<?= htmlspecialchars($retUrl) ?>">
                                        <button type="submit" class="adm-btn-sm adm-btn-mail">✉ Resend Email</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="adm-pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="<?= qs(['page' => $p]) ?>"
                   class="adm-page <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <p class="adm-foot">Menampilkan <?= count($rows) ?> dari <?= number_format($total, 0, ',', '.') ?> registrasi</p>

    <?php if (!$isTicketing): ?>
    <!-- Modal kontak bantuan (ditampilkan di halaman 404 publik) -->
    <div id="helpContactModal" class="modal-overlay" onclick="if(event.target===this)closeHelpContact()">
        <div class="modal-box" style="max-width:420px;">
            <button class="modal-close" onclick="closeHelpContact()">✕</button>
            <h3 class="m-title">Kontak Bantuan</h3>
            <p style="font-size:.85rem;color:#888;margin:-.5rem 0 .75rem;line-height:1.5;">
                Ditampilkan sebagai kontak bila pengunjung mengalami kendala tiket.
            </p>
            <label class="hc-label">Nama Kontak</label>
            <input type="text" id="hcName" class="hc-input" value="<?= htmlspecialchars($helpContact['name']) ?>">
            <label class="hc-label">Nomor WhatsApp (dipakai untuk tampilan &amp; link wa.me)</label>
            <div class="hc-wa-group">
                <span class="hc-prefix">62</span>
                <input type="text" id="hcWa" class="hc-input" placeholder="812xxxxxxxx"
                       value="<?= htmlspecialchars(preg_replace('/^62/', '', $helpContact['wa'])) ?>">
            </div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-top:1rem;">
                <button type="button" class="m-view-btn" onclick="saveHelpContact()">💾 Simpan</button>
                <span id="hcStatus" style="font-size:.8rem;font-weight:600;"></span>
            </div>
        </div>
    </div>

    <!-- Modal rekening sumbangan (ditampilkan di form registrasi) -->
    <div id="donationModal" class="modal-overlay" onclick="if(event.target===this)closeDonation()">
        <div class="modal-box" style="max-width:420px;">
            <button class="modal-close" onclick="closeDonation()">✕</button>
            <h3 class="m-title">Rekening Sumbangan</h3>
            <p style="font-size:.85rem;color:#888;margin:-.5rem 0 .75rem;line-height:1.5;">
                Ditampilkan di langkah Persembahan pada form registrasi tiket.
            </p>
            <label class="hc-label">Bank (singkat, tampil sebagai logo)</label>
            <input type="text" id="donBankShort" class="hc-input" value="<?= htmlspecialchars($donation['bank_short']) ?>">
            <label class="hc-label">Nama Bank</label>
            <input type="text" id="donBankName" class="hc-input" value="<?= htmlspecialchars($donation['bank_name']) ?>">
            <label class="hc-label">Nomor Rekening</label>
            <input type="text" id="donAccount" class="hc-input" value="<?= htmlspecialchars($donation['account']) ?>">
            <label class="hc-label">Atas Nama</label>
            <input type="text" id="donHolder" class="hc-input" value="<?= htmlspecialchars($donation['holder']) ?>">
            <div style="display:flex;align-items:center;gap:.6rem;margin-top:1rem;">
                <button type="button" class="m-view-btn" onclick="saveDonation()">💾 Simpan</button>
                <span id="donStatus" style="font-size:.8rem;font-weight:600;"></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal konfirmasi penjualan -->
    <div id="confirmModal" class="modal-overlay" onclick="if(event.target===this)closeConfirm()">
        <div class="modal-box" style="max-width:380px;text-align:center;">
            <h3 class="m-title" style="text-align:center;" id="confirmTitle">Konfirmasi</h3>
            <p id="confirmMsg" style="color:#555;margin-bottom:1.4rem;line-height:1.5;"></p>
            <div style="display:flex;gap:0.6rem;">
                <button type="button" class="adm-btn-ghost" style="flex:1;" onclick="closeConfirm()">Batal</button>
                <button type="button" class="adm-btn-primary" style="flex:1;" id="confirmYes">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>

    <script>
    function enableQuotaEdit() {
        document.getElementById('quotaInput').disabled = false;
        document.getElementById('quotaInput').focus();
        document.getElementById('btnEditQuota').style.display = 'none';
        document.getElementById('btnSaveQuota').style.display = 'inline-flex';
    }

    /* ---------- Modal konfirmasi ---------- */
    function openConfirm(title, msg, onYes) {
        document.getElementById('confirmTitle').textContent = title;
        document.getElementById('confirmMsg').textContent   = msg;
        document.getElementById('confirmYes').onclick = function () { onYes(); };
        document.getElementById('confirmModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeConfirm() {
        document.getElementById('confirmModal').classList.remove('open');
        document.body.style.overflow = '';
    }
    function confirmSales(label) {
        openConfirm(label + ' Penjualan Tiket', label + ' penjualan tiket sekarang?', function () {
            document.getElementById('salesForm').submit();
        });
    }

    /* ---------- Modal kuota & penjualan ---------- */
    function openQuotaModal()  { document.getElementById('quotaModal').classList.add('open'); }
    function closeQuotaModal() { document.getElementById('quotaModal').classList.remove('open'); }

    /* ---------- Rekening sumbangan ---------- */
    function openDonation()  { document.getElementById('donationModal').classList.add('open'); }
    function closeDonation() {
        document.getElementById('donationModal').classList.remove('open');
        var st = document.getElementById('donStatus'); if (st) st.textContent = '';
    }
    function saveDonation() {
        var st = document.getElementById('donStatus');
        var fd = new FormData();
        fd.append('bank_short', document.getElementById('donBankShort').value.trim());
        fd.append('bank_name',  document.getElementById('donBankName').value.trim());
        fd.append('account',    document.getElementById('donAccount').value.trim());
        fd.append('holder',     document.getElementById('donHolder').value.trim());
        st.style.color = '#666'; st.textContent = 'Menyimpan...';
        fetch('update_donation.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) { st.style.color = '#c0392b'; st.textContent = res.error || 'Gagal menyimpan'; return; }
                document.getElementById('donAccount').value = res.account;
                st.style.color = '#1a7a40'; st.textContent = '✓ Tersimpan';
                setTimeout(closeDonation, 900);
            })
            .catch(function () { st.style.color = '#c0392b'; st.textContent = 'Koneksi gagal'; });
    }

    /* ---------- Kontak bantuan 404 ---------- */
    function openHelpContact()  { document.getElementById('helpContactModal').classList.add('open'); }
    function closeHelpContact() {
        document.getElementById('helpContactModal').classList.remove('open');
        var st = document.getElementById('hcStatus'); if (st) st.textContent = '';
    }
    function saveHelpContact() {
        var st = document.getElementById('hcStatus');
        // Prefix 62 fix di kiri field — user hanya mengisi sisa nomornya
        var waLocal = document.getElementById('hcWa').value
            .replace(/\D/g, '').replace(/^0+/, '').replace(/^62/, '');
        var fd = new FormData();
        fd.append('name', document.getElementById('hcName').value.trim());
        fd.append('wa',   waLocal ? '62' + waLocal : '');
        st.style.color = '#666'; st.textContent = 'Menyimpan...';
        fetch('update_help_contact.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) { st.style.color = '#c0392b'; st.textContent = res.error || 'Gagal menyimpan'; return; }
                document.getElementById('hcWa').value = res.wa.replace(/^62/, '');
                st.style.color = '#1a7a40'; st.textContent = '✓ Tersimpan';
                setTimeout(closeHelpContact, 900);
            })
            .catch(function () { st.style.color = '#c0392b'; st.textContent = 'Koneksi gagal'; });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('confirmModal').classList.contains('open')) closeConfirm();
    });
    </script>

<?php require __DIR__ . '/partials/footer.php'; ?>
