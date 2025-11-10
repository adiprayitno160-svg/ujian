<?php
/**
 * Export Raport to PDF - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Export raport siswa ke PDF
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

global $pdo;

$tahun_ajaran = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester = $_GET['semester'] ?? 'ganjil';

// Get siswa data
$stmt = $pdo->prepare("SELECT u.*, k.nama_kelas, k.tingkat
                      FROM users u
                      INNER JOIN user_kelas uk ON u.id = uk.id_user
                      INNER JOIN kelas k ON uk.id_kelas = k.id
                      WHERE u.id = ? 
                      AND uk.tahun_ajaran = ? 
                      AND uk.semester = ?");
$stmt->execute([$_SESSION['user_id'], $tahun_ajaran, $semester]);
$siswa = $stmt->fetch();

if (!$siswa) {
    redirect('siswa/raport/list.php');
}

// Get penilaian
$stmt = $pdo->prepare("SELECT pm.*, m.nama_mapel, m.kode_mapel
                      FROM penilaian_manual pm
                      INNER JOIN mapel m ON pm.id_mapel = m.id
                      WHERE pm.id_siswa = ?
                      AND pm.tahun_ajaran = ?
                      AND pm.semester = ?
                      AND pm.status = 'approved'
                      ORDER BY m.nama_mapel ASC");
$stmt->execute([$_SESSION['user_id'], $tahun_ajaran, $semester]);
$penilaian_list = $stmt->fetchAll();

// Get sekolah data
$stmt = $pdo->query("SELECT * FROM sekolah LIMIT 1");
$sekolah = $stmt->fetch();

// Get active template
$stmt = $pdo->query("SELECT * FROM template_raport WHERE is_active = 1 LIMIT 1");
$template = $stmt->fetch();

if (!$template) {
    // Use default template if no template found
    $template = [
        'html_content' => '<div class="raport-container">
    <div class="raport-header">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 100px; vertical-align: top; padding-right: 20px;">
                    {{LOGO_SEKOLAH}}
                </td>
                <td style="text-align: center; vertical-align: middle;">
                    <div class="pemerintah">PEMERINTAH KABUPATEN TULUNGAGUNG</div>
                    <div class="dinas">DINAS PENDIDIKAN</div>
                    <div class="nama-sekolah">{{NAMA_SEKOLAH}}</div>
                    <div class="alamat">{{ALAMAT_SEKOLAH}}</div>
                    <div class="alamat">Telp: {{NO_TELP_SEKOLAH}}</div>
                </td>
                <td style="width: 100px;"></td>
            </tr>
        </table>
        <div class="separator"></div>
    </div>
    <div class="raport-title">RAPORT TENGAH SEMESTER</div>
    <div class="raport-info">
        <table>
            <tr>
                <td class="label">Nama Siswa</td>
                <td>: {{NAMA_SISWA}}</td>
                <td class="label">Kelas</td>
                <td>: {{KELAS}}</td>
            </tr>
            <tr>
                <td class="label">NIS</td>
                <td>: {{NIS}}</td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </div>
    <div style="border-top: 1px solid #000; margin: 20px 0;"></div>
    <table class="raport-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Mata Pelajaran</th>
                <th>Nilai</th>
            </tr>
        </thead>
        <tbody>
            {{TABEL_NILAI}}
        </tbody>
    </table>
</div>',
        'css_content' => '@media print {
    .no-print { display: none; }
    .page-break { page-break-after: always; }
    @page { margin: 1.5cm; }
}
body {
    font-family: "Times New Roman", serif;
    font-size: 12pt;
    margin: 0;
    padding: 20px;
}
.raport-container {
    max-width: 21cm;
    margin: 0 auto;
}
.raport-header {
    margin-bottom: 30px;
}
.raport-header table {
    width: 100%;
    border-collapse: collapse;
}
.raport-header .logo-sekolah {
    max-width: 80px;
    max-height: 80px;
    object-fit: contain;
}
.raport-header .pemerintah {
    font-size: 14pt;
    font-weight: bold;
    margin-bottom: 5px;
}
.raport-header .dinas {
    font-size: 13pt;
    font-weight: bold;
    margin-bottom: 5px;
}
.raport-header .nama-sekolah {
    font-size: 13pt;
    font-weight: bold;
    margin-bottom: 5px;
}
.raport-header .alamat {
    font-size: 11pt;
    margin-bottom: 2px;
}
.raport-header .separator {
    border-top: 3px solid #000;
    margin: 20px 0;
}
.raport-title {
    text-align: center;
    font-size: 14pt;
    font-weight: bold;
    margin: 20px 0;
    text-transform: uppercase;
}
.raport-info table {
    width: 100%;
    border-collapse: collapse;
}
.raport-info td {
    padding: 5px 10px;
    vertical-align: top;
}
.raport-info .label {
    width: 150px;
}
.raport-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.raport-table th,
.raport-table td {
    border: 1px solid #000;
    padding: 8px;
    text-align: left;
}
.raport-table th {
    background-color: #f0f0f0;
    font-weight: bold;
    text-align: center;
}
.raport-table td:first-child {
    text-align: center;
    width: 50px;
}
.raport-table td:last-child {
    text-align: center;
}
.logo-sekolah {
    max-width: 80px;
    max-height: 80px;
    object-fit: contain;
}'
    ];
}

// Build tabel nilai
$tabel_nilai = '';
if (empty($penilaian_list)) {
    $tabel_nilai = '<tr><td colspan="3" style="text-align: center;">Belum ada data penilaian</td></tr>';
} else {
    foreach ($penilaian_list as $index => $penilaian) {
        $tabel_nilai .= '<tr>';
        $tabel_nilai .= '<td>' . ($index + 1) . '</td>';
        $tabel_nilai .= '<td>' . escape($penilaian['nama_mapel']) . '</td>';
        $tabel_nilai .= '<td>' . ($penilaian['nilai_akhir'] !== null ? number_format($penilaian['nilai_akhir'], 2) : '-') . '</td>';
        $tabel_nilai .= '</tr>';
    }
}

// Get logo HTML
$logo_html = '';
if ($sekolah && !empty($sekolah['logo']) && file_exists(UPLOAD_PROFILE . '/' . $sekolah['logo'])) {
    $logo_url = asset_url('uploads/profile/' . $sekolah['logo']);
    $logo_html = '<img src="' . escape($logo_url) . '" alt="Logo Sekolah" class="logo-sekolah" />';
} else {
    $logo_html = ''; // Empty if no logo
}

// Replace variables in template
$html_content = $template['html_content'];
$html_content = str_replace('{{LOGO_SEKOLAH}}', $logo_html, $html_content);
$html_content = str_replace('{{NAMA_SEKOLAH}}', escape($sekolah['nama_sekolah'] ?? 'NAMA SEKOLAH'), $html_content);
$html_content = str_replace('{{ALAMAT_SEKOLAH}}', escape($sekolah['alamat'] ?? ''), $html_content);
$html_content = str_replace('{{NO_TELP_SEKOLAH}}', escape($sekolah['no_telp'] ?? ''), $html_content);
$html_content = str_replace('{{NAMA_SISWA}}', escape($siswa['nama']), $html_content);
$html_content = str_replace('{{NIS}}', escape($siswa['username']), $html_content);
$html_content = str_replace('{{KELAS}}', escape($siswa['nama_kelas']), $html_content);
$html_content = str_replace('{{TABEL_NILAI}}', $tabel_nilai, $html_content);

// Output PDF using browser print
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport - <?php echo escape($siswa['nama']); ?></title>
    <style>
        <?php echo $template['css_content']; ?>
        @media print {
            body { margin: 0; padding: 0; }
        }
        .logo-sekolah {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
        }
    </style>
    <script>
        // Auto print when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</head>
<body>
    <?php echo $html_content; ?>
</body>
</html>

