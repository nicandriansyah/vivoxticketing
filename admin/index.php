<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
require_once __DIR__ . '/helpers.php';

$dbReady = (bool)$pdo;
if ($dbReady) { try { ensureTicketTables($pdo); } catch (Exception $e) {} }

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
        $codes[] = 'FOAS13-' . $batch . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
    }
    return $codes;
}

// Map check-in & pembatalan per registrasi
$regIds       = array_map(fn($r) => (int)$r['id'], $rows);
$checkedMap   = $dbReady ? getCheckedMap($pdo, $regIds) : [];
$cancelledMap = $dbReady ? getCancelledMap($pdo, $regIds) : [];


// Data untuk modal detail
$regJson = [];
foreach ($rows as $r) {
    $id        = (int)$r['id'];
    $codes     = deriveCodes($r['kode_tiket'], (int)$r['jumlah_tiket']);
    $checked   = $checkedMap[$id] ?? [];
    $cancelled = $cancelledMap[$id] ?? [];
    $regJson[$id] = [
        'nama'         => $r['nama'],
        'no_hp'        => phoneDisplay($r['no_hp']),
        'email'        => $r['email'],
        'url'          => adminTicketUrl($r['kode_tiket']),
        'jumlah'       => (int)$r['jumlah_tiket'],
        'aktif'        => (int)$r['jumlah_tiket'] - count($cancelled),
        'sumbangan'    => (float)$r['sumbangan_amount'] > 0 ? rp($r['sumbangan_amount']) : '—',
        'email_sent'   => (int)$r['email_sent'],
        'created'      => date('d M Y H:i', strtotime($r['created_at'])),
        'codes'        => $codes,
        'checked'      => array_values($checked),
        'cancelled'    => array_values($cancelled),
        'upload_arwah' => (int)$r['upload_arwah'],
        'foto'         => $r['foto_arwah'] ? adminUploadUrl($r['foto_arwah']) : '',
        'nama_arwah'   => $r['nama_arwah'] ?? '',
        'tahun_lahir'  => $r['tahun_lahir'] ?? '',
        'tahun_wafat'  => $r['tahun_wafat'] ?? '',
        'hubungan'     => hubunganLabel($r['hubungan_arwah']),
    ];
}

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
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
            <div class="stat-card">
                <div class="stat-label">Total Registrasi</div>
                <div class="stat-value"><?= number_format($stats['reg'], 0, ',', '.') ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Tiket</div>
                <div class="stat-value"><?= number_format($stats['tiket'], 0, ',', '.') ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Sudah Check-in</div>
                <div class="stat-value"><?= number_format($stats['checkin'], 0, ',', '.') ?> <small>/ <?= number_format($stats['tiket'], 0, ',', '.') ?></small></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Sumbangan</div>
                <div class="stat-value"><?= rp($stats['sumbangan']) ?></div>
            </div>
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
            <a href="users.php" class="stat-card stat-card-link stat-card-user">
                <div class="stat-user-emoji">👥</div>
                <div class="stat-label">Setting User</div>
            </a>
        </div>

        <!-- Quota card -->
        <?php
            $statusLabel = $isOpen ? 'DIBUKA'
                : (!$salesOpen ? 'DITUTUP (manual)' : 'DITUTUP (Penuh)');
        ?>
        <div class="quota-card <?= $isOpen ? '' : 'closed' ?>">
            <div class="quota-info">
                <div class="quota-status">
                    <span class="quota-dot"></span>
                    Registrasi <strong><?= $statusLabel ?></strong>
                </div>
                <div class="quota-numbers">
                    <span>Terjual <strong><?= number_format($sold, 0, ',', '.') ?></strong></span>
                    <span>Kuota <strong><?= $quota > 0 ? number_format($quota, 0, ',', '.') : '∞' ?></strong></span>
                    <span>Sisa <strong><?= $quota > 0 ? number_format($remaining, 0, ',', '.') : '∞' ?></strong></span>
                </div>
            </div>
            <div class="quota-actions">
                <form method="POST" action="quota_save.php" class="quota-form" onsubmit="return confirm('Yakin mengganti kuota tiket menjadi ' + document.getElementById('quotaInput').value + '?');">
                    <label>Kuota</label>
                    <input type="number" id="quotaInput" name="quota" min="0" value="<?= $quota ?>" class="adm-input" style="width:95px;" disabled>
                    <button type="button" id="btnEditQuota" class="adm-btn-ghost" onclick="enableQuotaEdit()">Edit</button>
                    <button type="submit" id="btnSaveQuota" class="adm-btn-primary" style="display:none;">Simpan</button>
                </form>
                <form method="POST" action="sales_toggle.php" id="salesForm">
                    <input type="hidden" name="open" value="<?= $salesOpen ? '0' : '1' ?>">
                    <button type="button" class="<?= $salesOpen ? 'adm-btn-danger' : 'adm-btn-secondary' ?>"
                            onclick="confirmSales('<?= $salesOpen ? 'Tutup' : 'Buka' ?>')">
                        <?= $salesOpen ? '⏸ Tutup Penjualan' : '▶ Buka Penjualan' ?>
                    </button>
                </form>
            </div>
        </div>
        <p class="quota-hint">Kuota <strong>0</strong> = tanpa batas. Saat penjualan ditutup atau kuota penuh, tombol di halaman depan jadi "Coming Soon".</p>

        <!-- Toolbar -->
        <div class="adm-toolbar">
            <form method="GET" class="adm-search">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama, email, no HP, atau kode tiket (mis. UGBQ001)..." class="adm-input">
                <?php if ($filter !== ''): ?><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
                <button type="submit" class="adm-btn-primary">Cari</button>
                <?php if ($q !== '' || $filter !== ''): ?><a href="index.php" class="adm-btn-ghost">Reset</a><?php endif; ?>
            </form>
            <a href="export.php<?= qs() ?>" class="adm-btn-secondary">⬇ Export CSV</a>
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
                        <th>Sumbangan</th>
                        <th>Arwah</th>
                        <th>Email</th>
                        <th class="col-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="9" class="adm-empty"><?= $q !== '' ? 'Tidak ada hasil untuk "' . htmlspecialchars($q) . '"' : 'Belum ada registrasi.' ?></td></tr>
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
                            <td><?= (float)$r['sumbangan_amount'] > 0 ? rp($r['sumbangan_amount']) : '—' ?></td>
                            <td style="text-align:center;"><?= $r['upload_arwah'] ? '🕊️' : '—' ?></td>
                            <td style="text-align:center;"><?= $r['email_sent'] ? '<span class="badge-ok">✓</span>' : '<span class="badge-no">✗</span>' ?></td>
                            <td class="col-aksi">
                                <div class="aksi-stack">
                                    <button type="button" class="adm-btn-sm adm-btn-detail" onclick="openDetail(<?= $id ?>)">Detail</button>
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

    <!-- Modal Detail -->
    <div id="detailModal" class="modal-overlay" onclick="if(event.target===this)closeDetail()">
        <div class="modal-box">
            <button class="modal-close" onclick="closeDetail()">✕</button>
            <div id="modalContent"></div>
        </div>
    </div>

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

    <!-- Lightbox gambar tiket -->
    <div id="imgLightbox" class="img-lightbox" onclick="if(event.target===this)closeLightbox()">
        <div class="img-lightbox-inner">
            <button class="modal-close" onclick="closeLightbox()">✕</button>
            <div id="lightboxBody"></div>
            <button type="button" class="adm-btn-wa" style="margin-top:0.9rem;" onclick="shareTicketWA(lbCode)">↗ Share ke WhatsApp</button>
        </div>
    </div>

    <script>
    var REG = <?= json_encode($regJson, JSON_UNESCAPED_UNICODE) ?>;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

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

    function openDetail(id) {
        var d = REG[id];
        if (!d) return;
        var checkedSet = {}, cancelSet = {};
        (d.checked   || []).forEach(function(c){ checkedSet[c] = true; });
        (d.cancelled || []).forEach(function(c){ cancelSet[c]  = true; });

        var ticketsHtml = d.codes.map(function(c, i) {
            var status, cancelBtn = '';
            if (cancelSet[c]) {
                status = '<span class="badge-no">Dibatalkan</span>';
            } else if (checkedSet[c]) {
                status = '<span class="badge-ok">✓ Check-in</span>';
            } else {
                status = '<span class="m-badge-active">Aktif</span>';
                cancelBtn = '<button type="button" class="m-cancel-btn" onclick="cancelTicket(\'' + esc(c) + '\')">Batalkan</button>';
            }
            var actions =
                '<div class="m-ticket-actions">' +
                    '<button type="button" class="m-view-btn" onclick="viewTicket(\'' + esc(c) + '\')">👁 Lihat Tiket</button>' +
                    '<button type="button" class="m-wa-btn" onclick="shareTicketWA(\'' + esc(c) + '\')">↗ Share WA</button>' +
                '</div>';
            return '<div class="m-ticket' + (cancelSet[c] ? ' cancelled' : '') + '">' +
                       '<div class="m-ticket-head"><span class="tt-n">' + (i+1) + '</span><code>' + esc(c) + '</code></div>' +
                       '<div class="m-ticket-foot">' + status + cancelBtn + '</div>' +
                       actions +
                   '</div>';
        }).join('');

        var hasArwah = !!d.upload_arwah;
        var arwahHtml = '';
        if (hasArwah) {
            arwahHtml =
                '<h4 class="m-sub">🕊️ Data Arwah</h4>' +
                (d.foto ? '<div style="text-align:center;margin-bottom:.75rem;"><img src="' + esc(d.foto) + '" style="max-width:120px;max-height:120px;border-radius:10px;object-fit:cover;"></div>' : '') +
                '<div class="m-section">' +
                row('Nama Arwah', esc(d.nama_arwah) || '—') +
                row('Tahun Lahir', esc(d.tahun_lahir) || '—') +
                row('Tahun Wafat', esc(d.tahun_wafat) || '—') +
                row('Hubungan', esc(d.hubungan) || '—') +
                '</div>';
        }

        var linkRow =
            '<div class="m-linkrow">' +
                '<input class="m-link-input" value="' + esc(d.url) + '" readonly onclick="this.select()">' +
                '<button type="button" class="m-copy-btn" onclick="copyUrl(this)" title="Salin link tiket">📋</button>' +
            '</div>';

        var ticketsCol =
            '<div class="m-col-left">' +
                '<h4 class="m-sub">Tiket Terbentuk (' + d.aktif + ' aktif / ' + d.jumlah + ')</h4>' +
                '<div class="m-tickets">' + ticketsHtml + '</div>' +
            '</div>';
        var arwahCol = hasArwah ? '<div class="m-col-right">' + arwahHtml + '</div>' : '';

        document.getElementById('modalContent').innerHTML =
            '<h3 class="m-title">' + esc(d.nama) + '</h3>' +
            '<p class="m-contact">' + esc(d.no_hp) + ' &middot; ' + esc(d.email) + '</p>' +
            linkRow +
            '<div class="m-cols' + (hasArwah ? ' two' : '') + '">' + ticketsCol + arwahCol + '</div>';

        // Lebarkan modal jika ada kolom arwah
        document.querySelector('#detailModal .modal-box').classList.toggle('modal-box-wide', hasArwah);

        document.getElementById('detailModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function copyUrl(btn) {
        var input = btn.parentElement.querySelector('.m-link-input');
        var done = function () {
            var o = btn.textContent;
            btn.textContent = '✓';
            btn.classList.add('copied');
            setTimeout(function () { btn.textContent = o; btn.classList.remove('copied'); }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(done).catch(function () { input.select(); document.execCommand('copy'); done(); });
        } else {
            input.select(); document.execCommand('copy'); done();
        }
    }

    function cancelTicket(code) {
        if (!confirm('Batalkan tiket ' + code + '?\nTiket tidak dihapus, hanya mengurangi total tiket aktif.')) return;
        var fd = new FormData();
        fd.append('code', code);
        fetch('cancel_ticket.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.ok) { location.reload(); }
                else { alert(res.error || 'Gagal membatalkan tiket.'); }
            })
            .catch(function(){ alert('Koneksi gagal.'); });
    }

    function row(label, value) {
        return '<div class="m-row"><span>' + label + '</span><strong>' + value + '</strong></div>';
    }

    /* ---------- Lihat & share gambar tiket ---------- */
    var lbCode = null;

    function viewTicket(code) {
        lbCode = code;
        document.getElementById('lightboxBody').innerHTML =
            '<p class="lb-loading">Memuat gambar tiket…</p>' +
            '<img class="lb-img" src="ticket_image.php?code=' + encodeURIComponent(code) + '&t=' + Date.now() + '" ' +
                 'onload="this.previousElementSibling.style.display=\'none\';" ' +
                 'onerror="this.style.display=\'none\';this.previousElementSibling.innerHTML=\'Gambar tiket belum tersedia.<br><small>Tiket tersimpan otomatis saat peserta membuka link tiketnya.</small>\';">';
        document.getElementById('imgLightbox').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        document.getElementById('imgLightbox').classList.remove('open');
        if (!document.getElementById('detailModal').classList.contains('open')) {
            document.body.style.overflow = '';
        }
    }

    async function shareTicketWA(code) {
        if (!code) return;
        try {
            var resp = await fetch('ticket_image.php?code=' + encodeURIComponent(code));
            if (!resp.ok) throw new Error('no-image');
            var blob = await resp.blob();
            var file = new File([blob], code + '.jpg', { type: 'image/jpeg' });

            if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({ files: [file], title: 'Tiket FOAS 13' });
            } else {
                // Fallback (desktop): buka gambar di tab baru untuk dibagikan manual
                var url = URL.createObjectURL(blob);
                window.open(url, '_blank');
                setTimeout(function(){ URL.revokeObjectURL(url); }, 4000);
            }
        } catch (e) {
            if (e && e.name === 'AbortError') return;
            alert('Gambar tiket belum tersedia untuk dibagikan.');
        }
    }

    function closeDetail() {
        document.getElementById('detailModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('confirmModal').classList.contains('open')) closeConfirm();
        else if (document.getElementById('imgLightbox').classList.contains('open')) closeLightbox();
        else closeDetail();
    });
    </script>

<?php require __DIR__ . '/partials/footer.php'; ?>
