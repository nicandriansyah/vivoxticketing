/* ============================================================
   FOAS 13 - Multi-step Form Logic
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

    // Arwah validation
    if (document.getElementById('uploadArwah').checked) {
        const arwahFields = [
            { name: 'nama_arwah',     msg: 'Nama arwah wajib diisi' },
            { name: 'tahun_lahir',    msg: 'Tahun lahir wajib diisi' },
            { name: 'tahun_wafat',    msg: 'Tahun wafat wajib diisi' },
            { name: 'hubungan_arwah', msg: 'Hubungan wajib dipilih' },
        ];
        arwahFields.forEach(({ name, msg }) => {
            const el = document.querySelector(`[name="${name}"]`);
            clearError(el);
            if (!el.value.trim()) {
                showError(el, msg);
                valid = false;
            }
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

/* ---------- Arwah Toggle ---------- */

document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('uploadArwah');
    const form     = document.getElementById('arwahForm');
    if (!checkbox || !form) return;

    checkbox.addEventListener('change', function () {
        if (this.checked) {
            form.style.display = 'block';
            form.style.animation = 'fadeInUp 0.3s ease both';
        } else {
            form.style.display = 'none';
        }
    });
});

/* ---------- Drag & Drop Upload ---------- */

document.addEventListener('DOMContentLoaded', function () {
    const area    = document.getElementById('uploadArea');
    const input   = document.getElementById('fotoArwah');
    if (!area || !input) return;

    area.addEventListener('click', (e) => {
        if (!e.target.classList.contains('btn-browse') && !e.target.classList.contains('btn-remove-img')) {
            input.click();
        }
    });

    area.addEventListener('dragover', (e) => {
        e.preventDefault();
        area.classList.add('dragover');
    });
    area.addEventListener('dragleave', () => area.classList.remove('dragover'));
    area.addEventListener('drop', (e) => {
        e.preventDefault();
        area.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) handleFile(files[0]);
    });

    input.addEventListener('change', function () {
        if (this.files[0]) handleFile(this.files[0]);
    });
});

function handleFile(file) {
    if (!file.type.startsWith('image/')) { alert('Hanya file gambar yang diperbolehkan.'); return; }
    if (file.size > 2 * 1024 * 1024) { alert('Ukuran file maksimal 2MB.'); return; }

    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('uploadPlaceholder').style.display = 'none';
        document.getElementById('uploadPreview').style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function removeImage() {
    document.getElementById('fotoArwah').value = '';
    document.getElementById('previewImg').src = '';
    document.getElementById('uploadPlaceholder').style.display = 'block';
    document.getElementById('uploadPreview').style.display = 'none';
}

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
    const hubunganMap = { orang_tua: 'Orang Tua', anak: 'Anak', saudara: 'Saudara' };
    const sumbangan = get('sumbangan_amount');

    let html = `
    <div class="review-group">
        <div class="review-group-title">Data Peserta</div>
        <div class="review-rows">
            <div class="review-row"><span class="review-label">Nama Lengkap</span><span class="review-value">${esc(get('nama'))}</span></div>
            <div class="review-row"><span class="review-label">Nomor WhatsApp</span><span class="review-value">+62 ${esc(get('no_hp'))}</span></div>
            <div class="review-row"><span class="review-label">Email</span><span class="review-value">${esc(get('email'))}</span></div>
            <div class="review-row"><span class="review-label">Jumlah Tiket</span><span class="review-value">${esc(get('jumlah_tiket'))} tiket</span></div>
        </div>
    </div>`;

    if (checked) {
        html += `
    <div class="review-group">
        <div class="review-group-title">Data Arwah yang Didoakan</div>
        <div class="review-rows">
            <div class="review-row"><span class="review-label">Nama Arwah</span><span class="review-value">${esc(get('nama_arwah'))}</span></div>
            <div class="review-row"><span class="review-label">Tahun Lahir</span><span class="review-value">${esc(get('tahun_lahir'))}</span></div>
            <div class="review-row"><span class="review-label">Tahun Wafat</span><span class="review-value">${esc(get('tahun_wafat'))}</span></div>
            <div class="review-row"><span class="review-label">Hubungan</span><span class="review-value">${hubunganMap[get('hubungan_arwah')] || '-'}</span></div>
        </div>
    </div>`;
    }

    html += `
    <div class="review-group">
        <div class="review-group-title">Persembahan</div>
        <div class="review-rows">
            <div class="review-row"><span class="review-label">Sumbangan</span><span class="review-value">${sumbangan ? 'Rp ' + formatNum(sumbangan) : 'Tidak ada'}</span></div>
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
