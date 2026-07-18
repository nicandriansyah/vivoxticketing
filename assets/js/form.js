/* ============================================================
   FOAS 14 - Multi-step Form Logic
   ============================================================ */

let currentStep = 1;

/* ---------- Step Navigation ---------- */

function goToStep(step) {
    document.getElementById('step-' + currentStep).style.display = 'none';
    document.getElementById('step-' + step).style.display = 'block';
    document.getElementById('step-' + step).style.animation = 'none';
    requestAnimationFrame(() => {
        document.getElementById('step-' + step).style.animation = 'fadeInUp 0.35s ease both';
    });
    updateStepIndicators(step);
    currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateStepIndicators(active) {
    const items = document.querySelectorAll('.step-item');
    const lines = document.querySelectorAll('.step-line');
    items.forEach((el, i) => {
        el.classList.remove('active', 'completed');
        if (i + 1 < active)  el.classList.add('completed');
        if (i + 1 === active) el.classList.add('active');
    });
    lines.forEach((el, i) => {
        el.classList.toggle('completed', i + 1 < active);
    });
}

function nextStep(from) {
    if (from === 1 && !validateStep1()) return;
    if (from === 2) buildReview();
    goToStep(from + 1);
}

function prevStep(from) {
    goToStep(from - 1);
}

/* ---------- Step 1 Validation ---------- */

function validateStep1() {
    let valid = true;

    const fields = [
        { id: 'nama',         msg: 'Nama lengkap wajib diisi' },
        { id: 'no_hp',        msg: 'Nomor WhatsApp wajib diisi' },
        { id: 'email',        msg: 'Email aktif wajib diisi' },
    ];

    fields.forEach(({ id, msg }) => {
        const el = document.querySelector(`[name="${id}"]`);
        clearError(el);
        if (!el.value.trim()) {
            showError(el, msg);
            valid = false;
        }
    });

    const email = document.querySelector('[name="email"]');
    if (email.value.trim() && !isValidEmail(email.value)) {
        showError(email, 'Format email tidak valid');
        valid = false;
    }

    // Arwah validation (per-entri, sampai 5 arwah)
    if (document.getElementById('uploadArwah').checked) {
        const thisYear = new Date().getFullYear();
        document.querySelectorAll('#arwahEntries .arwah-entry').forEach((entry) => {
            const nama  = entry.querySelector('[name="nama_arwah[]"]');
            const lahir = entry.querySelector('[name="tahun_lahir[]"]');
            const wafat = entry.querySelector('[name="tahun_wafat[]"]');
            const hub   = entry.querySelector('[name="hubungan_arwah[]"]');
            [nama, lahir, wafat, hub].forEach(clearError);

            if (!nama.value.trim())  { showError(nama, 'Nama arwah wajib diisi'); valid = false; }

            const lv = parseInt(lahir.value, 10), wv = parseInt(wafat.value, 10);
            if (!lahir.value.trim() || lv < 1900 || lv > thisYear) {
                showError(lahir, 'Tahun lahir tidak valid'); valid = false;
            }
            if (!wafat.value.trim() || wv < 1900 || wv > thisYear) {
                showError(wafat, 'Tahun wafat tidak valid'); valid = false;
            }
            if (lahir.value.trim() && wafat.value.trim() && wv < lv) {
                showError(wafat, 'Tahun wafat sebelum lahir'); valid = false;
            }
            if (!hub.value.trim())   { showError(hub, 'Hubungan wajib dipilih'); valid = false; }
        });
    }

    if (!valid) {
        document.querySelector('#step-1 .form-card').classList.add('shake');
        setTimeout(() => document.querySelector('#step-1 .form-card').classList.remove('shake'), 400);
    }
    return valid;
}

function showError(el, msg) {
    el.classList.add('is-invalid');
    let err = el.parentElement.querySelector('.field-error');
    if (!err) {
        err = document.createElement('div');
        err.className = 'field-error';
        el.parentElement.appendChild(err);
    }
    err.textContent = msg;
    err.classList.add('visible');
}

function clearError(el) {
    el.classList.remove('is-invalid');
    const err = el.parentElement.querySelector('.field-error');
    if (err) err.classList.remove('visible');
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/* ---------- Cegah Enter/Return men-submit form ---------- */
// Di Safari/iOS tombol "Masuk" (Return) pada keyboard akan men-submit form &
// reload halaman padahal masih di step 1/2. Blokir Enter pada semua input.
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registrasiForm');
    if (!form) return;
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
            e.preventDefault();
        }
    });
});

/* ---------- Ticket Counter ---------- */

document.addEventListener('DOMContentLoaded', function () {
    const dec = document.getElementById('decreaseBtn');
    const inc = document.getElementById('increaseBtn');
    const cnt = document.getElementById('ticketCount');
    if (!dec || !inc || !cnt) return;

    dec.addEventListener('click', () => {
        if (parseInt(cnt.value) > 1) cnt.value = parseInt(cnt.value) - 1;
    });
    inc.addEventListener('click', () => {
        if (parseInt(cnt.value) < 5) cnt.value = parseInt(cnt.value) + 1;
    });
});

/* ---------- Arwah: toggle + multi-entri (maks 5) + upload per-entri ---------- */

document.addEventListener('DOMContentLoaded', function () {
    const MAX      = 5;
    const checkbox = document.getElementById('uploadArwah');
    const wrap     = document.getElementById('arwahForm');
    const list     = document.getElementById('arwahEntries');
    const addBtn   = document.getElementById('arwahAddBtn');
    if (!checkbox || !wrap || !list || !addBtn) return;

    checkbox.addEventListener('change', function () {
        wrap.style.display = this.checked ? 'block' : 'none';
        if (this.checked) wrap.style.animation = 'fadeInUp 0.3s ease both';
    });

    function entries() { return Array.from(list.querySelectorAll('.arwah-entry')); }

    function renumber() {
        const els = entries();
        els.forEach(function (el, i) {
            const n = el.querySelector('.arwah-num');
            if (n) n.textContent = i + 1;
            const rm = el.querySelector('.arwah-remove');
            if (rm) rm.style.display = els.length > 1 ? '' : 'none';
        });
        addBtn.style.display = els.length >= MAX ? 'none' : '';
    }

    function resetPhoto(entry) {
        const inp = entry.querySelector('.arwah-foto');
        const pv  = entry.querySelector('.arwah-pv');
        const ph  = entry.querySelector('.arwah-ph');
        const img = entry.querySelector('.arwah-previmg');
        if (inp) inp.value = '';
        if (pv)  pv.style.display = 'none';
        if (ph)  ph.style.display = 'block';
        if (img) img.src = '';
    }

    function clearEntry(entry) {
        entry.querySelectorAll('input, select').forEach(function (f) {
            if (f.type === 'file')        f.value = '';
            else if (f.tagName === 'SELECT') f.selectedIndex = 0;
            else                          f.value = '';
            clearError(f);
        });
        resetPhoto(entry);
    }

    function handleArwahFile(inp) {
        const file = inp.files[0];
        if (!file) return;
        const entry  = inp.closest('.arwah-entry');
        const okType = ['image/jpeg', 'image/png'].indexOf(file.type) !== -1 || /\.(jpe?g|png)$/i.test(file.name);
        if (!okType) { alert('Format tidak didukung. Hanya file JPG atau PNG yang diperbolehkan.'); resetPhoto(entry); return; }
        if (file.size > 2 * 1024 * 1024) { alert('Ukuran file terlalu besar. Maksimal 2 MB.'); resetPhoto(entry); return; }
        const reader = new FileReader();
        reader.onload = function (e) {
            entry.querySelector('.arwah-previmg').src = e.target.result;
            entry.querySelector('.arwah-ph').style.display = 'none';
            entry.querySelector('.arwah-pv').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    // Tambah arwah (clone entri pertama)
    addBtn.addEventListener('click', function () {
        const els = entries();
        if (els.length >= MAX) return;
        const clone = els[0].cloneNode(true);
        clearEntry(clone);
        list.appendChild(clone);
        renumber();
        clone.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    // Delegasi klik: hapus entri / hapus foto / buka file picker
    list.addEventListener('click', function (e) {
        if (e.target.closest('.arwah-remove')) {
            if (entries().length > 1) { e.target.closest('.arwah-entry').remove(); renumber(); }
            return;
        }
        if (e.target.closest('.arwah-rmimg')) {
            e.stopPropagation();
            resetPhoto(e.target.closest('.arwah-entry'));
            return;
        }
        const area = e.target.closest('.arwah-uploadarea');
        if (area && !e.target.closest('.arwah-pv')) area.querySelector('.arwah-foto').click();
    });

    // Delegasi perubahan file
    list.addEventListener('change', function (e) {
        const inp = e.target.closest('.arwah-foto');
        if (inp && inp.files[0]) handleArwahFile(inp);
    });

    // Drag & drop per area
    list.addEventListener('dragover', function (e) {
        const area = e.target.closest('.arwah-uploadarea');
        if (area) { e.preventDefault(); area.classList.add('dragover'); }
    });
    list.addEventListener('dragleave', function (e) {
        const area = e.target.closest('.arwah-uploadarea');
        if (area) area.classList.remove('dragover');
    });
    list.addEventListener('drop', function (e) {
        const area = e.target.closest('.arwah-uploadarea');
        if (!area) return;
        e.preventDefault();
        area.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const inp = area.querySelector('.arwah-foto');
            // Assign ke input agar ikut ter-submit (bukan hanya preview)
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            inp.files = dt.files;
            handleArwahFile(inp);
        }
    });

    renumber();
});

/* ---------- Copy Rekening ---------- */

function copyRekening() {
    navigator.clipboard.writeText('12345678').then(() => {
        const btn = document.querySelector('.btn-copy');
        const orig = btn.textContent;
        btn.textContent = 'Tersalin!';
        btn.classList.add('copied');
        setTimeout(() => { btn.textContent = orig; btn.classList.remove('copied'); }, 2000);
    });
}

/* ---------- Build Review ---------- */

function buildReview() {
    const get = (name) => {
        const el = document.querySelector(`[name="${name}"]`);
        return el ? el.value.trim() : '';
    };
    const checked = document.getElementById('uploadArwah') && document.getElementById('uploadArwah').checked;
    const hubunganMap = {
        orang_tua_ayah: 'Orang Tua - Ayah',
        orang_tua_ibu:  'Orang Tua - Ibu',
        pasangan:       'Pasangan',
        anak:           'Anak',
        saudara:        'Saudara/Kerabat/Teman'
    };
    const sumbangan = get('sumbangan_amount');

    const rr = 'style="display:flex;flex-direction:column;align-items:flex-start;gap:0.2rem;padding:0.75rem 0;"';
    const rl = 'style="color:#888;font-size:0.85rem;"';
    const rv = 'style="color:#1a1a1a;font-weight:600;"';

    let html = `
    <div class="review-group">
        <div class="review-group-title">Data Peserta</div>
        <div class="review-rows">
            <div class="review-row" ${rr}><span class="review-label" ${rl}>Nama Lengkap</span><span class="review-value" ${rv}>${esc(get('nama'))}</span></div>
            <div class="review-row" ${rr}><span class="review-label" ${rl}>Nomor WhatsApp</span><span class="review-value" ${rv}>+62 ${esc(get('no_hp'))}</span></div>
            <div class="review-row" ${rr}><span class="review-label" ${rl}>Email</span><span class="review-value" ${rv}>${esc(get('email'))}</span></div>
            <div class="review-row" ${rr}><span class="review-label" ${rl}>Jumlah Tiket</span><span class="review-value" ${rv}>${esc(get('jumlah_tiket'))} tiket</span></div>
        </div>
    </div>`;

    if (checked) {
        const arwahEntries = document.querySelectorAll('#arwahEntries .arwah-entry');
        arwahEntries.forEach((entry, idx) => {
            const get1 = (sel) => { const el = entry.querySelector(sel); return el ? el.value.trim() : ''; };
            const nama  = get1('[name="nama_arwah[]"]');
            const lahir = get1('[name="tahun_lahir[]"]');
            const wafat = get1('[name="tahun_wafat[]"]');
            const hub   = get1('[name="hubungan_arwah[]"]');
            const img   = entry.querySelector('.arwah-previmg');
            const previewSrc = img ? img.src : '';
            const hasPhoto = previewSrc && !previewSrc.endsWith('#') && previewSrc !== window.location.href;
            const title = arwahEntries.length > 1 ? `Data Arwah #${idx + 1}` : 'Data Arwah yang Didoakan';
            html += `
    <div class="review-group">
        <div class="review-group-title">${title}</div>
        <div class="review-rows">
            ${hasPhoto ? `<div style="text-align:center;padding:0.75rem 0;width:100%;"><img src="${previewSrc}" onclick="openPhotoModal(this.src)" style="max-width:130px;max-height:130px;border-radius:10px;object-fit:cover;cursor:pointer;box-shadow:0 2px 10px rgba(0,0,0,0.18);" title="Klik untuk perbesar"></div>` : ''}
            <div class="review-row" ${rr}><span class="review-label" ${rl}>Nama Arwah</span><span class="review-value" ${rv}>${esc(nama)}</span></div>
            <div class="review-row" ${rr}><span class="review-label" ${rl}>Tahun Lahir</span><span class="review-value" ${rv}>${esc(lahir)}</span></div>
            <div class="review-row" ${rr}><span class="review-label" ${rl}>Tahun Wafat</span><span class="review-value" ${rv}>${esc(wafat)}</span></div>
            <div class="review-row" ${rr}><span class="review-label" ${rl}>Hubungan</span><span class="review-value" ${rv}>${hubunganMap[hub] || '-'}</span></div>
        </div>
    </div>`;
        });
    }

    html += `
    <div class="review-group">
        <div class="review-group-title">Persembahan</div>
        <div class="review-rows">
            <div class="review-row" ${rr}><span class="review-label" ${rl}>Sumbangan</span><span class="review-value" ${rv}>${sumbangan ? 'Rp ' + esc(sumbangan) : 'Tidak ada'}</span></div>
        </div>
    </div>`;

    document.getElementById('reviewContent').innerHTML = html;
}

function esc(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function formatNum(n) {
    return parseInt(n).toLocaleString('id-ID');
}

/* ---------- Photo Modal ---------- */

function openPhotoModal(src) {
    var modal = document.getElementById('photoModal');
    document.getElementById('photoModalImg').src = src;
    modal.style.display = 'flex';
}

/* ---------- Format Sumbangan ---------- */

function formatSumbangan(el) {
    const raw = el.value.replace(/[^0-9]/g, '');
    if (raw === '') { el.value = ''; return; }
    el.value = parseInt(raw, 10).toLocaleString('id-ID');
}
