<?php
require 'config.php';
permission('my_balance');
if (user()['role'] !== 'admin') { http_response_code(403); exit('Admin saja.'); }
$search = trim((string)($_GET['q'] ?? ''));
$sql = 'SELECT id, nis, name, class_name, balance FROM students'; $args = [];
if ($search !== '') { $sql .= ' WHERE name LIKE ? OR nis LIKE ? OR class_name LIKE ?'; $like = '%'.$search.'%'; $args = [$like,$like,$like]; }
$sql .= ' ORDER BY class_name ASC, name ASC';
$stmt = db()->prepare($sql); $stmt->execute($args); $rows = $stmt->fetchAll();
function balancePdfText(string $value): string { $value = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value) ?: ''; return str_replace(["\\",'(',')',"\r","\n"],["\\\\",'\\(','\\)',' ',' '],$value); }
function balancePdfLine(string $text,int $x,int $y,int $size=8,string $font='F1',string $color='0.16 0.28 0.22'): string { return $color." rg BT /{$font} {$size} Tf {$x} {$y} Td (".balancePdfText($text).") Tj ET\n"; }
function balancePdfRect(float $x,float $y,float $w,float $h,string $color): string { return "q {$color} rg {$x} {$y} {$w} {$h} re f Q\n"; }
$pages = array_chunk($rows, 20); if (!$pages) $pages = [[]];
$totalBalance = array_sum(array_map(static fn($r)=>(int)$r['balance'],$rows));
$stamp = date('Ymd_His'); $streams=[];
foreach ($pages as $pageNo=>$pageRows) {
    $c='';
    $c .= balancePdfRect(0,474,842,121,'0.035 0.25 0.18');
    $c .= balancePdfRect(0,474,9,121,'0.16 0.72 0.48');
    $c .= balancePdfLine('TABUNGAN SANTRI',32,562,9,'F2','0.62 0.91 0.75');
    $c .= balancePdfLine('REKAP SALDO SANTRI',32,532,22,'F2','1 1 1');
    $c .= balancePdfLine('Ringkasan saldo terkini seluruh santri',32,512,9,'F1','0.86 0.95 0.89');
    $c .= balancePdfLine('DICETAK  '.date('d/m/Y  H:i'),645,560,8,'F2','1 1 1');
    $c .= balancePdfLine('HALAMAN  '.($pageNo+1).' / '.count($pages),645,542,8,'F1','0.86 0.95 0.89');
    $c .= balancePdfRect(30,438,782,24,'0.92 0.97 0.94');
    $c .= balancePdfLine('Pencarian: '.($search !== '' ? mb_substr($search,0,70) : 'Semua santri'),42,446,8,'F2','0.12 0.37 0.26');
    $c .= balancePdfRect(30,382,245,43,'0.95 0.98 0.96');
    $c .= balancePdfRect(288,382,245,43,'0.93 0.97 1');
    $c .= balancePdfRect(546,382,266,43,'1 0.96 0.88');
    $c .= balancePdfLine('JUMLAH SANTRI',43,408,7,'F2','0.35 0.48 0.40');
    $c .= balancePdfLine(number_format(count($rows),0,',','.').' santri',43,391,13,'F2','0.04 0.39 0.26');
    $c .= balancePdfLine('TOTAL SALDO',301,408,7,'F2','0.30 0.43 0.63');
    $c .= balancePdfLine('Rp '.number_format($totalBalance,0,',','.'),301,391,13,'F2','0.15 0.34 0.65');
    $c .= balancePdfLine('DATA LAPORAN',559,408,7,'F2','0.56 0.39 0.12');
    $c .= balancePdfLine($search !== '' ? 'Hasil pencarian' : 'Seluruh santri',559,391,12,'F2','0.61 0.35 0.08');
    $c .= balancePdfRect(30,350,782,23,'0.055 0.34 0.25');
    foreach ([['No.',40],['Nama Santri',78],['NIS',350],['Kelas',455],['Saldo (Rp)',620]] as [$h,$x]) $c .= balancePdfLine($h,$x,358,8,'F2','1 1 1');
    $y=332;
    foreach ($pageRows as $idx=>$r) {
        if ($idx%2===0) $c .= balancePdfRect(30,$y-5,782,19,'0.965 0.98 0.97');
        $vals=[[ (string)(($pageNo*20)+$idx+1),40],[mb_substr((string)$r['name'],0,42),78],[mb_substr((string)$r['nis'],0,20),350],[mb_substr((string)$r['class_name'],0,25),455],[number_format((int)$r['balance'],0,',','.'),620]];
        foreach ($vals as [$v,$x]) $c .= balancePdfLine((string)$v,$x,$y,8,'F1','0.18 0.28 0.23');
        $y-=14;
    }
    $footerY=max(47,$y-2); $c .= "0.7 w 30 {$footerY} m 812 {$footerY} l S\n";
    $c .= balancePdfLine('Jumlah data pada halaman: '.count($pageRows),32,$footerY-17,8,'F2','0.20 0.35 0.27');
    $c .= balancePdfLine('Total saldo seluruh hasil: Rp '.number_format($totalBalance,0,',','.'),32,$footerY-32,8,'F2','0.04 0.43 0.29');
    $c .= balancePdfLine('Dokumen dibuat otomatis oleh sistem Tabungan Santri.',500,$footerY-32,7,'F1','0.42 0.52 0.46');
    $streams[]=$c;
}
$objects=[]; $objects[1]='<< /Type /Catalog /Pages 2 0 R >>'; $objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'; $objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
$kids=[];$next=5; foreach($streams as $stream){$pageId=$next++;$contentId=$next++;$kids[]="$pageId 0 R";$objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';$objects[$contentId]='<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";}
$objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>'; ksort($objects); $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];$max=max(array_keys($objects));for($i=1;$i<=$max;$i++){$offsets[$i]=strlen($pdf);$pdf.=$i." 0 obj\n".$objects[$i]."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="rekap_saldo_santri_'.$stamp.'.pdf"'); header('Content-Length: '.strlen($pdf)); echo $pdf; exit;
