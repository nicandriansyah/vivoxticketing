<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
require_once __DIR__ . '/helpers.php';

$dbReady = (bool)$pdo;
$cat  = $_GET['cat'] ?? '';
$opts = hubunganOptions();
$rows = [];

if ($dbReady) {
    try {
        $where  = "WHERE upload_arwah = 1 AND nama_arwah IS NOT NULL AND nama_arwah <> ''";
        $params = [];
        if ($cat !== '' && array_key_exists($cat, $opts)) {
            $where   .= " AND hubungan_arwah = ?";
            $params[] = $cat;
        }
        $stmt = $pdo->prepare("SELECT id, kode_tiket, nama_arwah, tahun_lahir, tahun_wafat, foto_arwah, hubungan_arwah, slide_layout
                               FROM registrations $where ORDER BY hubungan_arwah ASC, id DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pptTitle = (string)getSetting($pdo, 'ppt_title', 'Mengenang & Mendoakan');
    } catch (Exception $e) { $dbReady = false; $dbErr = $e->getMessage(); }
}
$pptTitle = $pptTitle ?? 'Mengenang & Mendoakan';

function yearsText($l, $w): string {
    $l = $l ? (string)$l : '';
    $w = $w ? (string)$w : '';
    if ($l && $w) return "$l – $w";
    return $l ?: $w;
}

$pageTitle  = 'PPT Generator';
$activeMenu = 'ppt';
$mainClass  = 'adm-main-full';
require __DIR__ . '/partials/header.php';
?>

        <?php if (!$dbReady): ?>
            <div class="adm-alert">Koneksi database gagal<?= isset($dbErr) ? ': ' . htmlspecialchars($dbErr) : '' ?>.</div>
        <?php else: ?>

        <!-- Card: kategori + judul + download -->
        <div class="ppt-card ppt-toolbar">
            <form method="GET" class="ppt-filter">
                <label>Kategori</label>
                <select name="cat" class="adm-input" onchange="this.form.submit()">
                    <option value="" <?= $cat === '' ? 'selected' : '' ?>>Semua Kategori</option>
                    <?php foreach ($opts as $k => $label): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= $cat === $k ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="ppt-title-field">
                <label>Judul Slide</label>
                <input type="text" id="slideTitle" class="adm-input" value="<?= htmlspecialchars($pptTitle) ?>" placeholder="Judul di tiap slide">
            </div>
            <button type="button" class="adm-btn-secondary" id="btnPptx" onclick="downloadPptx()" <?= $rows ? '' : 'disabled' ?>>⬇ Download PPTX (<?= count($rows) ?>)</button>
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
                    <?php foreach ($rows as $i => $r):
                        $foto = $r['foto_arwah'] ? adminUploadUrl($r['foto_arwah']) : '';
                        $L    = json_decode($r['slide_layout'] ?? '', true);
                        $namaT = (is_array($L) && isset($L['nama']) && $L['nama'] !== '') ? $L['nama'] : $r['nama_arwah'];
                    ?>
                    <div class="ppt-thumb <?= $i === 0 ? 'active' : '' ?>" data-idx="<?= $i ?>" onclick="selectSlide(<?= $i ?>)">
                        <span class="ppt-thumb-no"><?= $i + 1 ?></span>
                        <?php if ($foto): ?><img class="ppt-thumb-foto" src="<?= htmlspecialchars($foto) ?>" alt=""><?php else: ?><span class="ppt-thumb-foto ppt-thumb-noimg">—</span><?php endif; ?>
                        <span class="ppt-thumb-nama" id="thumbnama-<?= $i ?>"><?= htmlspecialchars($namaT) ?></span>
                    </div>
                    <?php endforeach; ?>
                </aside>
                <button type="button" class="adm-btn-primary ppt-save-btn" id="btnSave" onclick="saveLayout()">💾 Simpan</button>
                <span class="ppt-save-status" id="saveStatus"></span>
            </div>

            <div class="ppt-canvas-area">
                <?php foreach ($rows as $i => $r):
                    $foto = $r['foto_arwah'] ? adminUploadUrl($r['foto_arwah']) : '';
                    $L    = json_decode($r['slide_layout'] ?? '', true);
                    if (!is_array($L)) $L = [];
                    $namaT = (isset($L['nama']) && $L['nama'] !== '') ? $L['nama'] : $r['nama_arwah'];
                    $yrT   = isset($L['years']) ? $L['years'] : yearsText($r['tahun_lahir'], $r['tahun_wafat']);
                    $bg = $L['bg'] ?? '#1a0e00';
                    $ph = $L['ph'] ?? ['x'=>240,'y'=>25,'w'=>160,'h'=>160];
                    $na = $L['na'] ?? ['x'=>36,'y'=>213,'w'=>568];
                    $yp = $L['yr'] ?? ['x'=>36,'y'=>270,'w'=>568];
                    $f = fn($a,$k,$d) => isset($a[$k]) && is_numeric($a[$k]) ? (float)$a[$k] : $d;
                    $sv = fn($a,$k,$d) => isset($a[$k]) && $a[$k] !== '' ? $a[$k] : $d;
                    // style teks
                    $naFs = $f($na,'fs',30); $naColor = $sv($na,'color','#ffffff'); $naAlign = $sv($na,'align','center'); $naBold = isset($na['bold']) ? ($na['bold'] ? 700 : 400) : 700;
                    $yrFs = $f($yp,'fs',21); $yrColor = $sv($yp,'color','#c9a84c'); $yrAlign = $sv($yp,'align','center'); $yrBold = isset($yp['bold']) ? ($yp['bold'] ? 700 : 400) : 400;
                ?>
                <div class="ppt-slide <?= $i === 0 ? 'active' : '' ?>" data-idx="<?= $i ?>" data-id="<?= (int)$r['id'] ?>" style="background:<?= htmlspecialchars($bg) ?>;">
                    <div class="ppt-cat"><?= htmlspecialchars(hubunganLabel($r['hubungan_arwah'])) ?></div>

                    <div class="ppt-item ppt-photo" data-foto="<?= htmlspecialchars($foto) ?>" style="left:<?= $f($ph,'x',240) ?>px;top:<?= $f($ph,'y',25) ?>px;width:<?= $f($ph,'w',160) ?>px;height:<?= $f($ph,'h',160) ?>px;">
                        <?php if ($foto): ?>
                            <img src="<?= htmlspecialchars($foto) ?>" alt="" draggable="false">
                        <?php else: ?>
                            <div class="ppt-foto-empty">Tanpa Foto</div>
                        <?php endif; ?>
                        <span class="ppt-move" title="Geser">✥</span>
                        <span class="ppt-resize" title="Ubah ukuran"></span>
                    </div>

                    <div class="ppt-item ppt-tbox ppt-t-nama" style="left:<?= $f($na,'x',36) ?>px;top:<?= $f($na,'y',213) ?>px;width:<?= $f($na,'w',568) ?>px;">
                        <span class="ppt-move" title="Geser">✥</span>
                        <span class="ppt-edit ppt-nama" contenteditable="true" spellcheck="false" data-idx="<?= $i ?>"
                              style="font-size:<?= $naFs ?>px;font-weight:<?= $naBold ?>;color:<?= htmlspecialchars($naColor) ?>;text-align:<?= htmlspecialchars($naAlign) ?>;"><?= htmlspecialchars($namaT) ?></span>
                    </div>

                    <div class="ppt-item ppt-tbox ppt-t-years" style="left:<?= $f($yp,'x',36) ?>px;top:<?= $f($yp,'y',270) ?>px;width:<?= $f($yp,'w',568) ?>px;">
                        <span class="ppt-move" title="Geser">✥</span>
                        <span class="ppt-edit ppt-years" contenteditable="true" spellcheck="false"
                              style="font-size:<?= $yrFs ?>px;font-weight:<?= $yrBold ?>;color:<?= htmlspecialchars($yrColor) ?>;text-align:<?= htmlspecialchars($yrAlign) ?>;"><?= htmlspecialchars($yrT) ?></span>
                    </div>
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
        slideScale = area.clientWidth / SLIDE_W;
        document.querySelectorAll('.ppt-slide').forEach(function (s) { s.style.transform = 'scale(' + slideScale + ')'; });
        area.style.height = (SLIDE_H * slideScale) + 'px';
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

    document.getElementById('btnReset').addEventListener('click', function () {
        var slide = document.querySelector('.ppt-slide.active');
        if (!slide) return;
        var ph = slide.querySelector('.ppt-photo');
        ph.style.cssText = 'left:240px;top:25px;width:160px;height:160px;';
        var na = slide.querySelector('.ppt-t-nama');  na.style.cssText = 'left:36px;top:213px;width:568px;';
        var yr = slide.querySelector('.ppt-t-years'); yr.style.cssText = 'left:36px;top:270px;width:568px;';
        na.querySelector('.ppt-edit').style.cssText = 'font-size:30px;font-weight:700;color:#ffffff;text-align:center;';
        yr.querySelector('.ppt-edit').style.cssText = 'font-size:21px;font-weight:400;color:#c9a84c;text-align:center;';
        slide.style.background = '#1a0e00';
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

    document.querySelectorAll('.ppt-nama').forEach(function (el) {
        el.addEventListener('input', function () {
            var t = document.getElementById('thumbnama-' + el.dataset.idx);
            if (t) t.textContent = el.textContent;
        });
    });

    /* ---------- Util warna ---------- */
    function rgbToHex(rgb) {
        var m = (rgb || '').match(/\d+/g);
        if (!m || m.length < 3) return null;
        return '#' + m.slice(0,3).map(function (n) { return ('0' + parseInt(n,10).toString(16)).slice(-2); }).join('');
    }

    /* ---------- Simpan ---------- */
    function collectSlides() {
        var arr = [];
        document.querySelectorAll('.ppt-slide').forEach(function (el) {
            var photo = el.querySelector('.ppt-photo');
            var naB = el.querySelector('.ppt-t-nama'),  naE = naB.querySelector('.ppt-edit');
            var yrB = el.querySelector('.ppt-t-years'), yrE = yrB.querySelector('.ppt-edit');
            var cs = function (e, p) { return getComputedStyle(e)[p]; };
            arr.push({
                id: parseInt(el.dataset.id, 10),
                nama: naE.textContent.trim(),
                years: yrE.textContent.trim(),
                bg: rgbToHex(cs(el, 'backgroundColor')),
                ph: { x: photo.offsetLeft, y: photo.offsetTop, w: photo.offsetWidth, h: photo.offsetHeight },
                na: { x: naB.offsetLeft, y: naB.offsetTop, w: naB.offsetWidth,
                      fs: parseFloat(cs(naE,'fontSize')), bold: (cs(naE,'fontWeight')|0) >= 600,
                      color: rgbToHex(cs(naE,'color')), align: cs(naE,'textAlign') },
                yr: { x: yrB.offsetLeft, y: yrB.offsetTop, w: yrB.offsetWidth,
                      fs: parseFloat(cs(yrE,'fontSize')), bold: (cs(yrE,'fontWeight')|0) >= 600,
                      color: rgbToHex(cs(yrE,'color')), align: cs(yrE,'textAlign') }
            });
        });
        return arr;
    }

    function saveLayout() {
        var btn = document.getElementById('btnSave');
        var st  = document.getElementById('saveStatus');
        btn.disabled = true; var orig = btn.textContent; btn.textContent = 'Menyimpan...';
        var fd = new FormData();
        fd.append('title', document.getElementById('slideTitle').value || '');
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
            var title = (document.getElementById('slideTitle').value || '').trim();
            var slides = document.querySelectorAll('.ppt-slide');
            var cs = function (e, p) { return getComputedStyle(e)[p]; };

            for (var i = 0; i < slides.length; i++) {
                var el = slides[i];
                var sw = el.clientWidth, sh = el.clientHeight;
                var ptScale = (PW / sw) * 72; // px -> pt
                var photo = el.querySelector('.ppt-photo');
                var naB = el.querySelector('.ppt-t-nama'),  naE = naB.querySelector('.ppt-edit');
                var yrB = el.querySelector('.ppt-t-years'), yrE = yrB.querySelector('.ppt-edit');
                var cat = el.querySelector('.ppt-cat').textContent.trim();

                var s = pptx.addSlide();
                s.background = { color: (rgbToHex(cs(el,'backgroundColor')) || '#1A0E00').replace('#','') };

                if (title) {
                    s.addText(title, { x: 0.5, y: 0.3, w: PW - 1, h: 0.6, align: 'center',
                        color: 'E8C66E', fontSize: 22, bold: true, fontFace: 'Georgia' });
                }
                var foto = photo.getAttribute('data-foto');
                if (foto) {
                    var d = await toDataURL(foto);
                    if (d) s.addImage({ data: d, x: photo.offsetLeft/sw*PW, y: photo.offsetTop/sh*PH,
                        sizing: { type: 'contain', w: photo.offsetWidth/sw*PW, h: photo.offsetHeight/sh*PH } });
                }
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
                addTextEl(naB, naE);
                addTextEl(yrB, yrE);

                if (cat) {
                    s.addText(cat.toUpperCase(), { x: 0.5, y: PH - 0.55, w: PW - 1, h: 0.35, align: 'center',
                        color: '9A7A55', fontSize: 11, charSpacing: 2 });
                }
            }
            var fname = 'arwah-foas13' + (<?= json_encode($cat ?: '') ?> ? '-' + <?= json_encode($cat ?: '') ?> : '') + '.pptx';
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
