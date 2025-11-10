<?php
/**
 * Auto Migration for Template Raport
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto migration untuk tabel template_raport
 * 
 * Note: Functions table_exists() and column_exists() are defined in auto_migration.php
 * This file should be included after auto_migration.php
 */

/**
 * Run template raport migration
 */
function run_template_raport_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists') || !function_exists('column_exists')) {
        error_log("Template raport migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Table: template_raport
        if (!table_exists('template_raport')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS template_raport (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama_template VARCHAR(255) NOT NULL,
                html_content LONGTEXT NOT NULL COMMENT 'HTML content untuk template',
                css_content TEXT DEFAULT NULL COMMENT 'CSS styling untuk template',
                is_active TINYINT(1) DEFAULT 1,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Insert default template
            $default_html = '<div class="raport-container">
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
</div>';
            
            $default_css = '@media print {
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
}';
            
            $stmt = $pdo->prepare("INSERT INTO template_raport (nama_template, html_content, css_content, is_active, created_by) VALUES (?, ?, ?, 1, ?)");
            $stmt->execute(['Template Default', $default_html, $default_css, 1]); // User ID 1 as default
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Template raport migration error: " . $e->getMessage());
        return false;
    }
}

// Migration will be called from database.php after connection is established
// Don't auto-run here to avoid issues

