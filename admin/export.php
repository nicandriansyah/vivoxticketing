<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
require_once __DIR__ . '/helpers.php';

if (!$pdo) {
    http_response_code(500);
    exit('Database tidak tersedia.');
}

$q      = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';

$conds  = [];
$params = [];
list($sc, $sp) = buildSearchClause($q);
if ($sc) { $conds[] = $sc; $params = array_merge($params, $sp); }
if ($filter === 'email_fail') { $conds[] = 'email_sent = 0'; }
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$stmt = $pdo->prepare("SELECT * FROM registrations $where ORDER BY id DESC");
$stmt->execute($params);

/* ---------- Susun data baris ---------- */
$headers = ['ID', 'Kode Tiket', 'Nama', 'No WhatsApp', 'Email',
            'Jumlah Tiket', 'Tanggal', 'Upload Arwah', 'Email Terkirim'];
$rows = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Satu baris per kode tiket — kolom lain diulang
    foreach (deriveAllCodes($r['kode_tiket'], (int)$r['jumlah_tiket']) as $code) {
        $rows[] = [
            (int)$r['id'],
            $code,
            $r['nama'],
            phoneDisplay($r['no_hp']),
            $r['email'],
            (int)$r['jumlah_tiket'],
            $r['created_at'],
            $r['upload_arwah'] ? 'Ya' : 'Tidak',
            $r['email_sent'] ? 'Ya' : 'Tidak',
        ];
    }
}

/* ---------- Generator XLSX minimal (tanpa ekstensi zip) ---------- */

function xmlEsc($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Baris worksheet: angka sebagai number, selainnya inline string. */
function sheetRow(int $rowNum, array $cells, bool $bold = false): string {
    $xml = '<row r="' . $rowNum . '">';
    foreach ($cells as $i => $v) {
        $col = chr(65 + $i) . $rowNum;   // kolom A..I (maks 26 kolom, cukup)
        $s   = $bold ? ' s="1"' : '';
        if (is_int($v) || is_float($v)) {
            $xml .= '<c r="' . $col . '"' . $s . '><v>' . $v . '</v></c>';
        } else {
            $xml .= '<c r="' . $col . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . xmlEsc($v) . '</t></is></c>';
        }
    }
    return $xml . '</row>';
}

/** Bangun arsip ZIP (metode stored) murni PHP — tak butuh ext-zip. */
function buildZip(array $files): string {
    $local = ''; $central = ''; $offset = 0;
    foreach ($files as $name => $data) {
        $crc  = crc32($data);
        $size = strlen($data);
        $nlen = strlen($name);
        $hdr  = "\x50\x4b\x03\x04" . pack('vvvvvVVVvv', 20, 0, 0, 0, 0, $crc, $size, $size, $nlen, 0) . $name;
        $local   .= $hdr . $data;
        $central .= "\x50\x4b\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nlen, 0, 0, 0, 0, 32, $offset) . $name;
        $offset  += strlen($hdr) + $size;
    }
    $count = count($files);
    return $local . $central . "\x50\x4b\x05\x06" . pack('vvvvVVv', 0, 0, $count, $count, strlen($central), $offset, 0);
}

$sheetRows = sheetRow(1, $headers, true);
foreach ($rows as $i => $r) $sheetRows .= sheetRow($i + 2, $r);

$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<cols>'
    . '<col min="1" max="1" width="6" customWidth="1"/>'
    . '<col min="2" max="2" width="18" customWidth="1"/>'
    . '<col min="3" max="3" width="28" customWidth="1"/>'
    . '<col min="4" max="4" width="18" customWidth="1"/>'
    . '<col min="5" max="5" width="30" customWidth="1"/>'
    . '<col min="6" max="6" width="12" customWidth="1"/>'
    . '<col min="7" max="7" width="20" customWidth="1"/>'
    . '<col min="8" max="9" width="14" customWidth="1"/>'
    . '</cols>'
    . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
    . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
    . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
    . '<borders count="1"><border/></borders>'
    . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
    . '<cellXfs count="2"><xf/><xf fontId="1" applyFont="1"/></cellXfs>'
    . '</styleSheet>';

$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
    . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="Registrasi" sheetId="1" r:id="rId1"/></sheets></workbook>';

$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '</Types>';

$xlsx = buildZip([
    '[Content_Types].xml'        => $contentTypes,
    '_rels/.rels'                => $rootRels,
    'xl/workbook.xml'            => $workbook,
    'xl/_rels/workbook.xml.rels' => $wbRels,
    'xl/styles.xml'              => $styles,
    'xl/worksheets/sheet1.xml'   => $sheet,
]);

$filename = 'registrasi-foas14-' . date('Ymd-His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($xlsx));
echo $xlsx;
exit;
