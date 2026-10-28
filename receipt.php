<?php require 'config.php';
login_required();
$s = db()->prepare('SELECT t.*,s.name,s.nis,s.class_name,u.username FROM transactions t JOIN students s ON s.id=t.student_id JOIN users u ON u.id=t.user_id WHERE t.id=?');
$s->execute([(int)($_GET['id'] ?? 0)]);
$r = $s->fetch();
if (!$r) {
    http_response_code(404);
    exit('Kwitansi tidak ditemukan');
}
if (user()['role'] === 'santri' && (int)$r['student_id'] !== (int)user()['student_id']) {
    http_response_code(403);
    exit('Akses ditolak');
} ?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Kwitansi</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm
        }

        * {
            box-sizing: border-box
        }

        body.receipt-page {
            margin: 0;
            padding: 24px;
            background: #eef3f0;
            color: #18352a;
            font-family: Arial, Helvetica, sans-serif
        }

        .actions {
            max-width: 1000px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px
        }

        .actions a,
        .actions button {
            border: 0;
            border-radius: 8px;
            padding: 10px 16px;
            background: #087f5b;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer
        }

        .actions a {
            background: #e0ebe5;
            color: #214638
        }

        .receipt {
            max-width: 1000px;
            min-height: 440px;
            margin: auto;
            padding: 28px 34px;
            background: #fff;
            border: 1px solid #cbdcd2;
            border-radius: 14px;
            box-shadow: 0 12px 32px rgba(16, 45, 31, .10);
            position: relative
        }

        .receipt:before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 9px;
            background: #087f5b;
            border-radius: 14px 0 0 14px
        }

        .receipt-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #dce9e1
        }

        .receipt h1 {
            margin: 0 0 6px;
            color: #087f5b;
            font-size: 25px;
            letter-spacing: .6px
        }

        .subtitle {
            margin: 0;
            color: #61766a;
            font-size: 12px
        }

        .receipt-number {
            min-width: 210px;
            text-align: right;
            font-size: 12px;
            color: #62766b
        }

        .receipt-number strong {
            display: block;
            color: #18352a;
            font-size: 15px;
            margin-top: 5px
        }

        .receipt-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 9px 28px;
            padding: 18px 0
        }

        .receipt-item {
            font-size: 13px;
            color: #61766a;
            border-bottom: 1px dashed #dce6df;
            padding: 8px 0
        }

        .receipt-item strong {
            display: block;
            margin-top: 5px;
            font-size: 15px;
            color: #18352a;
            overflow-wrap: anywhere
        }

        .amount-box {
            margin: 4px 0 14px;
            padding: 14px 18px;
            border-radius: 10px;
            background: #edf8f1;
            border: 1px solid #cde9d7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px
        }

        .amount-box span {
            font-size: 13px;
            color: #436b55
        }

        .amount-box strong {
            font-size: 24px;
            color: #087f5b
        }

        .receipt-bottom {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 24px
        }

        .balance-info {
            font-size: 12px;
            line-height: 1.8;
            color: #4e6658
        }

        .sign {
            display: flex;
            gap: 50px;
            text-align: center;
            font-size: 12px;
            color: #526b5c
        }

        .sign span {
            display: block;
            width: 120px;
            padding-top: 48px;
            border-bottom: 1px solid #8ca394
        }

        @media print {
            body.receipt-page {
                background: #fff;
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact
            }

            .actions {
                display: none !important
            }

            .receipt {
                max-width: none;
                min-height: 0;
                height: 180mm;
                border-radius: 0;
                border: 1px solid #cbdcd2;
                box-shadow: none;
                padding: 20px 28px;
                break-inside: avoid
            }

            .receipt:before {
                border-radius: 0
            }
        }

        @media(max-width:700px) {
            body.receipt-page {
                padding: 12px
            }

            .receipt {
                padding: 22px 18px
            }

            .receipt-head,
            .receipt-bottom {
                flex-direction: column;
                align-items: stretch
            }

            .receipt-number {
                text-align: left
            }

            .receipt-grid {
                grid-template-columns: 1fr
            }

            .amount-box {
                align-items: flex-start;
                flex-direction: column
            }
        }
    </style>
</head>

<body class="receipt-page">
    <div class="actions"><a href="javascript:history.back()">← Kembali</a><button onclick="print()">Cetak / Simpan PDF</button></div>
    <article class="receipt">
        <div class="receipt-head">
            <div>
                <h1>KWITANSI TABUNGAN SANTRI</h1>
                <p class="subtitle">Bukti transaksi resmi tabungan santri</p>
            </div>
            <div class="receipt-number">NOMOR KWITANSI<strong><?= e($r['receipt_no']) ?></strong></div>
        </div>
        <div class="receipt-grid">
            <div class="receipt-item">Nama santri<strong><?= e($r['name']) ?></strong></div>
            <div class="receipt-item">NIS / Kelas<strong><?= e($r['nis']) ?> / <?= e($r['class_name']) ?></strong></div>
            <div class="receipt-item">Tanggal transaksi<strong><?= e($r['created_at']) ?></strong></div>
            <div class="receipt-item">Jenis transaksi<strong><?= e($r['type'] === 'topup' ? 'Top up / Setoran' : 'Penarikan') ?></strong></div>
        </div>
        <div class="amount-box"><span>JUMLAH TRANSAKSI</span><strong><?= e(money((int)$r['amount'])) ?></strong></div>
        <div class="receipt-bottom">
            <div class="balance-info">
                <div>Saldo sebelum: <strong><?= e(money((int)$r['balance_before'])) ?></strong></div>
                <div>Saldo sesudah: <strong><?= e(money((int)$r['balance_after'])) ?></strong></div>
                <div>Petugas: <strong><?= e($r['username']) ?></strong></div>
                <div>Catatan: <?= e($r['note'] ?? '—') ?></div>
            </div>
            <div class="sign"><span>Santri / Wali</span><span>Petugas</span></div>
        </div>
    </article>
</body>

</html>