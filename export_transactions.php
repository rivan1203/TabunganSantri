<?php
require 'config.php';
permission('transactions');
$format = (string)($_GET['format'] ?? '');
if (!in_array($format, ['pdf','excel'], true)) { http_response_code(400); exit('Format laporan tidak valid.'); }
$from=trim((string)($_GET['from']??'')); $to=trim((string)($_GET['to']??'')); $type=(string)($_GET['type']??'');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from))$from='';
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))$to='';
if(!in_array($type,['topup','withdrawal'],true))$type='';
$where=[];$args=[];
if($from!==''){$where[]='t.created_at >= ?';$args[]=$from.' 00:00:00';}
if($to!==''){$where[]='t.created_at < DATE_ADD(?, INTERVAL 1 DAY)';$args[]=$to;}
if($type!==''){$where[]='t.type = ?';$args[]=$type;}
if(user()['role']==='santri'){$where[]='t.student_id = ?';$args[]=(int)user()['student_id'];}
$sql='SELECT t.receipt_no,t.created_at,t.type,t.amount,t.balance_before,t.balance_after,t.note,s.name,s.nis,s.class_name,u.username FROM transactions t JOIN students s ON s.id=t.student_id JOIN users u ON u.id=t.user_id';
if($where)$sql.=' WHERE '.implode(' AND ',$where);$sql.=' ORDER BY t.id DESC';
$stmt=db()->prepare($sql);$stmt->execute($args);$rows=$stmt->fetchAll();
$stamp=date('Ymd_His');
if($format==='excel'){
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="laporan_transaksi_'.$stamp.'.csv"');
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','w');
    fputcsv($out,['No. Kwitansi','Tanggal','Nama Santri','NIS','Kelas','Jenis Transaksi','Nominal (Rp)','Saldo Sebelum (Rp)','Saldo Akhir (Rp)','Petugas','Catatan'],';','"','\\');
    foreach($rows as $r){$vals=[$r['receipt_no'],$r['created_at'],$r['name'],$r['nis'],$r['class_name'],$r['type']==='topup'?'Top up':'Penarikan',(int)$r['amount'],(int)$r['balance_before'],(int)$r['balance_after'],$r['username'],$r['note']??''];foreach($vals as &$v){$v=(string)$v;if(preg_match('/^[=+@\-]/',$v))$v="'".$v;}unset($v);fputcsv($out,$vals,';','"','\\');}
    fclose($out);exit;
}

// PDF laporan dengan layout editorial modern, tanpa dependensi eksternal.
function pdfText(string $value): string {
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: '';
    return str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", ' ', ' '], $value);
}
function pdfLine(string $text, int $x, int $y, int $size=8, string $font='F1', string $color='0.16 0.28 0.22'): string {
    return $color." rg BT /{$font} {$size} Tf {$x} {$y} Td (".pdfText($text).") Tj ET\n";
}
function pdfRect(float $x, float $y, float $w, float $h, string $color): string {
    return "q {$color} rg {$x} {$y} {$w} {$h} re f Q\n";
}
$pages = array_chunk($rows, 13);
if (!$pages) $pages = [[]];
$streams = [];
$grandTotal = array_sum(array_map(static fn($r) => (int)$r['amount'], $rows));
$topups = 0; $withdrawals = 0;
foreach ($rows as $r) {
    if ($r['type'] === 'topup') $topups += (int)$r['amount'];
    else $withdrawals += (int)$r['amount'];
}
$filterText = 'Periode: '.($from !== '' ? date('d/m/Y', strtotime($from)) : 'Awal data').' - '.($to !== '' ? date('d/m/Y', strtotime($to)) : 'Hari ini');
$typeText = 'Jenis: '.($type === '' ? 'Semua transaksi' : ($type === 'topup' ? 'Setoran / Top up' : 'Penarikan'));
foreach ($pages as $pageNo => $pageRows) {
    $c = '';
    // Header banner.
    $c .= pdfRect(0, 474, 842, 121, '0.035 0.25 0.18');
    $c .= pdfRect(0, 474, 9, 121, '0.16 0.72 0.48');
    $c .= pdfLine('TABUNGAN SANTRI', 32, 562, 9, 'F2', '0.62 0.91 0.75');
    $c .= pdfLine('LAPORAN TRANSAKSI', 32, 532, 22, 'F2', '1 1 1');
    $c .= pdfLine('Ringkasan transaksi dan pergerakan saldo', 32, 512, 9, 'F1', '0.86 0.95 0.89');
    $c .= pdfLine('DICETAK  '.date('d/m/Y  H:i'), 645, 560, 8, 'F2', '1 1 1');
    $c .= pdfLine('HALAMAN  '.($pageNo+1).' / '.count($pages), 645, 542, 8, 'F1', '0.86 0.95 0.89');
    // Filter bar.
    $c .= pdfRect(30, 441, 782, 23, '0.92 0.97 0.94');
    $c .= pdfLine($filterText.'     |     '.$typeText, 42, 449, 8, 'F2', '0.12 0.37 0.26');
    // Summary cards.
    $c .= pdfRect(30, 385, 245, 43, '0.95 0.98 0.96');
    $c .= pdfRect(288, 385, 245, 43, '0.93 0.97 1');
    $c .= pdfRect(546, 385, 266, 43, '1 0.96 0.88');
    $c .= pdfLine('JUMLAH TRANSAKSI', 43, 411, 7, 'F2', '0.35 0.48 0.40');
    $c .= pdfLine(number_format(count($rows), 0, ',', '.').' transaksi', 43, 394, 13, 'F2', '0.04 0.39 0.26');
    $c .= pdfLine('TOTAL SETORAN', 301, 411, 7, 'F2', '0.30 0.43 0.63');
    $c .= pdfLine('Rp '.number_format($topups, 0, ',', '.'), 301, 394, 13, 'F2', '0.15 0.34 0.65');
    $c .= pdfLine('TOTAL PENARIKAN', 559, 411, 7, 'F2', '0.56 0.39 0.12');
    $c .= pdfLine('Rp '.number_format($withdrawals, 0, ',', '.'), 559, 394, 13, 'F2', '0.61 0.35 0.08');
    // Table header.
    $c .= pdfRect(30, 350, 782, 23, '0.055 0.34 0.25');
    $headers = [['No. Kwitansi',40], ['Tanggal',151], ['Nama Santri / NIS',245], ['Jenis',426], ['Nominal (Rp)',500], ['Saldo Akhir (Rp)',603], ['Petugas',724]];
    foreach ($headers as [$h, $x]) $c .= pdfLine($h, $x, 358, 7, 'F2', '1 1 1');
    $y = 332; $pageTotal = 0;
    foreach ($pageRows as $idx => $r) {
        if ($idx % 2 === 0) $c .= pdfRect(30, $y-5, 782, 19, '0.965 0.98 0.97');
        $amount = (int)$r['amount']; $pageTotal += $amount;
        $name = mb_substr($r['name'].' / '.$r['nis'], 0, 28);
        $values = [
            [mb_substr((string)$r['receipt_no'], 0, 18), 40],
            [date('d/m/y H:i', strtotime($r['created_at'])), 151],
            [$name, 245],
            [$r['type'] === 'topup' ? 'Setoran' : 'Penarikan', 426],
            [number_format($amount, 0, ',', '.'), 500],
            [number_format((int)$r['balance_after'], 0, ',', '.'), 603],
            [mb_substr((string)$r['username'], 0, 15), 724],
        ];
        foreach ($values as [$v, $x]) {
            $color = ($x === 426 && $r['type'] === 'topup') ? '0.04 0.48 0.31' : '0.18 0.28 0.23';
            $c .= pdfLine((string)$v, $x, $y, 7, 'F1', $color);
        }
        $y -= 19;
    }
    // Footer summary and fine print.
    $footerY = max(47, $y - 2);
    $c .= "0.7 w 30 {$footerY} m 812 {$footerY} l S\n";
    $c .= pdfLine('Transaksi pada halaman: '.count($pageRows), 32, $footerY-17, 8, 'F2', '0.20 0.35 0.27');
    $c .= pdfLine('Total nominal halaman: Rp '.number_format($pageTotal, 0, ',', '.'), 32, $footerY-32, 8, 'F2', '0.04 0.43 0.29');
    $c .= pdfLine('Total nominal seluruh laporan: Rp '.number_format($grandTotal, 0, ',', '.'), 450, $footerY-17, 8, 'F2', '0.04 0.43 0.29');
    $c .= pdfLine('Dokumen dibuat otomatis oleh sistem Tabungan Santri.', 450, $footerY-32, 7, 'F1', '0.42 0.52 0.46');
    $streams[] = $c;
}
// Fonts are assigned fixed object IDs; reserve IDs 3 and 4, then rebuild page/content references to avoid collisions.
// Rebuild with dynamically allocated font objects for a valid cross-reference table.
$objects = [];
$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
$objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
$objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
$kids = []; $next = 5;
foreach ($streams as $stream) {
    $pageId = $next++; $contentId = $next++; $kids[] = "$pageId 0 R";
    $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
    $objects[$contentId] = '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
}
$objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
ksort($objects);
$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets = [0]; $max = max(array_keys($objects));
for ($i=1; $i<=$max; $i++) { $offsets[$i] = strlen($pdf); $pdf .= $i." 0 obj\n".$objects[$i]."\nendobj\n"; }
$xref = strlen($pdf); $pdf .= "xref\n0 ".($max+1)."\n0000000000 65535 f \n";
for ($i=1; $i<=$max; $i++) $pdf .= sprintf('%010d 00000 n ', $offsets[$i])."\n";
$pdf .= "trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="laporan_transaksi_'.$stamp.'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
