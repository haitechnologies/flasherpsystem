<?php

use App\Core\DB;
include('admin_elements/admin_header.php');

// Use existing module slug to avoid permission lookup warnings.
$module = 'sitemap';
$module_caption = 'Sitemap Status';
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$projectRoot = realpath(__DIR__ . '/..');
$pagesRoot = $projectRoot . DIRECTORY_SEPARATOR . 'pages';

$masterSitemapFilename = trim((string)getSystemSetting('master_sitemap_filename', 'sitemap.xml'));
if (!preg_match('/^[a-z0-9][a-z0-9._-]*\.xml$/i', $masterSitemapFilename)) {
    $masterSitemapFilename = 'sitemap.xml';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_master_sitemap_filename') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh and try again.';
    } else {
        $submittedFilename = strtolower(trim((string)($_POST['master_sitemap_filename'] ?? '')));

        if ($submittedFilename === '') {
            $error_message = 'Master sitemap file name is required.';
        } elseif (!preg_match('/^[a-z0-9][a-z0-9._-]*\.xml$/', $submittedFilename)) {
            $error_message = 'Use a valid XML filename like sitemap.xml (letters, numbers, dash, underscore, dot only).';
        } elseif (mb_strlen($submittedFilename) > 120) {
            $error_message = 'Master sitemap file name must be 120 characters or less.';
        } else {
            $escapedFilename = $mysqli->real_escape_string($submittedFilename);
            $settingSlug = 'master_sitemap_filename';
            $escapedSlug = $mysqli->real_escape_string($settingSlug);

            $existsResult = $mysqli->query("SELECT id FROM `" . DB::SYSTEM_SETTINGS . "` WHERE setting_slug='" . $escapedSlug . "' LIMIT 1");
            $settingExists = ($existsResult && $existsResult->num_rows > 0);

            if ($settingExists) {
                $saveResult = $mysqli->query("UPDATE `" . DB::SYSTEM_SETTINGS . "` SET setting_value='" . $escapedFilename . "', updated_at=NOW(), updated_by='" . (int)Session::userId() . "' WHERE setting_slug='" . $escapedSlug . "'");
            } else {
                $saveResult = $mysqli->query("INSERT INTO `" . DB::SYSTEM_SETTINGS . "` (setting_slug, setting_name, setting_value, hint, is_active, created_by, updated_by, created_at, updated_at) VALUES ('" . $escapedSlug . "', 'Master Sitemap Filename', '" . $escapedFilename . "', 'Custom filename used for the primary sitemap endpoint', 1, '" . (int)Session::userId() . "', '" . (int)Session::userId() . "', NOW(), NOW())");
            }

            if ($saveResult) {
                $masterSitemapFilename = $submittedFilename;
                $success_message = 'Master sitemap XML filename updated successfully.';
            } else {
                $error_message = 'Failed to update master sitemap filename. Please try again.';
            }
        }
    }
}

// Get base URL for correct path generation in local and live environments
$baseUrl = '../'; // Relative to dashboard/

// Global SEO/sitemap configuration (source: global_settings.php > SEO tab)
$sitemapConfig = [
    'sitemap_enabled' => (int)getSystemSetting('sitemap_enabled', 1),
    'ai_sitemap_enabled' => (int)getSystemSetting('ai_sitemap_enabled', 1),
    // Decommissioned: sitemap_companies & sitemap_blogs
    'sitemap_categories' => (int)getSystemSetting('sitemap_categories', 1),
    'sitemap_hs_codes' => (int)getSystemSetting('sitemap_hs_codes', 1),
    'sitemap_amp' => (int)getSystemSetting('sitemap_amp', 0),
    'seo_ai_policy_mode' => (string)getSystemSetting('seo_ai_policy_mode', 'inherit'),
];

$settingsPageUrl = 'global_settings.php?tab=seo';

$sitemapFiles = [
    [
        'name' => 'Main Sitemap',
        'url_path' => $masterSitemapFilename,
        'handler_file' => 'sitemap.php',
        'type' => 'XML',
        'setting_key' => 'sitemap_enabled'
    ],
    [
        'name' => 'Sitemap Index',
        'url_path' => 'sitemap_index.xml',
        'handler_file' => 'sitemap-index.php',
        'type' => 'XML',
        'setting_key' => 'sitemap_enabled'
    ],
    [
        'name' => 'HS Codes Sitemap',
        'url_path' => 'sitemap-hs-codes.xml',
        'handler_file' => 'sitemap-hs-codes.php',
        'type' => 'XML',
        'setting_key' => 'sitemap_hs_codes'
    ],
    [
        'name' => 'Static Pages Sitemap',
        'url_path' => 'sitemap-static.xml',
        'handler_file' => 'sitemap-static.php',
        'type' => 'XML',
        'setting_key' => 'sitemap_enabled'
    ],
    [
        'name' => 'AI Sitemap',
        'url_path' => 'ai-sitemap.xml',
        'handler_file' => 'ai-sitemap.php',
        'type' => 'XML',
        'setting_key' => 'ai_sitemap_enabled'
    ],
    // Decommissioned: Companies & Blog sitemaps
    [
        'name' => 'Categories Sitemap',
        'url_path' => 'sitemap-categories.xml',
        'handler_file' => 'sitemap-categories.php',
        'type' => 'XML',
        'setting_key' => 'sitemap_categories'
    ],
    [
        'name' => 'AMP Pages Sitemap',
        'url_path' => 'sitemap-amp.xml',
        'handler_file' => 'sitemap-amp.php',
        'type' => 'XML',
        'setting_key' => 'sitemap_amp'
    ]
];

foreach ($sitemapFiles as &$file) {
    // Check if handler file exists in pages/ directory
    $absolutePath = $pagesRoot . DIRECTORY_SEPARATOR . $file['handler_file'];
    
    $exists = is_file($absolutePath);

    $file['exists'] = $exists;
    $file['enabled'] = isset($sitemapConfig[$file['setting_key']]) ? ((int)$sitemapConfig[$file['setting_key']] === 1) : false;
    $file['full_url'] = $baseUrl . $file['url_path']; // Relative URL for dashboard context
    $file['updated_at'] = $exists ? date('Y-m-d H:i:s', (int)filemtime($absolutePath)) : 'N/A';
    $file['size'] = $exists ? number_format((int)filesize($absolutePath)) . ' B' : '-';
}
unset($file);
?>

<div class="content-wrapper">
        <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                    <a href="listing_<?php echo $module; ?>.php" class="text-dark">All <?php echo ucwords(str_ireplace('_', " ", $module)); ?></a>
                </h1>
            </div>

            <div class="my-1">
                <?php if (empty($hide_add_button) && isset($module_id) && isset($module) && granted('create', $module_id)) { ?>
                    <a href="<?php echo $module; ?>.php" class="btn btn-primary btn-sm d-inline-flex align-items-center">
                        <i class="ph-plus ph-sm me-2 opacity-75"></i>New
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content">
        <?php include('admin_elements/breadcrumb.php'); ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo e($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo e($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Sitemap Endpoints Status</h6>
                <div class="d-flex gap-2">
                    <button type="button" id="runRealtimeSitemapChecks" class="btn btn-sm btn-warning">
                        <i class="ph-arrows-clockwise me-1"></i>Run Real-Time Checks
                    </button>
                    <a href="<?php echo e($settingsPageUrl); ?>" class="btn btn-sm btn-success"><i class="ph-sliders me-1"></i>SEO Settings</a>
                    <a href="../<?php echo e($masterSitemapFilename); ?>" target="_blank" class="btn btn-sm btn-primary"><i class="ph-globe me-1"></i>Open Main Sitemap</a>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="" class="row g-2 align-items-end mb-3">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_master_sitemap_filename">
                    <div class="col-lg-6 col-md-8">
                        <label for="master_sitemap_filename" class="form-label mb-1">Main Sitemap XML File</label>
                        <input type="text" class="form-control" id="master_sitemap_filename" name="master_sitemap_filename" value="<?php echo e($masterSitemapFilename); ?>" maxlength="120" placeholder="sitemap.xml">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-primary">Save Filename</button>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Changes the real endpoint path for the main sitemap, e.g. <code>/sitemap.xml</code> to <code>/my-main-sitemap.xml</code>.</small>
                    </div>
                </form>

                <div class="alert alert-info mb-3">
                    <i class="ph-info me-2"></i>
                    <strong>Note:</strong> Sitemaps are dynamically generated by PHP handlers in the <code>pages/</code> directory. They are accessible via clean URLs through the routing system. URLs are relative to work in both local and production environments.
                    <div class="mt-2">
                        <strong>Global Config:</strong> This page follows settings from
                        <a href="<?php echo e($settingsPageUrl); ?>"><code>Global Settings > SEO</code></a>.
                    </div>
                </div>
                
                <div class="alert alert-success mb-3">
                    <div class="d-flex align-items-start">
                        <i class="ph-check-circle me-2 mt-1"></i>
                        <div>
                            <strong>Google Rich Results Ready</strong>
                            <p class="mb-2 mt-1">Your pages include structured data (JSON-LD schema) for enhanced search results with rich snippets.</p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="https://search.google.com/test/rich-results" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="ph-google-logo me-1"></i>Test Rich Results
                                </a>
                                <a href="https://search.google.com/search-console" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="ph-chart-line me-1"></i>Search Console
                                </a>
                                <a href="https://validator.schema.org/" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="ph-code me-1"></i>Schema Validator
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="sitemapRealtimeSummary" class="alert alert-secondary mb-3 d-none"></div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>URL</th>
                                <th>Handler Modified</th>
                                <th class="text-end">Handler Size</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sitemapFiles as $file): ?>
                                <tr
                                    data-sitemap-row="1"
                                    data-enabled="<?php echo $file['enabled'] ? '1' : '0'; ?>"
                                    data-handler-exists="<?php echo $file['exists'] ? '1' : '0'; ?>"
                                    data-endpoint-url="<?php echo e($file['full_url']); ?>"
                                    data-endpoint-name="<?php echo e($file['name']); ?>"
                                >
                                    <td><?php echo e($file['name']); ?></td>
                                    <td><?php echo e($file['type']); ?></td>
                                    <td>
                                        <?php if ($file['exists'] && $file['enabled']): ?>
                                            <a href="<?php echo e($file['full_url']); ?>" target="_blank"><?php echo e($file['url_path']); ?></a>
                                        <?php else: ?>
                                            <?php echo e($file['url_path']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e($file['updated_at']); ?></td>
                                    <td class="text-end"><?php echo e($file['size']); ?></td>
                                    <td class="text-center">
                                        <?php if (!$file['enabled']): ?>
                                            <span class="badge bg-secondary sitemap-status-badge">Disabled in SEO Settings</span>
                                        <?php elseif ($file['exists']): ?>
                                            <span class="badge bg-success sitemap-status-badge">Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger sitemap-status-badge">Handler Missing</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- AI Search Engines & Modern Discovery -->
        <div class="card mt-3">
            <div class="card-header bg-gradient-primary text-white">
                <h6 class="mb-0"><i class="ph-robot me-2"></i>AI Search Engines Discovery (2026 Standards)</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="ph-lightbulb me-2"></i>
                    <strong>AI Search Engine Optimization:</strong> Modern AI search engines (ChatGPT, Claude, Perplexity) require structured data and comprehensive sitemaps for optimal content discovery.
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="mb-2"><i class="ph-sparkle me-1"></i> AI Crawler Configuration</h6>
                        <ul class="small mb-3">
                            <li><strong>GPTBot (ChatGPT):</strong> OpenAI's crawler for AI training</li>
                            <li><strong>Claudebot:</strong> Anthropic's Claude AI crawler</li>
                            <li><strong>PerplexityBot:</strong> Perplexity AI search engine</li>
                            <li><strong>Google-Extended:</strong> Google AI training data</li>
                            <li><strong>CCBot:</strong> Common Crawl for AI datasets</li>
                        </ul>
                        
                        <h6 class="mb-2"><i class="ph-file-code me-1"></i> Recommended robots.txt Rules</h6>
                        <div class="bg-light p-2 rounded small font-monospace mb-2">
                            <div>User-agent: GPTBot</div>
                            <div>Allow: /</div>
                            <div>Crawl-delay: 1</div>
                            <div class="mt-2">User-agent: Claudebot</div>
                            <div>Allow: /</div>
                            <div>Crawl-delay: 1</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="mb-2"><i class="ph-graph me-1"></i> AI Sitemap Features</h6>
                        <p class="small text-muted">Your AI sitemap should include:</p>
                        <ul class="small mb-3">
                            <li>Detailed content summaries in descriptions</li>
                            <li>Structured data (JSON-LD) references</li>
                            <li>High-priority pages (companies, blogs, HS codes)</li>
                            <li>Content freshness indicators (lastmod)</li>
                            <li>Language and locale information</li>
                        </ul>
                        
                        <h6 class="mb-2"><i class="ph-rss me-1"></i> Alternative Discovery Methods</h6>
                        <div class="d-flex flex-column gap-2">
                            <div class="alert alert-secondary py-2 px-3 mb-0 small">
                                <strong>RSS Feed:</strong> Implement <code>/feed.xml</code> or <code>/rss.xml</code> for blog content
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- IndexNow & Instant Indexing -->
        <div class="card mt-3">
            <div class="card-header bg-gradient-success text-white">
                <h6 class="mb-0"><i class="ph-lightning-a me-2"></i>IndexNow Protocol - Instant Indexing</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <p class="mb-2"><strong>What is IndexNow?</strong></p>
                        <p class="text-muted small mb-3">IndexNow is a protocol that allows websites to instantly notify search engines (Bing, Yandex, Seznam) about content changes, reducing crawl time from days/weeks to minutes.</p>
                        
                        <div class="alert alert-warning mb-3">
                            <i class="ph-warning-circle me-2"></i>
                            <strong>Action Required:</strong> Implement IndexNow API integration to notify search engines when:
                            <ul class="mb-0 mt-2 small">
                                <!-- Decommissioned: company and blog items -->
                                <li>HS code is updated</li>
                                <li>Any content is modified or deleted</li>
                            </ul>
                        </div>
                        
                        <h6 class="mb-2">Supported Search Engines:</h6>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge bg-info">Microsoft Bing</span>
                            <span class="badge bg-info">Yandex</span>
                            <span class="badge bg-info">Seznam.cz</span>
                            <span class="badge bg-info">Naver</span>
                            <span class="badge bg-secondary">Others via partners</span>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <h6 class="mb-2">Implementation Steps:</h6>
                        <ol class="small">
                            <li>Generate unique API key</li>
                            <li>Place key file at root: <code>/[key].txt</code></li>
                            <li>Submit URLs via POST to:<br>
                                <code class="small">api.indexnow.org/indexnow</code>
                            </li>
                            <li>Monitor submission logs</li>
                        </ol>
                        
                        <div class="d-flex flex-column gap-2 mt-3">
                            <a href="https://www.indexnow.org/" target="_blank" class="btn btn-sm btn-outline-success">
                                <i class="ph-link-external me-1"></i>IndexNow Documentation
                            </a>
                            <a href="https://www.bing.com/indexnow" target="_blank" class="btn btn-sm btn-outline-info">
                                <i class="ph-microsoft-logo me-1"></i>Bing IndexNow
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sitemap Best Practices -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="ph-list-checks me-2"></i>Sitemap Best Practices (2026)</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <h6 class="mb-2"><i class="ph-star me-1"></i> Priority Guidelines</h6>
                        <div class="table-responsive">
<table class="table table-sm small mb-0">
                            <tbody>
                                <tr>
                                    <td><code>1.0</code></td>
                                    <td>Homepage only</td>
                                </tr>
                                <tr>
                                    <td><code>0.8-0.9</code></td>
                                    <td>Main category pages</td>
                                </tr>
                                <tr>
                                    <td><code>0.6-0.7</code></td>
                                    <td>Company/product pages</td>
                                </tr>
                                <tr>
                                    <td><code>0.4-0.5</code></td>
                                    <td>Blog posts, articles</td>
                                </tr>
                                <tr>
                                    <td><code>0.1-0.3</code></td>
                                    <td>Archive, old content</td>
                                </tr>
                            </tbody>
                        </table>
</div>
                    </div>
                    
                    <div class="col-md-4">
                        <h6 class="mb-2"><i class="ph-clock me-1"></i> Change Frequency</h6>
                        <div class="table-responsive">
<table class="table table-sm small mb-0">
                            <tbody>
                                <tr>
                                    <td><code>always</code></td>
                                    <td>Real-time data (search results)</td>
                                </tr>
                                <tr>
                                    <td><code>hourly</code></td>
                                    <td>News feeds, live data</td>
                                </tr>
                                <tr>
                                    <td><code>daily</code></td>
                                    <td>Blog, company updates</td>
                                </tr>
                                <tr>
                                    <td><code>weekly</code></td>
                                    <td>Category pages</td>
                                </tr>
                                <tr>
                                    <td><code>monthly</code></td>
                                    <td>Static pages</td>
                                </tr>
                                <tr>
                                    <td><code>never</code></td>
                                    <td>Archived content</td>
                                </tr>
                            </tbody>
                        </table>
</div>
                    </div>
                    
                    <div class="col-md-4">
                        <h6 class="mb-2"><i class="ph-warning me-1"></i> Common Mistakes</h6>
                        <ul class="small mb-3">
                            <li>Setting all pages to priority 1.0</li>
                            <li>Not updating lastmod dates</li>
                            <li>Exceeding 50,000 URLs per sitemap</li>
                            <li>Including blocked/noindex pages</li>
                            <li>Missing canonical URLs</li>
                            <li>Not compressing large sitemaps (gzip)</li>
                        </ul>
                        
                        <div class="alert alert-info py-2 px-3 mb-0 small">
                            <strong>Tip:</strong> Use sitemap index files to split large sitemaps by content type
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Submission & Monitoring -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="ph-upload-simple me-2"></i>Sitemap Submission & Monitoring</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <h6 class="mb-2">Traditional Search Engines</h6>
                        <div class="list-group list-group-flush small">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="ph-google-logo me-2 text-primary"></i>
                                    <strong>Google Search Console</strong>
                                    <br><span class="text-muted small">Submit sitemap_index.xml</span>
                                </div>
                                <a href="https://search.google.com/search-console/sitemaps" target="_blank" class="btn btn-sm btn-outline-primary">
                                    Submit
                                </a>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="ph-microsoft-logo me-2 text-info"></i>
                                    <strong>Bing Webmaster Tools</strong>
                                    <br><span class="text-muted small">Auto-imports from GSC or manual</span>
                                </div>
                                <a href="https://www.bing.com/webmasters/sitemaps" target="_blank" class="btn btn-sm btn-outline-info">
                                    Submit
                                </a>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="ph-browser me-2 text-danger"></i>
                                    <strong>Yandex Webmaster</strong>
                                    <br><span class="text-muted small">For Russian/CIS markets</span>
                                </div>
                                <a href="https://webmaster.yandex.com/" target="_blank" class="btn btn-sm btn-outline-danger">
                                    Submit
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="mb-2">AI & Alternative Search</h6>
                        <div class="list-group list-group-flush small">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="ph-robot me-2 text-success"></i>
                                    <strong>AI Search Engines</strong>
                                    <br><span class="text-muted small">Reference ai-sitemap.xml in robots.txt</span>
                                </div>
                                <span class="badge bg-success">Auto-discover</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="ph-note me-2 text-warning"></i>
                                    <strong>DuckDuckGo</strong>
                                    <br><span class="text-muted small">Discovers via robots.txt</span>
                                </div>
                                <span class="badge bg-warning">Auto-discover</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="ph-rss-simple me-2 text-secondary"></i>
                                    <strong>RSS/Atom Feeds</strong>
                                    <br><span class="text-muted small">Create /feed.xml or /rss.xml</span>
                                </div>
                                <span class="badge bg-secondary">Planned</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-success">
                    <div class="d-flex align-items-start">
                        <i class="ph-check-circle me-2 mt-1"></i>
                        <div class="small">
                            <strong>Pro Tip:</strong> After submitting sitemaps, monitor these metrics:
                            <ul class="mb-0 mt-2">
                                <li><strong>Coverage:</strong> How many URLs are indexed vs submitted</li>
                                <li><strong>Last Read:</strong> When search engines last crawled your sitemap</li>
                                <li><strong>Errors:</strong> Any URLs that couldn't be indexed (404s, blocked, etc.)</li>
                                <li><strong>Impressions:</strong> How often your URLs appear in search results</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Image & Video Sitemaps -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="ph-images me-2"></i>Image & Video Sitemaps (Advanced)</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="mb-2"><i class="ph-image me-1"></i> Image Sitemap</h6>
                        <p class="small text-muted">Help Google discover images for Image Search results</p>
                        <div class="alert alert-secondary py-2 px-3 small mb-2">
                            <strong>Status:</strong> <span class="badge bg-warning">Recommended</span>
                        </div>
                        <p class="small mb-2"><strong>Should include:</strong></p>
                        <ul class="small mb-3">
                            <li>Company logos and profile images</li>
                            <li>Product/service images</li>
                            <li>Blog post featured images</li>
                            <li>Category page images</li>
                        </ul>
                        <div class="bg-light p-2 rounded small font-monospace">
                            &lt;image:image&gt;<br>
                            &nbsp;&nbsp;&lt;image:loc&gt;URL&lt;/image:loc&gt;<br>
                            &nbsp;&nbsp;&lt;image:title&gt;Title&lt;/image:title&gt;<br>
                            &nbsp;&nbsp;&lt;image:caption&gt;...&lt;/image:caption&gt;<br>
                            &lt;/image:image&gt;
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="mb-2"><i class="ph-video-camera me-1"></i> Video Sitemap</h6>
                        <p class="small text-muted">Enable video rich results in Google Search</p>
                        <div class="alert alert-secondary py-2 px-3 small mb-2">
                            <strong>Status:</strong> <span class="badge bg-secondary">Optional</span>
                        </div>
                        <p class="small mb-2"><strong>Should include:</strong></p>
                        <ul class="small mb-3">
                            <li>Tutorial/how-to videos</li>
                            <li>Product demonstration videos</li>
                            <li>Company introduction videos</li>
                            <li>Event recordings</li>
                        </ul>
                        <div class="bg-light p-2 rounded small font-monospace">
                            &lt;video:video&gt;<br>
                            &nbsp;&nbsp;&lt;video:thumbnail_loc&gt;...&lt;/video:thumbnail_loc&gt;<br>
                            &nbsp;&nbsp;&lt;video:title&gt;Title&lt;/video:title&gt;<br>
                            &nbsp;&nbsp;&lt;video:description&gt;...&lt;/video:description&gt;<br>
                            &lt;/video:video&gt;
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3 mb-0">
                    <i class="ph-info me-2"></i>
                    <strong>Implementation Note:</strong> Image and video tags can be added to existing sitemaps or created as separate sitemaps. Recommended: Add to existing company/blog sitemaps.
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="ph-lightning me-2"></i>AMP Pages (Accelerated Mobile Pages)</h6>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">AMP pages are available in <code>pages/amp/</code> directory for faster mobile loading and better Google ranking.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="mb-2">Available AMP Pages:</h6>
                        <ul class="list-unstyled mb-0">
                            <!-- Decommissioned: Blog and Company AMP pages -->
                            <li><i class="ph-check-circle text-success me-2"></i>HS Code Details AMP</li>
                            <li><i class="ph-check-circle text-success me-2"></i>HS Codes Listing AMP</li>
                            <li><i class="ph-check-circle text-success me-2"></i>Listings AMP</li>
                            <li><i class="ph-check-circle text-success me-2"></i>About AMP</li>
                            <li><i class="ph-check-circle text-success me-2"></i>Contact AMP</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-2">AMP Testing Tools:</h6>
                        <div class="d-flex flex-column gap-2">
                            <a href="https://search.google.com/test/amp" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="ph-lightning me-1"></i>Google AMP Validator
                            </a>
                            <a href="https://search.google.com/test/mobile-friendly" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="ph-device-mobile me-1"></i>Mobile-Friendly Test
                            </a>
                            <a href="https://pagespeed.web.dev/" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="ph-gauge me-1"></i>PageSpeed Insights
                            </a>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="ph-check-circle me-2"></i>
                    <strong>AMP Sitemap Handler:</strong> <code>pages/sitemap-amp.php</code> is available and now auto-splits into multiple sitemap parts when AMP URLs exceed 45,000. Enable it from <a href="<?php echo e($settingsPageUrl); ?>">Global Settings &gt; SEO</a>.
                </div>
            </div>
        </div>
    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>

<script>
$(function() {
    const $button = $('#runRealtimeSitemapChecks');
    const $summary = $('#sitemapRealtimeSummary');

    async function checkEndpoint(url) {
        try {
            const headResponse = await fetch(url, {
                method: 'HEAD',
                cache: 'no-store',
                credentials: 'same-origin'
            });

            if (headResponse.status === 405 || headResponse.status === 501) {
                const getResponse = await fetch(url, {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin'
                });
                return getResponse;
            }

            return headResponse;
        } catch (error) {
            return null;
        }
    }

    function setBadge($badge, type, text) {
        $badge.removeClass('bg-success bg-danger bg-warning bg-secondary bg-info')
            .addClass(type)
            .text(text);
    }

    $button.on('click', async function() {
        const $rows = $('tr[data-sitemap-row="1"]');
        let okCount = 0;
        let failedCount = 0;
        let skippedCount = 0;

        $button.prop('disabled', true).html('<i class="ph-spinner-gap me-1"></i>Checking...');
        $summary.removeClass('d-none alert-success alert-danger alert-secondary').addClass('alert-info').text('Running real-time sitemap checks...');

        for (let i = 0; i < $rows.length; i++) {
            const $row = $($rows[i]);
            const enabled = $row.data('enabled') === 1 || $row.data('enabled') === '1';
            const handlerExists = $row.data('handler-exists') === 1 || $row.data('handler-exists') === '1';
            const endpointUrl = $row.data('endpoint-url');
            const $badge = $row.find('.sitemap-status-badge');

            if (!enabled) {
                skippedCount++;
                setBadge($badge, 'bg-secondary', 'Disabled in SEO Settings');
                continue;
            }

            if (!handlerExists) {
                failedCount++;
                setBadge($badge, 'bg-danger', 'Handler Missing');
                continue;
            }

            setBadge($badge, 'bg-warning', 'Checking...');

            const response = await checkEndpoint(endpointUrl);
            if (response && response.status >= 200 && response.status < 400) {
                okCount++;
                setBadge($badge, 'bg-success', 'Live ✓ (' + response.status + ')');
            } else {
                failedCount++;
                const code = response ? response.status : 'ERR';
                setBadge($badge, 'bg-danger', 'Live ✗ (' + code + ')');
            }
        }

        const checkedCount = okCount + failedCount;
        const summaryClass = failedCount > 0 ? 'alert-danger' : 'alert-success';
        const now = new Date().toLocaleString();

        $summary.removeClass('alert-info alert-success alert-danger alert-secondary d-none')
            .addClass(summaryClass)
            .html('<strong>Real-Time Check Complete:</strong> ' + okCount + ' passed, ' + failedCount + ' failed, ' + skippedCount + ' skipped (disabled).<br><small>Checked: ' + checkedCount + ' endpoints at ' + now + '</small>');

        $button.prop('disabled', false).html('<i class="ph-arrows-clockwise me-1"></i>Run Real-Time Checks');
    });
});
</script>

<?php include('admin_elements/admin_footer.php'); ?>
