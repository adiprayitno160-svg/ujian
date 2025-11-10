<?php
/**
 * Print Berita Acara - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    redirect('operator-assessment-berita-acara-generate');
}

// Get berita acara
$stmt = $pdo->prepare("SELECT ba.*, u.judul, u.tipe_asesmen, u.tahun_ajaran, u.semester, 
                       m.nama_mapel, k.nama_kelas, s.nama_sesi, u2.nama as creator_name
                       FROM berita_acara ba
                       INNER JOIN ujian u ON ba.id_ujian = u.id
                       INNER JOIN mapel m ON u.id_mapel = m.id
                       LEFT JOIN kelas k ON ba.id_kelas = k.id
                       LEFT JOIN sesi_ujian s ON ba.id_sesi = s.id
                       LEFT JOIN users u2 ON ba.created_by = u2.id
                       WHERE ba.id = ?");
$stmt->execute([$id]);
$berita_acara = $stmt->fetch();

if (!$berita_acara) {
    redirect('operator-assessment-berita-acara-generate');
}

// Get absensi detail
$stmt = $pdo->prepare("SELECT a.*, u.nama as nama_siswa, u.username, k.nama_kelas
                       FROM absensi_ujian a
                       INNER JOIN users u ON a.id_siswa = u.id
                       LEFT JOIN user_kelas uk ON u.id = uk.id_user
                       LEFT JOIN kelas k ON uk.id_kelas = k.id
                       WHERE a.id_sesi = ?
                       ORDER BY u.nama ASC");
$stmt->execute([$berita_acara['id_sesi']]);
$absensi_detail = $stmt->fetchAll();

// Parse pengawas
$pengawas = json_decode($berita_acara['pengawas'], true) ?? [];

// Get sekolah info
$stmt = $pdo->query("SELECT * FROM sekolah LIMIT 1");
$sekolah = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita Acara - <?php echo escape($berita_acara['judul']); ?></title>
    <style>
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
                padding: 20px;
            }
        }
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 11pt;
            margin: 2px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 200px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 60px;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer;">
            <i class="fas fa-print"></i> Print
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer;">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>

    <div class="header">
        <?php if ($sekolah): ?>
            <h1><?php echo escape($sekolah['nama_sekolah']); ?></h1>
            <?php if ($sekolah['alamat']): ?>
                <p><?php echo escape($sekolah['alamat']); ?></p>
            <?php endif; ?>
            <?php if ($sekolah['no_telp']): ?>
                <p>Telp: <?php echo escape($sekolah['no_telp']); ?></p>
            <?php endif; ?>
        <?php else: ?>
            <h1>Sekolah Menengah Pertama</h1>
        <?php endif; ?>
        <hr style="border: 2px solid #000; margin: 20px 0;">
        <h2>BERITA ACARA</h2>
        <h2><?php echo escape($berita_acara['judul']); ?></h2>
    </div>

    <table>
        <tr>
            <th width="200">Mata Pelajaran</th>
            <td><?php echo escape($berita_acara['nama_mapel']); ?></td>
        </tr>
        <tr>
            <th>Kelas</th>
            <td><?php echo escape($berita_acara['nama_kelas'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th>Tipe Assessment</th>
            <td><?php echo get_sumatip_badge_label($berita_acara['tipe_asesmen']); ?></td>
        </tr>
        <tr>
            <th>Tanggal</th>
            <td><?php echo format_date($berita_acara['tanggal'], 'd F Y'); ?></td>
        </tr>
        <tr>
            <th>Waktu</th>
            <td><?php echo date('H:i', strtotime($berita_acara['waktu_mulai'])); ?> - <?php echo date('H:i', strtotime($berita_acara['waktu_selesai'])); ?> WIB</td>
        </tr>
        <tr>
            <th>Pengawas</th>
            <td>
                <?php if (!empty($pengawas)): ?>
                    <?php echo implode(', ', array_map('escape', $pengawas)); ?>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <h3 style="margin-top: 30px; margin-bottom: 10px;">DATA PESERTA</h3>
    <table>
        <thead>
            <tr>
                <th width="50" class="text-center">No</th>
                <th>Nama Siswa</th>
                <th width="150">Kelas</th>
                <th width="100" class="text-center">Status</th>
                <th width="150">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($absensi_detail as $index => $absensi): ?>
            <tr>
                <td class="text-center"><?php echo $index + 1; ?></td>
                <td><?php echo escape($absensi['nama_siswa']); ?></td>
                <td><?php echo escape($absensi['nama_kelas'] ?? '-'); ?></td>
                <td class="text-center">
                    <?php 
                    $status_label = [
                        'hadir' => 'Hadir',
                        'tidak_hadir' => 'Tidak Hadir',
                        'izin' => 'Izin',
                        'sakit' => 'Sakit'
                    ];
                    echo $status_label[$absensi['status_absen']] ?? $absensi['status_absen'];
                    ?>
                </td>
                <td><?php echo $absensi['status_absen'] === 'hadir' ? '✓' : '-'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table>
        <tr>
            <th width="200">Total Peserta</th>
            <td><?php echo $berita_acara['total_peserta']; ?> orang</td>
        </tr>
        <tr>
            <th>Hadir</th>
            <td><?php echo $berita_acara['total_hadir']; ?> orang</td>
        </tr>
        <tr>
            <th>Tidak Hadir</th>
            <td><?php echo $berita_acara['total_tidak_hadir']; ?> orang</td>
        </tr>
        <tr>
            <th>Izin</th>
            <td><?php echo $berita_acara['total_izin']; ?> orang</td>
        </tr>
        <tr>
            <th>Sakit</th>
            <td><?php echo $berita_acara['total_sakit']; ?> orang</td>
        </tr>
    </table>

    <?php if ($berita_acara['catatan']): ?>
    <div style="margin-top: 20px;">
        <strong>Catatan:</strong><br>
        <?php echo nl2br(escape($berita_acara['catatan'])); ?>
    </div>
    <?php endif; ?>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                Pengawas 1<br>
                <?php echo !empty($pengawas[0]) ? escape($pengawas[0]) : '___________________'; ?>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                Pengawas 2<br>
                <?php echo !empty($pengawas[1]) ? escape($pengawas[1]) : '___________________'; ?>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                Mengetahui,<br>
                Operator<br><br><br>
                ___________________
            </div>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 10pt;">
        <p>Dicetak pada: <?php echo date('d F Y H:i:s'); ?></p>
    </div>

    <script>
        // Auto print on load
        window.onload = function() {
            // Optional: auto print
            // window.print();
        };
    </script>
</body>
</html>

