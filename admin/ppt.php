<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
require_once __DIR__ . '/helpers.php';

$dbReady = (bool)$pdo;
$cat  = $_GET['cat'] ?? '';
$opts = hubunganOptions();
$rows = [];
$slides = [];
$availCats = [];   // kategori yang punya data
$per  = 2;   // foto per slide (1 atau 2)

if ($dbReady) {
    try {
        try { ensureTicketTables($pdo); } catch (Exception $e) {}

        // Foto per slide dikunci ke 2 (kiri→kanan, ganjil terakhir di tengah).
        $per = 2;

        // Kategori yang ada datanya (untuk default & penanda kosong di tab)
        try {
            $availCats = $pdo->query("SELECT DISTINCT hubungan_arwah FROM arwah
                                      WHERE nama_arwah <> '' AND hubungan_arwah IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}

        // Tidak ada 'Semua Kategori': selalu satu kategori (tiap kategori dipisah).
        // Default = kategori pertama yang ada datanya; jika tak ada, kategori pertama.
        if (!array_key_exists($cat, $opts)) {
            $cat = '';
            foreach ($opts as $k => $v) { if (in_array($k, $availCats, true)) { $cat = $k; break; } }
            if ($cat === '') $cat = array_key_first($opts);
        }

        // Ambil arwah kategori terpilih. ORDER BY id ASC → arwah terbaru selalu di
        // akhir (urutan stabil, isi kiri→kanan, ganjil terakhir di tengah).
        $stmt = $pdo->prepare("SELECT a.id, a.nama_arwah, a.tahun_lahir, a.tahun_wafat, a.foto_arwah, a.hubungan_arwah, a.slide_layout
                               FROM arwah a
                               JOIN registrations r ON r.id = a.registration_id
                               WHERE a.nama_arwah IS NOT NULL AND a.nama_arwah <> '' AND a.hubungan_arwah = ?
                               ORDER BY a.id ASC");
        $stmt->execute([$cat]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $slides = array_chunk($rows, max(1, $per));   // kelompokkan jadi slide
    } catch (Exception $e) { $dbReady = false; $dbErr = $e->getMessage(); }
}

function yearsText($l, $w): string {
    $l = $l ? (string)$l : '';
    $w = $w ? (string)$w : '';
    if ($l && $w) return "$l – $w";
    return $l ?: $w;
}

/**
 * Posisi default sebuah unit arwah dalam slide, sesuai mode (per) & indeks unit.
 * Kanvas 640x360.
 */
function unitDefaults(int $per, int $idx): array {
    if ($per >= 2) {
        if ($idx % 2 === 0) { // kolom kiri
            return ['ph'=>['x'=>80,'y'=>34,'w'=>160,'h'=>160],
                    'na'=>['x'=>16,'y'=>232,'w'=>288],
                    'yr'=>['x'=>16,'y'=>286,'w'=>288],
                    'naFs'=>24, 'yrFs'=>17];
        }
        return ['ph'=>['x'=>400,'y'=>34,'w'=>160,'h'=>160],   // kolom kanan
                'na'=>['x'=>336,'y'=>232,'w'=>288],
                'yr'=>['x'=>336,'y'=>286,'w'=>288],
                'naFs'=>24, 'yrFs'=>17];
    }
    return ['ph'=>['x'=>240,'y'=>25,'w'=>160,'h'=>160],       // 1 per slide (tengah)
            'na'=>['x'=>36,'y'=>213,'w'=>568],
            'yr'=>['x'=>36,'y'=>270,'w'=>568],
            'naFs'=>30, 'yrFs'=>21];
}

$pageTitle  = 'PPT Generator';
$activeMenu = 'ppt';
$mainClass  = 'adm-main-full adm-main-ppt';
require __DIR__ . '/partials/header.php';
?>

        <?php if (!$dbReady): ?>
            <div class="adm-alert">Koneksi database gagal<?= isset($dbErr) ? ': ' . htmlspecialchars($dbErr) : '' ?>.</div>
        <?php else: ?>

        <!-- Card: kategori + jumlah/slide + judul + download -->
        <div class="ppt-card ppt-toolbar">
            <div class="ppt-cattabs">
                <span class="ppt-cattabs-label">Kategori</span>
                <?php foreach ($opts as $k => $label):
                    $isEmpty = !in_array($k, $availCats, true); ?>
                    <a class="ppt-cattab<?= $cat === $k ? ' active' : '' ?><?= $isEmpty ? ' empty' : '' ?>"
                       href="?cat=<?= urlencode($k) ?>"><?= htmlspecialchars($label) ?><?= $isEmpty ? ' (kosong)' : '' ?></a>
                <?php endforeach; ?>
            </div>
            <button type="button" class="adm-btn-secondary" id="btnPptx" onclick="downloadPptx()" <?= $rows ? '' : 'disabled' ?>>⬇ Download PPTX (<?= count($slides) ?> slide)</button>
        </div>

        <?php if (!$rows): ?>
            <div class="adm-alert">Belum ada data arwah<?= $cat !== '' ? ' untuk kategori ini' : '' ?>.</div>
        <?php else: ?>

        <!-- Card: editor (ribbon + slide list + kanvas) -->
        <div class="ppt-card ppt-editorcard">
        <div class="ppt-ribbon">
            <div class="rib-group">
                <span class="rib-label">Teks</span>
                <div class="rib-row">
                    <button type="button" class="rib-btn" data-act="fs-" title="Perkecil">A−</button>
                    <button type="button" class="rib-btn" data-act="fs+" title="Perbesar">A+</button>
                    <button type="button" class="rib-btn" data-act="bold" title="Tebal"><b>B</b></button>
                    <label class="rib-color" title="Warna teks"><input type="color" data-act="color"><span>A</span></label>
                    <button type="button" class="rib-btn" data-act="al-left"   title="Rata kiri">⯇</button>
                    <button type="button" class="rib-btn" data-act="al-center" title="Rata tengah">≡</button>
                    <button type="button" class="rib-btn" data-act="al-right"  title="Rata kanan">⯈</button>
                </div>
            </div>
            <div class="rib-group">
                <span class="rib-label">Slide</span>
                <div class="rib-row">
                    <label class="rib-color" title="Warna latar slide"><input type="color" id="bgColor" value="#1a0e00"><span>▦</span></label>
                    <button type="button" class="rib-btn" id="btnReset" title="Kembalikan posisi default slide ini">↺ Reset</button>
                </div>
            </div>
            <div class="rib-hint">Klik item (foto/teks) untuk memilih, lalu format. Geser pakai ✥, resize foto pakai titik putih.</div>
        </div>

        <!-- Editor: sidebar + kanvas -->
        <div class="ppt-editor">
            <div class="ppt-leftcol">
                <aside class="ppt-slidelist">
                    <?php foreach ($slides as $si => $chunk):
                        $names = [];
                        foreach ($chunk as $r) {
                            $L = json_decode($r['slide_layout'] ?? '', true);
                            $names[] = (is_array($L) && isset($L['nama']) && $L['nama'] !== '') ? $L['nama'] : $r['nama_arwah'];
                        }
                        $firstFoto = $chunk[0]['foto_arwah'] ? adminUploadUrl($chunk[0]['foto_arwah']) : '';
                    ?>
                    <div class="ppt-thumb <?= $si === 0 ? 'active' : '' ?>" data-idx="<?= $si ?>" onclick="selectSlide(<?= $si ?>)">
                        <span class="ppt-thumb-no"><?= $si + 1 ?></span>
                        <?php if ($firstFoto): ?><img class="ppt-thumb-foto" src="<?= htmlspecialchars($firstFoto) ?>" alt=""><?php else: ?><span class="ppt-thumb-foto ppt-thumb-noimg">—</span><?php endif; ?>
                        <span class="ppt-thumb-nama" id="thumbnama-<?= $si ?>"><?= htmlspecialchars(implode(' & ', $names)) ?></span>
                    </div>
                    <?php endforeach; ?>
                </aside>
                <button type="button" class="adm-btn-primary ppt-save-btn" id="btnSave" onclick="saveLayout()">💾 Simpan</button>
                <span class="ppt-save-status" id="saveStatus"></span>
            </div>

            <div class="ppt-canvas-area">
                <?php foreach ($slides as $si => $chunk):
                    $L0 = json_decode($chunk[0]['slide_layout'] ?? '', true);
                    $bg = (is_array($L0) && !empty($L0['bg'])) ? $L0['bg'] : '#1a0e00';
                    $unitCount = count($chunk);
                ?>
                <div class="ppt-slide <?= $si === 0 ? 'active' : '' ?>" data-idx="<?= $si ?>" data-defbg="#1a0e00" style="background:<?= htmlspecialchars($bg) ?>;">
                    <?php foreach ($chunk as $j => $r):
                        $foto = $r['foto_arwah'] ? adminUploadUrl($r['foto_arwah']) : '';
                        $L    = json_decode($r['slide_layout'] ?? '', true);
                        if (!is_array($L)) $L = [];
                        // Unit tunggal di slide → pakai layout tengah (1-per) agar rapi
                        $def   = ($unitCount === 1) ? unitDefaults(1, 0) : unitDefaults($per, $j);
                        $namaT = (isset($L['nama']) && $L['nama'] !== '') ? $L['nama'] : $r['nama_arwah'];
                        $yrT   = isset($L['years']) ? $L['years'] : yearsText($r['tahun_lahir'], $r['tahun_wafat']);
                        $ph = $L['ph'] ?? $def['ph'];
                        $na = $L['na'] ?? $def['na'];
                        $yp = $L['yr'] ?? $def['yr'];
                        $f  = fn($a,$k,$d) => isset($a[$k]) && is_numeric($a[$k]) ? (float)$a[$k] : $d;
                        $sv = fn($a,$k,$d) => isset($a[$k]) && $a[$k] !== '' ? $a[$k] : $d;
                        $naFs = $f($na,'fs',$def['naFs']); $naColor = $sv($na,'color','#ffffff'); $naAlign = $sv($na,'align','center'); $naBold = isset($na['bold']) ? ($na['bold'] ? 700 : 400) : 700;
                        $yrFs = $f($yp,'fs',$def['yrFs']); $yrColor = $sv($yp,'color','#c9a84c'); $yrAlign = $sv($yp,'align','center'); $yrBold = isset($yp['bold']) ? ($yp['bold'] ? 700 : 400) : 400;
                        $defPh = "{$def['ph']['x']},{$def['ph']['y']},{$def['ph']['w']},{$def['ph']['h']}";
                        $defNa = "{$def['na']['x']},{$def['na']['y']},{$def['na']['w']}";
                        $defYr = "{$def['yr']['x']},{$def['yr']['y']},{$def['yr']['w']}";
                    ?>
                    <div class="ppt-unit" data-id="<?= (int)$r['id'] ?>"
                         data-def-ph="<?= $defPh ?>" data-def-na="<?= $defNa ?>" data-def-yr="<?= $defYr ?>"
                         data-def-nafs="<?= $def['naFs'] ?>" data-def-yrfs="<?= $def['yrFs'] ?>">
                        <div class="ppt-item ppt-photo" data-foto="<?= htmlspecialchars($foto) ?>" style="left:<?= $f($ph,'x',$def['ph']['x']) ?>px;top:<?= $f($ph,'y',$def['ph']['y']) ?>px;width:<?= $f($ph,'w',$def['ph']['w']) ?>px;height:<?= $f($ph,'h',$def['ph']['h']) ?>px;">
                            <?php if ($foto): ?>
                                <img src="<?= htmlspecialchars($foto) ?>" alt="" draggable="false">
                            <?php else: ?>
                                <div class="ppt-foto-empty">Tanpa Foto</div>
                            <?php endif; ?>
                            <span class="ppt-move" title="Geser">✥</span>
                            <span class="ppt-resize" title="Ubah ukuran"></span>
                        </div>

                        <div class="ppt-item ppt-tbox ppt-t-nama" style="left:<?= $f($na,'x',$def['na']['x']) ?>px;top:<?= $f($na,'y',$def['na']['y']) ?>px;width:<?= $f($na,'w',$def['na']['w']) ?>px;">
                            <span class="ppt-move" title="Geser">✥</span>
                            <span class="ppt-edit ppt-nama" contenteditable="true" spellcheck="false"
                                  style="font-size:<?= $naFs ?>px;font-weight:<?= $naBold ?>;color:<?= htmlspecialchars($naColor) ?>;text-align:<?= htmlspecialchars($naAlign) ?>;"><?= htmlspecialchars($namaT) ?></span>
                        </div>

                        <div class="ppt-item ppt-tbox ppt-t-years" style="left:<?= $f($yp,'x',$def['yr']['x']) ?>px;top:<?= $f($yp,'y',$def['yr']['y']) ?>px;width:<?= $f($yp,'w',$def['yr']['w']) ?>px;">
                            <span class="ppt-move" title="Geser">✥</span>
                            <span class="ppt-edit ppt-years" contenteditable="true" spellcheck="false"
                                  style="font-size:<?= $yrFs ?>px;font-weight:<?= $yrBold ?>;color:<?= htmlspecialchars($yrColor) ?>;text-align:<?= htmlspecialchars($yrAlign) ?>;"><?= htmlspecialchars($yrT) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        </div><!-- /ppt-editorcard -->

        <?php endif; endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/pptxgenjs@3.12.0/dist/pptxgen.bundle.js"></script>
    <script>
    function clamp(v, min, max) { return Math.max(min, Math.min(v, max)); }

    /* ---------- Skala slide responsif ---------- */
    var SLIDE_W = 640, SLIDE_H = 360, slideScale = 1;
    function layoutSlides() {
        var area = document.querySelector('.ppt-canvas-area');
        if (!area) return;
        if (window.innerWidth <= 820) {
            // Layout kolom (tablet kecil): skala ikut lebar, tinggi area menyesuaikan
            slideScale = area.clientWidth / SLIDE_W;
            area.style.height = (SLIDE_H * slideScale) + 'px';
            document.querySelectorAll('.ppt-slide').forEach(function (s) { s.style.transform = 'scale(' + slideScale + ')'; });
            return;
        }
        // Desktop fullscreen: fit lebar & tinggi area (tanpa scroll), slide di tengah
        area.style.height = '';
        var aw = area.clientWidth, ah = area.clientHeight;
        slideScale = Math.min(aw / SLIDE_W, ah / SLIDE_H);
        var ox = (aw - SLIDE_W * slideScale) / 2, oy = (ah - SLIDE_H * slideScale) / 2;
        document.querySelectorAll('.ppt-slide').forEach(function (s) {
            s.style.transform = 'translate(' + ox + 'px,' + oy + 'px) scale(' + slideScale + ')';
        });
    }
    window.addEventListener('resize', layoutSlides);

    /* ---------- Drag & resize ---------- */
    function makeDraggable(item, handle, slide) {
        handle.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            setSelected(item);
            var sx = e.clientX, sy = e.clientY, ol = item.offsetLeft, ot = item.offsetTop;
            function move(ev) {
                item.style.left = clamp(ol + (ev.clientX - sx) / slideScale, 0, slide.clientWidth  - item.offsetWidth)  + 'px';
                item.style.top  = clamp(ot + (ev.clientY - sy) / slideScale, 0, slide.clientHeight - item.offsetHeight) + 'px';
            }
            function up() { document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', up); }
            document.addEventListener('pointermove', move); document.addEventListener('pointerup', up);
        });
    }
    function makeResizable(item, handle, slide) {
        handle.addEventListener('pointerdown', function (e) {
            e.preventDefault(); e.stopPropagation();
            setSelected(item);
            var sx = e.clientX, sy = e.clientY, ow = item.offsetWidth, oh = item.offsetHeight;
            function move(ev) {
                item.style.width  = clamp(ow + (ev.clientX - sx) / slideScale, 30, slide.clientWidth  - item.offsetLeft) + 'px';
                item.style.height = clamp(oh + (ev.clientY - sy) / slideScale, 30, slide.clientHeight - item.offsetTop)  + 'px';
            }
            function up() { document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', up); }
            document.addEventListener('pointermove', move); document.addEventListener('pointerup', up);
        });
    }

    document.querySelectorAll('.ppt-slide').forEach(function (slide) {
        slide.querySelectorAll('.ppt-item').forEach(function (item) {
            var mv = item.querySelector('.ppt-move');
            var rz = item.querySelector('.ppt-resize');
            if (mv) makeDraggable(item, mv, slide);
            if (rz) makeResizable(item, rz, slide);
            item.addEventListener('pointerdown', function () { setSelected(item); });
        });
    });

    /* ---------- Selection & ribbon ---------- */
    var selected = null;
    function setSelected(item) {
        document.querySelectorAll('.ppt-item.selected').forEach(function (e) { e.classList.remove('selected'); });
        selected = item;
        if (item) item.classList.add('selected');
        updateRibbon();
    }
    function selectedEdit() {
        if (!selected) return null;
        return selected.querySelector('.ppt-edit'); // null jika foto
    }
    function updateRibbon() {
        var ed = selectedEdit();
        document.querySelectorAll('.ppt-ribbon [data-act]').forEach(function (b) {
            if (b.getAttribute('data-act') !== null) b.disabled = !ed;
        });
        // sync warna slide aktif
        var slide = document.querySelector('.ppt-slide.active');
        if (slide) {
            var bg = rgbToHex(getComputedStyle(slide).backgroundColor);
            if (bg) document.getElementById('bgColor').value = bg;
        }
    }

    document.querySelectorAll('.ppt-ribbon [data-act]').forEach(function (btn) {
        var act = btn.getAttribute('data-act');
        var ev  = (btn.tagName === 'INPUT') ? 'input' : 'click';
        btn.addEventListener(ev, function () {
            var ed = selectedEdit();
            if (!ed) return;
            if (act === 'fs+' || act === 'fs-') {
                var cur = parseFloat(getComputedStyle(ed).fontSize) || 20;
                ed.style.fontSize = clamp(cur + (act === 'fs+' ? 2 : -2), 8, 120) + 'px';
            } else if (act === 'bold') {
                var b = (getComputedStyle(ed).fontWeight | 0) >= 600;
                ed.style.fontWeight = b ? '400' : '700';
            } else if (act === 'color') {
                ed.style.color = btn.value;
            } else if (act === 'al-left')   { ed.style.textAlign = 'left'; }
            else if (act === 'al-center') { ed.style.textAlign = 'center'; }
            else if (act === 'al-right')  { ed.style.textAlign = 'right'; }
        });
    });

    document.getElementById('bgColor').addEventListener('input', function () {
        var slide = document.querySelector('.ppt-slide.active');
        if (slide) slide.style.background = this.value;
    });

    // Reset semua unit di slide aktif ke posisi default masing-masing
    document.getElementById('btnReset').addEventListener('click', function () {
        var slide = document.querySelector('.ppt-slide.active');
        if (!slide) return;
        slide.style.background = slide.dataset.defbg || '#1a0e00';
        slide.querySelectorAll('.ppt-unit').forEach(function (unit) {
            var ph = (unit.dataset.defPh || '240,25,160,160').split(',');
            var na = (unit.dataset.defNa || '36,213,568').split(',');
            var yr = (unit.dataset.defYr || '36,270,568').split(',');
            unit.querySelector('.ppt-photo').style.cssText   = 'left:'+ph[0]+'px;top:'+ph[1]+'px;width:'+ph[2]+'px;height:'+ph[3]+'px;';
            var nB = unit.querySelector('.ppt-t-nama');  nB.style.cssText = 'left:'+na[0]+'px;top:'+na[1]+'px;width:'+na[2]+'px;';
            var yB = unit.querySelector('.ppt-t-years'); yB.style.cssText = 'left:'+yr[0]+'px;top:'+yr[1]+'px;width:'+yr[2]+'px;';
            nB.querySelector('.ppt-edit').style.cssText = 'font-size:'+(unit.dataset.defNafs || 30)+'px;font-weight:700;color:#ffffff;text-align:center;';
            yB.querySelector('.ppt-edit').style.cssText = 'font-size:'+(unit.dataset.defYrfs || 21)+'px;font-weight:400;color:#c9a84c;text-align:center;';
        });
        updateRibbon();
    });

    /* ---------- Pilih slide ---------- */
    function selectSlide(idx) {
        document.querySelectorAll('.ppt-slide').forEach(function (s) { s.classList.toggle('active', parseInt(s.dataset.idx,10) === idx); });
        document.querySelectorAll('.ppt-thumb').forEach(function (t) { t.classList.toggle('active', parseInt(t.dataset.idx,10) === idx); });
        setSelected(null);
        updateRibbon();
    }
    window.selectSlide = selectSlide;

    // Sinkron nama ke thumbnail (gabungan semua nama di slide itu)
    document.querySelectorAll('.ppt-nama').forEach(function (el) {
        el.addEventListener('input', function () {
            var slide = el.closest('.ppt-slide');
            if (!slide) return;
            var names = Array.from(slide.querySelectorAll('.ppt-nama'))
                             .map(function (n) { return n.textContent.trim(); })
                             .filter(Boolean);
            var t = document.getElementById('thumbnama-' + slide.dataset.idx);
            if (t) t.textContent = names.join(' & ');
        });
    });

    /* ---------- Util warna ---------- */
    function rgbToHex(rgb) {
        var m = (rgb || '').match(/\d+/g);
        if (!m || m.length < 3) return null;
        return '#' + m.slice(0,3).map(function (n) { return ('0' + parseInt(n,10).toString(16)).slice(-2); }).join('');
    }

    /* ---------- Simpan (satu entri per unit/arwah, bg dibagi se-slide) ---------- */
    function collectSlides() {
        var arr = [];
        var cs = function (e, p) { return getComputedStyle(e)[p]; };
        document.querySelectorAll('.ppt-slide').forEach(function (slideEl) {
            var bg = rgbToHex(cs(slideEl, 'backgroundColor'));
            slideEl.querySelectorAll('.ppt-unit').forEach(function (unit) {
                var photo = unit.querySelector('.ppt-photo');
                var naB = unit.querySelector('.ppt-t-nama'),  naE = naB.querySelector('.ppt-edit');
                var yrB = unit.querySelector('.ppt-t-years'), yrE = yrB.querySelector('.ppt-edit');
                arr.push({
                    id: parseInt(unit.dataset.id, 10),
                    nama: naE.textContent.trim(),
                    years: yrE.textContent.trim(),
                    bg: bg,
                    ph: { x: photo.offsetLeft, y: photo.offsetTop, w: photo.offsetWidth, h: photo.offsetHeight },
                    na: { x: naB.offsetLeft, y: naB.offsetTop, w: naB.offsetWidth,
                          fs: parseFloat(cs(naE,'fontSize')), bold: (cs(naE,'fontWeight')|0) >= 600,
                          color: rgbToHex(cs(naE,'color')), align: cs(naE,'textAlign') },
                    yr: { x: yrB.offsetLeft, y: yrB.offsetTop, w: yrB.offsetWidth,
                          fs: parseFloat(cs(yrE,'fontSize')), bold: (cs(yrE,'fontWeight')|0) >= 600,
                          color: rgbToHex(cs(yrE,'color')), align: cs(yrE,'textAlign') }
                });
            });
        });
        return arr;
    }

    function saveLayout() {
        var btn = document.getElementById('btnSave');
        var st  = document.getElementById('saveStatus');
        btn.disabled = true; var orig = btn.textContent; btn.textContent = 'Menyimpan...';
        var fd = new FormData();
        fd.append('slides', JSON.stringify(collectSlides()));
        fetch('ppt_save.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                st.textContent = res.ok ? '✓ Tersimpan' : ('Gagal: ' + (res.error || ''));
                st.style.color = res.ok ? '#1a7a40' : '#c0392b';
            })
            .catch(function () { st.textContent = 'Koneksi gagal'; st.style.color = '#c0392b'; })
            .finally(function () { btn.disabled = false; btn.textContent = orig; setTimeout(function(){ st.textContent=''; }, 3000); });
    }
    window.saveLayout = saveLayout;

    /* ---------- Export PPTX ---------- */
    function toDataURL(url) {
        return fetch(url).then(function (r) {
            if (!r.ok) return null;
            return r.blob().then(function (b) {
                return new Promise(function (res) {
                    var fr = new FileReader();
                    fr.onload = function () { res(fr.result); };
                    fr.onerror = function () { res(null); };
                    fr.readAsDataURL(b);
                });
            });
        }).catch(function () { return null; });
    }

    async function downloadPptx() {
        var btn = document.getElementById('btnPptx');
        var orig = btn.textContent; btn.disabled = true; btn.textContent = 'Menyiapkan PPTX...';
        try {
            var pptx = new PptxGenJS();
            pptx.layout = 'LAYOUT_WIDE';
            var PW = 13.33, PH = 7.5;
            var slides = document.querySelectorAll('.ppt-slide');
            var cs = function (e, p) { return getComputedStyle(e)[p]; };

            for (var i = 0; i < slides.length; i++) {
                var el = slides[i];
                var sw = el.clientWidth, sh = el.clientHeight;
                var ptScale = (PW / sw) * 72; // px -> pt

                var s = pptx.addSlide();
                s.background = { color: (rgbToHex(cs(el,'backgroundColor')) || '#1A0E00').replace('#','') };

                function addTextEl(box, edit) {
                    var txt = edit.textContent.trim();
                    if (!txt) return;
                    s.addText(txt, {
                        x: box.offsetLeft/sw*PW, y: box.offsetTop/sh*PH, w: box.offsetWidth/sw*PW, h: 0.9,
                        valign: 'top', align: cs(edit,'textAlign') || 'center',
                        color: (rgbToHex(cs(edit,'color')) || 'FFFFFF').replace('#',''),
                        bold: (cs(edit,'fontWeight')|0) >= 600,
                        fontSize: Math.round(parseFloat(cs(edit,'fontSize')) * ptScale),
                        fontFace: 'Georgia'
                    });
                }

                var units = el.querySelectorAll('.ppt-unit');
                for (var u = 0; u < units.length; u++) {
                    var unit = units[u];
                    var photo = unit.querySelector('.ppt-photo');
                    var naB = unit.querySelector('.ppt-t-nama'),  naE = naB.querySelector('.ppt-edit');
                    var yrB = unit.querySelector('.ppt-t-years'), yrE = yrB.querySelector('.ppt-edit');

                    var foto = photo.getAttribute('data-foto');
                    if (foto) {
                        var d = await toDataURL(foto);
                        if (d) s.addImage({ data: d, x: photo.offsetLeft/sw*PW, y: photo.offsetTop/sh*PH,
                            sizing: { type: 'contain', w: photo.offsetWidth/sw*PW, h: photo.offsetHeight/sh*PH } });
                    }
                    addTextEl(naB, naE);
                    addTextEl(yrB, yrE);
                }
            }
            var fname = 'arwah-foas14' + (<?= json_encode($cat ?: '') ?> ? '-' + <?= json_encode($cat ?: '') ?> : '') + '.pptx';
            await pptx.writeFile({ fileName: fname });
        } catch (e) {
            alert('Gagal membuat PPTX: ' + e.message);
        }
        btn.disabled = false; btn.textContent = orig;
    }

    updateRibbon();
    layoutSlides();
    </script>

<?php require __DIR__ . '/partials/footer.php'; ?>
