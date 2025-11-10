<?php
/**
 * Version Check & Update Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

// GitHub repository info
$GITHUB_REPO = 'adiprayitno160-svg/ujian';
$GITHUB_API_URL = 'https://api.github.com/repos/' . $GITHUB_REPO;

/**
 * Compare two version strings (semantic versioning)
 * Returns: -1 if $version1 < $version2, 0 if equal, 1 if $version1 > $version2
 */
function compare_versions($version1, $version2) {
    // Remove 'v' prefix if present
    $version1 = ltrim($version1, 'vV');
    $version2 = ltrim($version2, 'vV');
    
    // Split version into parts
    $parts1 = array_map('intval', explode('.', $version1));
    $parts2 = array_map('intval', explode('.', $version2));
    
    // Pad arrays to same length
    $max_length = max(count($parts1), count($parts2));
    $parts1 = array_pad($parts1, $max_length, 0);
    $parts2 = array_pad($parts2, $max_length, 0);
    
    // Compare each part
    for ($i = 0; $i < $max_length; $i++) {
        if ($parts1[$i] < $parts2[$i]) {
            return -1;
        } elseif ($parts1[$i] > $parts2[$i]) {
            return 1;
        }
    }
    
    return 0;
}

/**
 * Get current application version
 */
function get_current_version() {
    global $pdo;
    
    // Try to get from database first
    try {
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT version FROM system_version WHERE is_current = 1 ORDER BY release_date DESC LIMIT 1");
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($version && !empty($version['version'])) {
                return $version['version'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting version from database: " . $e->getMessage());
    }
    
    // Fallback to config
    if (defined('APP_VERSION')) {
        return APP_VERSION;
    }
    
    // Fallback to Git tag (only if exec() is available)
    if (is_exec_available()) {
        try {
            $repo_path = dirname(__DIR__);
            if (is_dir($repo_path . '/.git')) {
                $old_dir = getcwd();
                @chdir($repo_path);
                
                $tag_output = [];
                $tag_return = 0;
                @exec('git describe --tags --abbrev=0 2>&1', $tag_output, $tag_return);
                $current_tag = ($tag_return === 0 && !empty($tag_output)) ? trim($tag_output[0]) : null;
                
                @chdir($old_dir);
                
                if ($current_tag) {
                    return ltrim($current_tag, 'vV');
                }
            }
        } catch (Exception $e) {
            error_log("Error getting version from Git: " . $e->getMessage());
        }
    }
    
    return '1.0.0'; // Default fallback
}

/**
 * Check if exec() function is available
 */
function is_exec_available() {
    // Check if exec is in disabled_functions
    $disabled = ini_get('disable_functions');
    if ($disabled) {
        $disabled = explode(',', $disabled);
        $disabled = array_map('trim', $disabled);
        if (in_array('exec', $disabled)) {
            return false;
        }
    }
    
    // Check if function exists and is callable
    return function_exists('exec') && is_callable('exec');
}

/**
 * Get version from Git tags (fallback when GitHub Releases API is not available)
 * Fast version - doesn't fetch from remote to avoid timeout
 * Returns null if exec() is not available
 */
function get_version_from_git_tags($fast_mode = true) {
    // Check if exec() is available
    if (!is_exec_available()) {
        error_log("exec() function is not available - skipping Git tags check");
        return null;
    }
    
    try {
        $repo_path = dirname(__DIR__);
        if (!is_dir($repo_path . '/.git')) {
            return null;
        }
        
        $old_dir = getcwd();
        @chdir($repo_path);
        
        // Fast mode: only check local tags (no remote fetch)
        if ($fast_mode) {
            // Get latest tag from local repository only
            $tag_output = [];
            $tag_return = 0;
            @exec('git describe --tags --abbrev=0 2>&1', $tag_output, $tag_return);
            
            @chdir($old_dir);
            
            if ($tag_return === 0 && !empty($tag_output)) {
                $tag = trim($tag_output[0]);
                // Remove 'v' prefix if present
                return ltrim($tag, 'vV');
            }
            
            // Try to get all local tags and find latest
            @chdir($repo_path);
            @exec('git tag -l 2>&1', $local_tags_output, $local_tags_return);
            @chdir($old_dir);
            
            if ($local_tags_return === 0 && !empty($local_tags_output)) {
                $tags = [];
                foreach ($local_tags_output as $tag) {
                    $tag = trim($tag);
                    if (preg_match('/^v?(\d+\.\d+\.\d+)$/', $tag, $matches)) {
                        $tags[] = $matches[1];
                    }
                }
                
                if (!empty($tags)) {
                    // Sort versions
                    usort($tags, function($a, $b) {
                        return compare_versions($a, $b);
                    });
                    return end($tags); // Return latest
                }
            }
        } else {
            // Slow mode: try to fetch from remote (can timeout)
            // Fetch tags from remote with timeout
            @exec('timeout 3 git fetch --tags origin 2>&1', $fetch_output, $fetch_return);
            // If timeout command not available, skip fetch
            
            // Get latest tag
            $tag_output = [];
            $tag_return = 0;
            @exec('git describe --tags --abbrev=0 2>&1', $tag_output, $tag_return);
            
            @chdir($old_dir);
            
            if ($tag_return === 0 && !empty($tag_output)) {
                $tag = trim($tag_output[0]);
                // Remove 'v' prefix if present
                return ltrim($tag, 'vV');
            }
            
            // Try to get from remote tags (with timeout protection)
            @chdir($repo_path);
            @exec('timeout 3 git ls-remote --tags origin 2>&1', $remote_tags_output, $remote_tags_return);
            @chdir($old_dir);
            
            if ($remote_tags_return === 0 && !empty($remote_tags_output)) {
                // Get the latest tag from remote
                $tags = [];
                foreach ($remote_tags_output as $line) {
                    if (preg_match('/refs\/tags\/(v?\d+\.\d+\.\d+)$/', $line, $matches)) {
                        $tags[] = ltrim($matches[1], 'vV');
                    }
                }
                
                if (!empty($tags)) {
                    // Sort versions
                    usort($tags, function($a, $b) {
                        return compare_versions($a, $b);
                    });
                    return end($tags); // Return latest
                }
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting version from Git tags: " . $e->getMessage());
        return null;
    }
}

/**
 * Check for latest version from GitHub Releases API
 */
function check_github_releases($use_cache = true, $cache_duration = 3600) {
    global $GITHUB_API_URL;
    
    $cache_file = dirname(__DIR__) . '/cache/github_releases.json';
    $cache_dir = dirname($cache_file);
    
    // Create cache directory if it doesn't exist
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    
    // Check cache first
    if ($use_cache && file_exists($cache_file)) {
        $cache_data = @json_decode(file_get_contents($cache_file), true);
        if ($cache_data && isset($cache_data['timestamp'])) {
            $cache_age = time() - $cache_data['timestamp'];
            if ($cache_age < $cache_duration) {
                return $cache_data['data'];
            }
        }
    }
    
    // Fetch from GitHub API with shorter timeout
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $GITHUB_API_URL . '/releases/latest',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 5, // Reduced from 10 to 5 seconds
        CURLOPT_CONNECTTIMEOUT => 3, // Reduced from 5 to 3 seconds
        CURLOPT_USERAGENT => 'UJAN-System/1.0',
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github.v3+json'
        ],
        CURLOPT_SSL_VERIFYPEER => false, // For development, set to true in production
        CURLOPT_SSL_VERIFYHOST => false, // For development
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("GitHub API curl error: " . $curl_error);
        
        // Check if it's a timeout error
        if (strpos(strtolower($curl_error), 'timeout') !== false || 
            strpos(strtolower($curl_error), 'timed out') !== false ||
            strpos(strtolower($curl_error), 'operation timed out') !== false) {
            // Try Git tags as fallback for timeout (fast mode to avoid another timeout)
            $git_tag_version = get_version_from_git_tags(true);
            if ($git_tag_version) {
                return [
                    'success' => true,
                    'latest_version' => $git_tag_version,
                    'tag_name' => 'v' . $git_tag_version,
                    'name' => 'v' . $git_tag_version,
                    'body' => '',
                    'html_url' => null,
                    'published_at' => null,
                    'source' => 'git_tags',
                    'message' => 'Version from Git tags (GitHub API timeout)',
                    'warning' => 'GitHub API timeout, menggunakan Git tags lokal sebagai fallback.'
                ];
            }
            
            // Return cached data if available, even if expired
            if (file_exists($cache_file)) {
                $cache_data = @json_decode(file_get_contents($cache_file), true);
                if ($cache_data && isset($cache_data['data'])) {
                    $cache_data['data']['warning'] = 'GitHub API timeout, menggunakan data cache.';
                    return $cache_data['data'];
                }
            }
            
            return [
                'success' => false,
                'error' => 'Timeout menghubungi GitHub API',
                'error_type' => 'timeout',
                'suggestion' => 'Cek koneksi internet atau coba lagi nanti. Sistem akan menggunakan Git tags sebagai fallback.'
            ];
        }
        
        // Return cached data if available for other errors
        if (file_exists($cache_file)) {
            $cache_data = @json_decode(file_get_contents($cache_file), true);
            if ($cache_data && isset($cache_data['data'])) {
                return $cache_data['data'];
            }
        }
        
        return [
            'success' => false,
            'error' => 'Failed to connect to GitHub API: ' . $curl_error,
            'error_type' => 'connection'
        ];
    }
    
    if ($http_code !== 200) {
        error_log("GitHub API HTTP error: " . $http_code . " - URL: " . $GITHUB_API_URL . '/releases/latest');
        
        // If 404, repository might not exist or have no releases - try to get from Git tags instead
        if ($http_code === 404) {
            // Try to get version from Git tags as fallback (fast mode) - only if exec() is available
            if (is_exec_available()) {
                $git_tag_version = get_version_from_git_tags(true);
                if ($git_tag_version) {
                    return [
                        'success' => true,
                        'latest_version' => $git_tag_version,
                        'tag_name' => 'v' . $git_tag_version,
                        'name' => 'v' . $git_tag_version,
                        'body' => '',
                        'html_url' => null,
                        'published_at' => null,
                        'source' => 'git_tags',
                        'message' => 'Version from Git tags (GitHub Releases not available)'
                    ];
                }
            }
            
            // Return cached data if available
            if (file_exists($cache_file)) {
                $cache_data = @json_decode(file_get_contents($cache_file), true);
                if ($cache_data && isset($cache_data['data'])) {
                    return $cache_data['data'];
                }
            }
            
            return [
                'success' => false,
                'error' => 'Repository tidak ditemukan atau belum ada release. Pastikan repository GitHub sudah dibuat dan memiliki release.',
                'error_code' => $http_code,
                'error_type' => 'not_found',
                'suggestion' => 'Gunakan fitur Pull dari GitHub untuk update manual, atau buat release di GitHub terlebih dahulu.'
            ];
        }
        
        // Return cached data if available for other errors
        if (file_exists($cache_file)) {
            $cache_data = @json_decode(file_get_contents($cache_file), true);
            if ($cache_data && isset($cache_data['data'])) {
                $cache_data['data']['warning'] = 'GitHub API error, menggunakan data cache.';
                return $cache_data['data'];
            }
        }
        
        return [
            'success' => false,
            'error' => 'GitHub API returned HTTP ' . $http_code . '. Pastikan repository GitHub tersedia dan dapat diakses.',
            'error_code' => $http_code,
            'error_type' => 'http_error'
        ];
    }
    
    $data = json_decode($response, true);
    if (!$data) {
        // Try to get from Git tags as fallback (fast mode) - only if exec() is available
        if (is_exec_available()) {
            $git_tag_version = get_version_from_git_tags(true);
            if ($git_tag_version) {
                return [
                    'success' => true,
                    'latest_version' => $git_tag_version,
                    'tag_name' => 'v' . $git_tag_version,
                    'name' => 'v' . $git_tag_version,
                    'body' => '',
                    'html_url' => null,
                    'published_at' => null,
                    'source' => 'git_tags',
                    'message' => 'Version from Git tags (GitHub API response invalid)'
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => 'Failed to parse GitHub API response'
        ];
    }
    
    // Extract version from tag_name
    $latest_version = isset($data['tag_name']) ? ltrim($data['tag_name'], 'vV') : null;
    
    if (!$latest_version) {
        // Try to get from Git tags as fallback (fast mode) - only if exec() is available
        if (is_exec_available()) {
            $git_tag_version = get_version_from_git_tags(true);
            if ($git_tag_version) {
                return [
                    'success' => true,
                    'latest_version' => $git_tag_version,
                    'tag_name' => 'v' . $git_tag_version,
                    'name' => 'v' . $git_tag_version,
                    'body' => '',
                    'html_url' => null,
                    'published_at' => null,
                    'source' => 'git_tags',
                    'message' => 'Version from Git tags (no version in GitHub release)'
                ];
            }
        }
    }
    
    $release_data = [
        'success' => true,
        'latest_version' => $latest_version,
        'tag_name' => $data['tag_name'] ?? null,
        'name' => $data['name'] ?? null,
        'body' => $data['body'] ?? null,
        'published_at' => $data['published_at'] ?? null,
        'html_url' => $data['html_url'] ?? null,
        'prerelease' => $data['prerelease'] ?? false,
        'draft' => $data['draft'] ?? false,
        'source' => 'github_api'
    ];
    
    // Save to cache only if from GitHub API (not from Git tags)
    if (!isset($release_data['source']) || $release_data['source'] === 'github_api') {
        @file_put_contents($cache_file, json_encode([
            'timestamp' => time(),
            'data' => $release_data
        ]));
    }
    
    return $release_data;
}

/**
 * Check if update is available
 */
function check_update_available($force_refresh = false) {
    $current_version = get_current_version();
    $latest_release = check_github_releases(!$force_refresh);
    
    // If GitHub API fails, try Git tags as fallback (only if exec() is available)
    if (!$latest_release['success']) {
        // Check if it's a timeout error - use fast mode for Git tags
        $is_timeout = isset($latest_release['error_type']) && $latest_release['error_type'] === 'timeout';
        
        // Try Git tags as fallback (fast mode to avoid another timeout) - only if exec() is available
        if (is_exec_available()) {
            $git_tag_version = get_version_from_git_tags(true);
            if ($git_tag_version) {
                $comparison = compare_versions($current_version, $git_tag_version);
                $has_update = $comparison < 0;
                
                $warning_msg = 'GitHub Releases API tidak tersedia';
                if ($is_timeout) {
                    $warning_msg = 'GitHub API timeout, menggunakan Git tags lokal sebagai fallback';
                }
                
                return [
                    'success' => true,
                    'has_update' => $has_update,
                    'current_version' => $current_version,
                    'latest_version' => $git_tag_version,
                    'tag_name' => 'v' . $git_tag_version,
                    'release_name' => 'v' . $git_tag_version,
                    'release_notes' => '',
                    'release_url' => null,
                    'published_at' => null,
                    'source' => 'git_tags',
                    'message' => $has_update ? "Update available: v{$git_tag_version} (from Git tags)" : "You are running the latest version (from Git tags)",
                    'warning' => $warning_msg . '. Versi yang ditampilkan dari Git tags lokal.'
                ];
            }
        }
        
        // If timeout and no Git tags (or exec() not available), return error with helpful message
        if ($is_timeout) {
            return [
                'success' => false,
                'has_update' => false,
                'error' => 'Timeout menghubungi GitHub API',
                'error_type' => 'timeout',
                'error_detail' => $latest_release['error_code'] ?? null,
                'suggestion' => 'Cek koneksi internet atau coba lagi nanti. Gunakan fitur "Pull dari GitHub" untuk update manual.',
                'current_version' => $current_version,
                'message' => 'Timeout saat memeriksa update. Gunakan fitur Pull dari GitHub untuk update manual.'
            ];
        }
        
        return [
            'success' => false,
            'has_update' => false,
            'error' => $latest_release['error'] ?? 'Unknown error',
            'error_type' => $latest_release['error_type'] ?? 'unknown',
            'error_detail' => $latest_release['error_code'] ?? null,
            'suggestion' => $latest_release['suggestion'] ?? null,
            'current_version' => $current_version,
            'message' => 'Tidak dapat memeriksa update. ' . ($latest_release['error'] ?? 'Unknown error')
        ];
    }
    
    $latest_version = $latest_release['latest_version'];
    
    if (!$latest_version) {
        return [
            'success' => false,
            'has_update' => false,
            'error' => 'No version found in latest release',
            'current_version' => $current_version
        ];
    }
    
    // Skip prerelease versions
    if (isset($latest_release['prerelease']) && $latest_release['prerelease']) {
        return [
            'success' => true,
            'has_update' => false,
            'current_version' => $current_version,
            'latest_version' => $latest_version,
            'message' => 'Latest release is a prerelease, skipping'
        ];
    }
    
    // Compare versions
    $comparison = compare_versions($current_version, $latest_version);
    $has_update = $comparison < 0;
    
    return [
        'success' => true,
        'has_update' => $has_update,
        'current_version' => $current_version,
        'latest_version' => $latest_version,
        'tag_name' => $latest_release['tag_name'] ?? null,
        'release_name' => $latest_release['name'] ?? $latest_release['tag_name'] ?? "v{$latest_version}",
        'release_notes' => $latest_release['body'] ?? '',
        'release_url' => $latest_release['html_url'] ?? null,
        'published_at' => $latest_release['published_at'] ?? null,
        'source' => $latest_release['source'] ?? 'unknown',
        'message' => $has_update ? "Update available: v{$latest_version}" : "You are running the latest version"
    ];
}

/**
 * Get all releases (for version selection)
 */
function get_all_releases($limit = 10) {
    global $GITHUB_API_URL;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $GITHUB_API_URL . '/releases?per_page=' . $limit,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'UJAN-System/1.0',
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github.v3+json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return [
            'success' => false,
            'error' => 'GitHub API returned HTTP ' . $http_code
        ];
    }
    
    $releases = json_decode($response, true);
    if (!$releases) {
        return [
            'success' => false,
            'error' => 'Failed to parse GitHub API response'
        ];
    }
    
    // Filter out prereleases and drafts, format data
    $formatted_releases = [];
    foreach ($releases as $release) {
        if (isset($release['prerelease']) && $release['prerelease']) {
            continue;
        }
        if (isset($release['draft']) && $release['draft']) {
            continue;
        }
        
        $formatted_releases[] = [
            'version' => ltrim($release['tag_name'] ?? '', 'vV'),
            'tag_name' => $release['tag_name'] ?? null,
            'name' => $release['name'] ?? $release['tag_name'] ?? null,
            'body' => $release['body'] ?? '',
            'published_at' => $release['published_at'] ?? null,
            'html_url' => $release['html_url'] ?? null,
        ];
    }
    
    return [
        'success' => true,
        'releases' => $formatted_releases
    ];
}

/**
 * Create a new GitHub release
 * @param string $version Version number (e.g., '1.0.8')
 * @param string $release_name Release name (optional, defaults to 'Release v{version}')
 * @param string $release_body Release notes/description (optional)
 * @param bool $draft Whether to create as draft (default: false)
 * @param bool $prerelease Whether to mark as prerelease (default: false)
 * @param string $github_token GitHub personal access token (optional, can be from env or config)
 * @return array Result array with success status and message
 */
function create_github_release($version, $release_name = null, $release_body = '', $draft = false, $prerelease = false, $github_token = '') {
    global $GITHUB_API_URL, $GITHUB_REPO;
    
    // Get GitHub token from various sources
    if (empty($github_token)) {
        // Try environment variable
        $github_token = getenv('GITHUB_TOKEN');
        
        // Try config file
        if (empty($github_token)) {
            $token_file = __DIR__ . '/../config/github_token.php';
            if (file_exists($token_file)) {
                $token_config = @include $token_file;
                if (isset($token_config['token'])) {
                    $github_token = $token_config['token'];
                }
            }
        }
    }
    
    if (empty($github_token)) {
        return [
            'success' => false,
            'error' => 'GitHub token tidak ditemukan. Silakan berikan GitHub personal access token dengan permission repo.',
            'suggestion' => 'Buat token di https://github.com/settings/tokens dengan permission: repo',
            'manual_steps' => [
                'Buka https://github.com/settings/tokens',
                'Buat token baru dengan permission: repo',
                'Simpan token di environment variable GITHUB_TOKEN atau di config/github_token.php',
                'Atau berikan token langsung saat membuat release'
            ]
        ];
    }
    
    // Validate version format
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        return [
            'success' => false,
            'error' => 'Format versi tidak valid. Gunakan format X.Y.Z (contoh: 1.0.8)'
        ];
    }
    
    // Set default release name
    if (empty($release_name)) {
        $release_name = 'Release v' . $version;
    }
    
    // Set default release body
    if (empty($release_body)) {
        $release_body = "Release v{$version}\n\nPerbaikan dan peningkatan fitur.";
    }
    
    // Prepare API request
    $tag_name = 'v' . $version;
    $api_url = $GITHUB_API_URL . '/releases';
    
    $data = [
        'tag_name' => $tag_name,
        'name' => $release_name,
        'body' => $release_body,
        'draft' => $draft,
        'prerelease' => $prerelease
    ];
    
    // Make API request
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github.v3+json',
            'Authorization: token ' . $github_token,
            'Content-Type: application/json',
            'User-Agent: UJAN-System/1.0'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return [
            'success' => false,
            'error' => 'Curl error: ' . $curl_error
        ];
    }
    
    $response_data = json_decode($response, true);
    
    if ($http_code === 201) {
        // Success - release created
        return [
            'success' => true,
            'message' => 'Release berhasil dibuat',
            'tag_name' => $tag_name,
            'release_name' => $release_name,
            'release_url' => $response_data['html_url'] ?? null,
            'release_id' => $response_data['id'] ?? null
        ];
    } elseif ($http_code === 422) {
        // Validation error - might be duplicate tag
        $error_message = 'Gagal membuat release';
        if (isset($response_data['errors'])) {
            foreach ($response_data['errors'] as $error) {
                if (isset($error['message'])) {
                    $error_message .= ': ' . $error['message'];
                }
            }
        }
        
        if (isset($response_data['message'])) {
            if (strpos($response_data['message'], 'already exists') !== false) {
                return [
                    'success' => false,
                    'error' => 'Release dengan tag ' . $tag_name . ' sudah ada',
                    'suggestion' => 'Gunakan versi yang berbeda atau hapus release lama terlebih dahulu',
                    'manual_steps' => [
                        'Buka https://github.com/' . $GITHUB_REPO . '/releases',
                        'Hapus release lama dengan tag ' . $tag_name . ' jika diperlukan',
                        'Atau gunakan versi yang berbeda'
                    ]
                ];
            }
            $error_message = $response_data['message'];
        }
        
        return [
            'success' => false,
            'error' => $error_message,
            'response' => $response_data
        ];
    } elseif ($http_code === 401) {
        return [
            'success' => false,
            'error' => 'Unauthorized: GitHub token tidak valid atau tidak memiliki permission',
            'suggestion' => 'Pastikan token memiliki permission: repo'
        ];
    } else {
        $error_message = 'Gagal membuat release: HTTP ' . $http_code;
        if (isset($response_data['message'])) {
            $error_message .= ' - ' . $response_data['message'];
        }
        
        return [
            'success' => false,
            'error' => $error_message,
            'http_code' => $http_code,
            'response' => $response_data
        ];
    }
}
