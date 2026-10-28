const scanners = {};

async function startScanner(key, readerId, onScan, statusId = null) {
    try {
        if (typeof Html5Qrcode === 'undefined') {
            throw new Error('Pustaka pemindai belum termuat. Periksa koneksi internet.');
        }

        if (scanners[key]) {
            try { await scanners[key].stop(); } catch (_) {}
            scanners[key].clear();
        }

        scanners[key] = new Html5Qrcode(readerId);
        await scanners[key].start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 240, height: 160 } },
            value => {
                onScan(value.trim());
                if (statusId) {
                    const status = document.getElementById(statusId);
                    if (status) {
                        status.textContent = 'Barcode berhasil dipindai. Periksa kode lalu lanjutkan.';
                        status.classList.add('scan-success');
                    }
                }
                stopScanner(key);
            },
            () => {}
        );

        if (statusId) {
            const status = document.getElementById(statusId);
            if (status) status.textContent = 'Kamera aktif. Arahkan ke barcode pada kartu santri.';
        }
    } catch (error) {
        showToast(error.message || 'Kamera gagal dibuka. Izinkan akses kamera dan gunakan localhost atau HTTPS.', 'error');
        if (statusId) {
            const status = document.getElementById(statusId);
            if (status) status.textContent = 'Kamera tidak dapat dibuka. Periksa izin kamera browser.';
        }
    }
}

async function stopScanner(key) {
    if (!scanners[key]) return;
    try {
        if (scanners[key].isScanning) await scanners[key].stop();
        scanners[key].clear();
    } catch (_) {}
}

document.getElementById('start')?.addEventListener('click', () => {
    startScanner('transaction', 'reader', value => {
        const input = document.getElementById('student_code');
        if (input) input.value = value;
        showToast('Barcode santri berhasil dipindai.');
    });
});

document.getElementById('stop')?.addEventListener('click', () => stopScanner('transaction'));

document.getElementById('register-start')?.addEventListener('click', () => {
    startScanner('registration', 'register-reader', value => {
        const input = document.getElementById('register-barcode');
        if (input) input.value = value;
        showToast('Barcode berhasil dibaca. Lengkapi data santri untuk menyimpan pendaftaran.');
    }, 'register-status');
});

document.getElementById('register-stop')?.addEventListener('click', async () => {
    await stopScanner('registration');
    const status = document.getElementById('register-status');
    if (status) status.textContent = 'Kamera dimatikan.';
});

// Keep the dialog as a direct child of body. This avoids desktop stacking/overflow
// issues caused by transformed or clipped application layout containers.
(() => {
    const modal = document.getElementById('card');
    if (modal && modal.parentElement !== document.body) document.body.appendChild(modal);
    if (!modal) return;
    modal.addEventListener('click', event => { if (event.target === modal) closeCard(); });
    modal.querySelector('.idcard')?.addEventListener('click', event => event.stopPropagation());
    modal.querySelectorAll('[onclick="closeCard()"]')?.forEach(button => button.addEventListener('click', event => { event.preventDefault(); closeCard(); }));
})();
let currentCardPassword = '';
let currentCardUsername = '';
function card(name, nis, cls, code, username, password) {
    const modal = document.getElementById('card');
    if (!modal) return;
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
    setText('cn', name || 'Santri');
    setText('ci', 'NIS: ' + (nis || '-'));
    setText('cc', 'Kelas: ' + (cls || '-'));
    setText('c-code', code || '-');
    currentCardUsername = String(username || '');
    currentCardPassword = String(password || '');
    setText('c-username', currentCardUsername || 'Belum tersedia');
    setText('c-password', currentCardPassword || 'Belum tersedia');
    setText('c-password-note', currentCardPassword ? 'Simpan informasi akun ini dengan aman.' : 'Password belum tersimpan. Atur ulang melalui menu Pengguna.');
    const copyCardButton = document.getElementById('copy-card-password');
    if (copyCardButton) copyCardButton.disabled = !currentCardUsername && !currentCardPassword;
    const qr = document.getElementById('qr');
    if (qr) {
        qr.replaceChildren();
        if (typeof QRCode !== 'undefined' && code) {
            try { new QRCode(qr, { text: String(code), width: 144, height: 144, correctLevel: QRCode.CorrectLevel ? QRCode.CorrectLevel.M : 0 }); }
            catch (_) { qr.textContent = String(code); }
        } else {
            const fallback = document.createElement('div');
            fallback.className = 'qr-fallback'; fallback.textContent = 'QR tidak tersedia'; qr.appendChild(fallback);
        }
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('card-modal-open');
    const closeButton = modal.querySelector('.idcard-actions button');
    if (closeButton) closeButton.focus({ preventScroll: true });
}
function closeCard() {
    const modal = document.getElementById('card');
    if (modal) { modal.hidden = true; modal.setAttribute('aria-hidden', 'true'); }
    document.body.classList.remove('card-modal-open', 'printing-card');
}
function printStudentCard() {
    const modal = document.getElementById('card');
    const sourceCard = document.getElementById('printable-card');
    if (!modal || modal.hidden || !sourceCard) return;

    // A dedicated print document avoids desktop layouts, sticky navigation and
    // application-level print rules hiding the card in Chrome/Edge print preview.
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    if (printWindow) {
        const clone = sourceCard.cloneNode(true);
        clone.querySelectorAll('.no-print').forEach(el => el.remove());
        clone.querySelectorAll('canvas').forEach(canvas => {
            try {
                const image = document.createElement('img');
                image.src = canvas.toDataURL('image/png');
                image.alt = 'QR Code';
                image.style.cssText = 'display:block;width:30mm;height:30mm;image-rendering:pixelated';
                canvas.replaceWith(image);
            } catch (_) {}
        });
        const cssUrl = new URL('assets/style.css', document.baseURI).href;
        printWindow.document.open();
        printWindow.document.write(`<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cetak Kartu Santri</title><link rel="stylesheet" href="${cssUrl}"><style>
          @page{size:A4 landscape;margin:12mm}*{box-sizing:border-box}html,body{margin:0;padding:0;background:#fff!important;color:#153c2d;font-family:Arial,sans-serif}.print-wrap{width:86mm;margin:0 auto}.idcard{display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;grid-template-rows:auto 1fr auto auto!important;position:static!important;width:86mm!important;height:54mm!important;min-height:54mm!important;max-width:86mm!important;max-height:54mm!important;overflow:hidden!important;margin:0!important;padding:0!important;border:1px solid #dbe9df!important;border-radius:3mm!important;box-shadow:none!important;background:#fff!important;break-inside:avoid}.idcard-top{grid-column:1/-1!important;grid-row:1!important;padding:2.5mm!important}.idcard-content{grid-column:1!important;grid-row:2!important;padding:3mm!important;align-self:center!important}.idcard-content h2{font-size:12pt!important;margin:1mm 0!important}.idcard-content p{font-size:7pt!important;margin:1mm 0!important}.idcard-code-area{grid-column:2!important;grid-row:2/4!important;display:flex!important;flex-direction:row!important;align-items:center!important;gap:2mm!important;margin:2mm 2mm 2mm 0!important;padding:2mm!important}.idcard-code-area #qr{display:grid!important;flex:0 0 17mm!important;width:17mm!important;min-height:17mm!important}.idcard-code-area #qr img,.idcard-code-area #qr canvas{width:16mm!important;height:16mm!important}.idcard-code-text small{font-size:6pt!important;margin-bottom:1mm!important}.idcard-code-text code{font-size:7pt!important}.idcard-code-text p{font-size:5.5pt!important;margin-top:1mm!important}.idcard-password{grid-column:1!important;grid-row:3!important;display:block!important;margin:0 1mm 2mm 2mm!important;padding:1.5mm!important}.idcard-password small{font-size:6pt!important;margin-bottom:1mm!important}.idcard-password p{font-size:5.5pt!important;margin:.5mm 0!important}.idcard-password code{font-size:5.5pt!important}.idcard-password button{display:none!important}.idcard-bottom{grid-column:1/-1!important;grid-row:4!important;padding:0 3mm 1.5mm!important;font-size:5pt!important}.idcard-logo{width:8mm!important;height:8mm!important;object-fit:contain!important}.idcard-password{break-inside:avoid}*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}@media screen{body{padding:24px;background:#f3f6f4!important}.print-wrap{margin:0 auto}}.idcard,.idcard-content,.idcard-content h2,.idcard-content p,.idcard-code-text,.idcard-code-text code,.idcard-code-text p,.idcard-password,.idcard-password p,.idcard-password code,.idcard-bottom{color:#153c2d!important}.idcard-top,.idcard-top strong,.idcard-top small,.idcard-chip{color:#f3fff8!important}.idcard-code-text small,.idcard-password small{color:#087f5b!important}.idcard-code-text p,.idcard-password .idcard-note,.idcard-bottom{color:#718579!important}
        </style></head><body><div class="print-wrap">${clone.outerHTML}</div><script>function doPrint(){setTimeout(()=>{window.focus();window.print()},500)};if(document.readyState==='complete'){doPrint()}else{window.addEventListener('load',doPrint,{once:true})};window.addEventListener('afterprint',()=>{setTimeout(()=>window.close(),250)});<\/script></body></html>`);
        printWindow.document.close();
        return;
    }

    // Fallback when the browser blocks pop-ups.
    document.body.classList.add('printing-card');
    const finish = () => { document.body.classList.remove('printing-card'); window.removeEventListener('afterprint', finish); document.removeEventListener('visibilitychange', onVisibility); };
    const onVisibility = () => { if (document.visibilityState === 'visible') window.setTimeout(finish, 300); };
    window.addEventListener('afterprint', finish, { once: true });
    document.addEventListener('visibilitychange', onVisibility);
    window.setTimeout(() => window.print(), 250);
}
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeCard(); });

function showToast(message,type='ok'){let toast=document.getElementById('app-toast');if(!toast){toast=document.createElement('div');toast.id='app-toast';toast.style.cssText='position:fixed;right:22px;bottom:22px;z-index:99;max-width:min(420px,calc(100vw - 32px));padding:15px 18px;border-radius:14px;color:white;font:600 13px DM Sans,sans-serif;box-shadow:0 14px 40px #10251e35;transition:opacity .2s';document.body.appendChild(toast)}toast.style.background=type==='error'?'#b9382b':'#087f5b';toast.textContent=message;toast.style.opacity='1';clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>toast.style.opacity='0',4500)}


// Navigasi mobile: sidebar drawer dari kanan.
(() => {
    const toggle = document.querySelector('.menu-toggle');
    const sidebar = document.getElementById('app-sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!toggle || !sidebar || !backdrop) return;

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'sidebar-close';
    closeButton.setAttribute('aria-label', 'Tutup menu navigasi');
    closeButton.innerHTML = '<span>Menu navigasi</span><span aria-hidden="true">×</span>';
    sidebar.prepend(closeButton);

    const setOpen = open => {
        document.body.classList.toggle('sidebar-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        if (open) closeButton.focus({ preventScroll: true });
        else toggle.focus({ preventScroll: true });
    };
    toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('sidebar-open')));
    closeButton.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') setOpen(false);
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 760) setOpen(false);
    });
})();

async function copyTextToClipboard(value) {
    if (!value) return false;
    try {
        if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(value); return true; }
    } catch (_) {}
    const temp = document.createElement('textarea'); temp.value = value; temp.setAttribute('readonly', '');
    temp.style.position = 'fixed'; temp.style.opacity = '0'; document.body.appendChild(temp); temp.select();
    let ok = false; try { ok = document.execCommand('copy'); } catch (_) {} temp.remove(); return ok;
}
async function copyCardPassword() {
    const button = document.getElementById('copy-card-password');
    const accountText = 'Username: ' + (currentCardUsername || '-') + '\nPassword: ' + (currentCardPassword || '-');
    const ok = await copyTextToClipboard(accountText);
    if (button) { button.textContent = ok ? 'Tersalin ✓' : 'Gagal menyalin'; window.setTimeout(() => { if (button.isConnected) button.textContent = 'Salin akun'; }, 1600); }
}
document.addEventListener('click', async event => {
    const button = event.target.closest('.copy-password'); if (!button) return;
    const field = button.parentElement?.querySelector('input.stored-password');
    const ok = await copyTextToClipboard(field?.value || '');
    button.textContent = ok ? 'Tersalin ✓' : 'Gagal';
    window.setTimeout(() => { if (button.isConnected) button.textContent = 'Salin'; }, 1600);
});
