<?php
require 'config.php';
permission('scan');
// Endpoint pencarian santri untuk top up tanpa barcode/PIN.
if (isset($_GET['ajax']) && $_GET['ajax'] === 'students') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string)($_GET['q'] ?? ''));
    if (strlen($q) < 1) { echo json_encode([]); exit; }
    $stmt = db()->prepare('SELECT id,name,nis,class_name FROM students WHERE is_active=1 AND (name LIKE ? OR nis LIKE ?) ORDER BY name LIMIT 12');
    $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
    echo json_encode($stmt->fetchAll()); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $type = (string)($_POST['type'] ?? 'topup');
    $amount = (int)($_POST['amount'] ?? 0);
    $pdo = db();
    try {
        if (!in_array($type, ['topup', 'withdrawal'], true) || $amount < 1) throw new RuntimeException('Jenis atau nominal tidak valid.');
        $pdo->beginTransaction();
        if ($type === 'topup') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $s = $pdo->prepare('SELECT * FROM students WHERE id=? AND is_active=1 FOR UPDATE');
            $s->execute([$studentId]);
            $st = $s->fetch();
            if (!$st) throw new RuntimeException('Pilih nama santri yang valid dari daftar pencarian.');
        } else {
            $code = trim((string)($_POST['student_code'] ?? ''));
            $pin = (string)($_POST['pin'] ?? '');
            $s = $pdo->prepare('SELECT * FROM students WHERE student_code=? AND is_active=1 FOR UPDATE');
            $s->execute([$code]); $st = $s->fetch();
            if (!$st || !password_verify($pin, $st['pin_hash'])) throw new RuntimeException('Barcode atau PIN salah.');
        }
        $before = (int)$st['balance'];
        if ($type === 'withdrawal' && $before < $amount) throw new RuntimeException('Saldo tidak cukup.');
        $after = $type === 'topup' ? $before + $amount : $before - $amount;
        $pdo->prepare('UPDATE students SET balance=? WHERE id=?')->execute([$after, $st['id']]);
        $receipt = 'TRX-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $i = $pdo->prepare('INSERT INTO transactions(receipt_no,student_id,user_id,type,amount,balance_before,balance_after,note) VALUES(?,?,?,?,?,?,?,?)');
        $i->execute([$receipt, $st['id'], user()['id'], $type, $amount, $before, $after, trim((string)($_POST['note'] ?? '')) ?: null]);
        $id = $pdo->lastInsertId(); $pdo->commit();
        header('Location: receipt.php?id=' . $id); exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($e->getMessage(), 'error'); header('Location: scan.php'); exit;
    }
}
begin('Top up / Penarikan'); ?>
<section class="columns">
    <div class="panel" id="withdrawal-scan-panel" hidden>
        <h2>Scan barcode penarikan</h2><div id="reader"></div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px"><button type="button" id="start">Aktifkan kamera</button><button type="button" id="stop">Matikan kamera</button></div>
        <p class="muted">Untuk penarikan, scan barcode kartu santri dan verifikasi PIN.</p>
    </div>
    <div class="panel">
        <h2>Transaksi tabungan</h2>
        <form method="post" class="stack" id="transaction-form">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <label>Jenis transaksi<select name="type" id="transaction-type"><option value="topup">Top up / Setoran</option><option value="withdrawal">Penarikan</option></select></label>
            <div id="topup-student-wrap"><label>Cari nama santri<input type="text" id="student-search" autocomplete="off" placeholder="Ketik nama santri atau NIS" required></label><div id="student-results" class="student-search-results" role="listbox"></div><p id="selected-student" class="muted">Pilih santri dari hasil pencarian.</p><input type="hidden" name="student_id" id="student-id"></div>
            <div id="withdrawal-fields" hidden><label>Kode barcode<input id="student_code" name="student_code" autocomplete="off"></label><label>PIN santri<input name="pin" id="student-pin" inputmode="numeric" pattern="[0-9]{4,8}"></label><p class="muted">Penarikan tetap menggunakan scan barcode.</p></div>
            <label>Nominal (Rp)<input type="number" name="amount" min="1" required></label><label>Catatan<input name="note"></label><button type="submit">Simpan transaksi</button>
        </form>
    </div>
</section>
<style>.student-search-results{display:grid;gap:6px;margin-top:6px}.student-search-results button{width:100%;text-align:left;background:#fff;color:#172b4d;border:1px solid #dce4f0}.student-search-results button small{display:block;color:#68778d}.student-search-results button:hover{background:#eef5ff} [hidden]{display:none!important}</style>
<script>
(function(){
 const type=document.getElementById('transaction-type'), topup=document.getElementById('topup-student-wrap'), withdrawal=document.getElementById('withdrawal-fields'), scanPanel=document.getElementById('withdrawal-scan-panel');
 const search=document.getElementById('student-search'), results=document.getElementById('student-results'), studentId=document.getElementById('student-id'), selected=document.getElementById('selected-student'), code=document.getElementById('student_code'), pin=document.getElementById('student-pin');
 function mode(){const isTopup=type.value==='topup';topup.hidden=!isTopup;withdrawal.hidden=isTopup;scanPanel.hidden=isTopup;search.required=isTopup;studentId.required=isTopup;code.required=!isTopup;pin.required=!isTopup;if(isTopup){code.value='';pin.value='';}else{studentId.value='';search.value='';results.innerHTML='';selected.textContent='';}}
 type.addEventListener('change',mode);mode();let timer;
 search.addEventListener('input',function(){clearTimeout(timer);studentId.value='';selected.textContent='Pilih santri dari hasil pencarian.';const q=search.value.trim();results.innerHTML='';if(!q)return;timer=setTimeout(()=>fetch('scan.php?ajax=students&q='+encodeURIComponent(q)).then(r=>r.json()).then(rows=>{results.innerHTML='';rows.forEach(st=>{const b=document.createElement('button');b.type='button';b.innerHTML='';const strong=document.createElement('strong');strong.textContent=st.name;const small=document.createElement('small');small.textContent='NIS: '+st.nis+' · Kelas: '+st.class_name;b.append(strong,small);b.addEventListener('click',()=>{studentId.value=st.id;search.value=st.name;selected.textContent='Santri dipilih: '+st.name+' · NIS '+st.nis;results.innerHTML='';});results.appendChild(b);});if(!rows.length){const p=document.createElement('p');p.className='muted';p.textContent='Santri tidak ditemukan.';results.appendChild(p);}}).catch(()=>{}),180);});
 document.getElementById('transaction-form').addEventListener('submit',e=>{if(type.value==='topup'&&!studentId.value){e.preventDefault();alert('Pilih nama santri dari daftar hasil pencarian terlebih dahulu.');}});
})();
</script>
<?php endpage(); ?>
