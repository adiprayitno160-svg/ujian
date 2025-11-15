<?php
/**
 * Template Raport - Laporan Hasil Belajar Tengah Semester
 * Template sesuai dengan format resmi sekolah
 */

// Template HTML
$template_html = '<div class="raport-container">
    <div class="raport-header">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 100px; vertical-align: top; padding-right: 20px;">
                    {{LOGO_RAPORT}}
                </td>
                <td style="text-align: center; vertical-align: middle;">
                    <div class="pemerintah">{{PEMERINTAH_KABUPATEN}}</div>
                    <div class="dinas">{{DINAS_PENDIDIKAN}}</div>
                    <div class="nama-sekolah">{{NAMA_SEKOLAH}}</div>
                    <div class="info-sekolah">
                        <span>NSS: {{NSS}}</span>
                        <span style="margin-left: 20px;">NPSN: {{NPSN}}</span>
                    </div>
                    <div class="alamat">{{ALAMAT_SEKOLAH}}</div>
                    <div class="alamat">Telp. {{NO_TELP_SEKOLAH}} Kode Pos {{KODE_POS}}</div>
                </td>
                <td style="width: 100px;"></td>
            </tr>
        </table>
        <div class="separator"></div>
    </div>
    
    <div class="raport-title">LAPORAN HASIL BELAJAR TENGAH SEMESTER 1</div>
    
    <div class="raport-info">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 120px;">Nama Siswa</td>
                <td style="width: 5px;">:</td>
                <td style="border-bottom: 1px solid #000;">{{NAMA_SISWA}}</td>
                <td style="width: 30px;"></td>
                <td style="width: 60px;">KELAS</td>
                <td style="width: 5px;">:</td>
                <td style="border-bottom: 1px solid #000;">{{KELAS}}</td>
            </tr>
            <tr>
                <td>Nomor Induk</td>
                <td>:</td>
                <td style="border-bottom: 1px solid #000;">{{NIS}}</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td style="text-align: right;">Tahun Pelajaran</td>
                <td>:</td>
                <td style="border-bottom: 1px solid #000;">{{TAHUN_PELAJARAN}}</td>
            </tr>
        </table>
    </div>
    
    <div style="margin-top: 20px;">
        <table class="raport-table">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>MATA PELAJARAN</th>
                    <th style="width: 100px;">NILAI</th>
                </tr>
            </thead>
            <tbody>
                {{TABEL_NILAI}}
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 30px; display: flex; justify-content: space-between; align-items: flex-start;">
        <div class="wali-kelas-info">
            <div style="margin-bottom: 40px;">
                <div>Wali Kelas</div>
                <div style="margin-top: 50px; border-top: 1px solid #000; padding-top: 5px; width: 200px;">
                    {{NAMA_WALI_KELAS}}
                </div>
                <div style="margin-top: 5px; font-size: 10pt;">
                    NIP. {{NIP_WALI_KELAS}}
                </div>
            </div>
        </div>
        <div class="keterangan" style="flex: 1; margin-left: 20px;">
            <p><strong>Keterangan:</strong></p>
            <p>0 atau - : berarti belum melengkapi tugas dan/atau belum mengikuti asesmen sumatip</p>
        </div>
    </div>
</div>';

// Template CSS
$template_css = '@media print {
    .no-print { display: none; }
    .page-break { page-break-after: always; }
    @page { 
        margin: 1.5cm;
        size: A4;
    }
}

body {
    font-family: "Times New Roman", serif;
    font-size: 12pt;
    margin: 0;
    padding: 20px;
    line-height: 1.5;
}

.raport-container {
    max-width: 21cm;
    margin: 0 auto;
    background: white;
}

.raport-header {
    margin-bottom: 25px;
}

.raport-header table {
    width: 100%;
    border-collapse: collapse;
}

.raport-header .logo-kop-surat {
    max-width: 90px;
    max-height: 90px;
    object-fit: contain;
}

.raport-header .pemerintah {
    font-size: 14pt;
    font-weight: bold;
    margin-bottom: 3px;
    letter-spacing: 0.5px;
}

.raport-header .dinas {
    font-size: 13pt;
    font-weight: bold;
    margin-bottom: 5px;
    letter-spacing: 0.5px;
}

.raport-header .nama-sekolah {
    font-size: 13pt;
    font-weight: bold;
    margin-bottom: 5px;
    text-transform: uppercase;
}

.raport-header .info-sekolah {
    font-size: 10pt;
    margin-bottom: 3px;
}

.raport-header .alamat {
    font-size: 10pt;
    margin-bottom: 2px;
}

.raport-header .separator {
    border-top: 3px solid #000;
    margin: 15px 0 10px 0;
}

.raport-title {
    text-align: center;
    font-size: 14pt;
    font-weight: bold;
    margin: 20px 0;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.raport-info {
    margin: 20px 0;
}

.raport-info table {
    width: 100%;
    border-collapse: collapse;
}

.raport-info td {
    padding: 5px 5px;
    vertical-align: top;
    font-size: 11pt;
}

.raport-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    font-size: 11pt;
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
    font-size: 11pt;
}

.raport-table td:first-child {
    text-align: center;
    width: 50px;
}

.raport-table td:last-child {
    text-align: center;
    font-weight: bold;
}

.raport-table .kelompok-label {
    background-color: #e8e8e8;
    font-weight: bold;
    padding: 5px 8px;
    text-align: left;
}

.keterangan {
    margin-top: 20px;
    font-size: 10pt;
    line-height: 1.6;
}

.keterangan p {
    margin: 5px 0;
}

.wali-kelas-info {
    font-size: 11pt;
    min-width: 200px;
}

.wali-kelas-info > div:first-child {
    font-weight: bold;
    margin-bottom: 5px;
}';

// Function to get template
function get_template_raport_laporan_hasil_belajar() {
    global $template_html, $template_css;
    return [
        'html_content' => $template_html,
        'css_content' => $template_css
    ];
}

// Return template array (for backward compatibility)
return [
    'html_content' => $template_html,
    'css_content' => $template_css
];

