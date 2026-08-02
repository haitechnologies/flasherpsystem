<?php
/**
 * SEO Meta Tags Helper
 * 
 * Generates proper meta tags for search engine optimization (SEO)
 * and social media sharing (OpenGraph, Twitter Cards)
 * 
 * @package Helpers
 * @version 1.0
 */

/**
 * Output SEO meta tags for a page
 * Recommended to use in <head> section of HTML
 * 
 * Usage:
 * <?php output_seo_meta_tags([ 
 *     'title' => 'Page Title',
 *     'description' => 'Page description',
 *     'keywords' => 'keyword1, keyword2, keyword3',
 *     'image' => 'https://example.com/image.jpg',
 *     'url' => 'https://example.com/page',
 *     'type' => 'article', // website, article, product, etc.
 *     'author' => 'Author Name'
 *  ]); ?>
 */
function output_seo_meta_tags($seo_data = []) {
    // Defaults
    $app_name_clean = defined('APP_NAME') ? APP_NAME : 'FLASH ERP SYSTEM';
    $app_name_lower = strtolower($app_name_clean);
    $defaults = [
        'title' => '',
        'description' => '',
        'keywords' => '',
        'image' => asset_url('images/default-og-image.jpg'),
        'url' => current_url(),
        'type' => 'website',
        'author' => $app_name_clean,
        'twitter_handle' => '@' . $app_name_lower,
        'facebook_app_id' => '123456789',
        'locale' => 'en_US',
    ];
    
    $data = array_merge($defaults, $seo_data);
    
    // Clean and validate data
    $title = htmlspecialchars(substr($data['title'], 0, 60), ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars(substr($data['description'], 0, 160), ENT_QUOTES, 'UTF-8');
    $keywords = htmlspecialchars($data['keywords'], ENT_QUOTES, 'UTF-8');
    $image = htmlspecialchars($data['image'], ENT_QUOTES, 'UTF-8');
    $url = htmlspecialchars($data['url'], ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars($data['type'], ENT_QUOTES, 'UTF-8');
    $author = htmlspecialchars($data['author'], ENT_QUOTES, 'UTF-8');
    
    // Output all meta tags
    echo "<!-- SEO Meta Tags -->\n";
    echo "<meta charset=\"UTF-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    echo "<title>$title</title>\n";
    echo "<meta name=\"description\" content=\"$description\">\n";
    
    if (!empty($keywords)) {
        echo "<meta name=\"keywords\" content=\"$keywords\">\n";
    }
    
    echo "<meta name=\"author\" content=\"$author\">\n";
    echo "<meta name=\"robots\" content=\"index, follow\">\n";
    echo "<meta name=\"language\" content=\"English\">\n";
    
    // Open Graph / Facebook
    echo "\n<!-- Open Graph / Facebook -->\n";
    echo "<meta property=\"og:type\" content=\"$type\">\n";
    echo "<meta property=\"og:url\" content=\"$url\">\n";
    echo "<meta property=\"og:title\" content=\"$title\">\n";
    echo "<meta property=\"og:description\" content=\"$description\">\n";
    echo "<meta property=\"og:image\" content=\"$image\">\n";
    echo "<meta property=\"og:site_name\" content=\"" . htmlspecialchars($app_name_clean, ENT_QUOTES, 'UTF-8') . "\">\n";
    echo "<meta property=\"og:locale\" content=\"en_US\">\n";
    
    if (!empty($data['facebook_app_id'])) {
        echo "<meta property=\"fb:app_id\" content=\"{$data['facebook_app_id']}\">\n";
    }
    
    // Twitter
    echo "\n<!-- Twitter -->\n";
    echo "<meta property=\"twitter:card\" content=\"summary_large_image\">\n";
    echo "<meta property=\"twitter:url\" content=\"$url\">\n";
    echo "<meta property=\"twitter:title\" content=\"$title\">\n";
    echo "<meta property=\"twitter:description\" content=\"$description\">\n";
    echo "<meta property=\"twitter:image\" content=\"$image\">\n";
    if (!empty($data['twitter_handle'])) {
        echo "<meta name=\"twitter:creator\" content=\"{$data['twitter_handle']}\">\n";
    }
    
    // Canonical URL
    if (!empty($data['canonical_url'])) {
        echo "\n<!-- Canonical URL -->\n";
        echo "<link rel=\"canonical\" href=\"" . htmlspecialchars($data['canonical_url'], ENT_QUOTES, 'UTF-8') . "\">\n";
    }
}

/**
 * Extract SEO data from database record
 * 
 * @param array $record - Database record with SEO fields
 * @param array $overrides - Optional field overrides
 * @return array - Formatted SEO data
 */
function extract_seo_data($record, $overrides = []) {
    $seo_data = [
        'title' => $record['meta_title'] ?? $record['title'] ?? $record['name'] ?? '',
        'description' => $record['meta_description'] ?? $record['description'] ?? $record['excerpt'] ?? '',
        'keywords' => $record['meta_keywords'] ?? $record['seo_keywords'] ?? '',
        'image' => $record['og_image'] ?? $record['featured_image'] ?? asset_url('images/default-og-image.jpg'),
        'url' => $record['canonical_url'] ?? current_url(),
        'author' => $record['created_by_name'] ?? (defined('APP_NAME') ? APP_NAME : 'FLASH ERP SYSTEM'),
    ];
    
    // Apply overrides (for dynamic content)
    return array_merge($seo_data, $overrides);
}

/**
 * Generate structured data (JSON-LD) for search engines
 * Helps Google understand page content better
 * 
 * Usage: echo generate_json_ld_organization();
 */
function generate_json_ld_organization() {
    $name = defined('APP_NAME') ? APP_NAME : 'FLASH ERP SYSTEM';
    $domain = defined('APP_DOMAIN') ? APP_DOMAIN : 'flasherpsystem.com';
    $name_lower = strtolower($name);
    
    $data = [
        "@context" => "https://schema.org/",
        "@type" => "Organization",
        "name" => $name,
        "url" => "https://" . $domain,
        "logo" => "https://" . $domain . "/assets/images/logo.png",
        "description" => $name . " Business Directory & Trade Platform",
        "address" => [
            "@type" => "PostalAddress",
            "addressCountry" => "AE",
            "addressLocality" => "Dubai",
        ],
        "sameAs" => [
            "https://www.facebook.com/" . $name_lower,
            "https://www.twitter.com/" . $name_lower,
            "https://www.instagram.com/" . $name_lower,
        ],
    ];
    
    return '<script type="application/ld+json">' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>';
}

/**
 * Generate JSON-LD breadcrumb schema
 * Helps search engines understand page hierarchy
 */
function generate_json_ld_breadcrumb($breadcrumbs = []) {
    if (empty($breadcrumbs)) {
        return '';
    }
    
    $items = [];
    foreach ($breadcrumbs as $index => $crumb) {
        $items[] = [
            "@type" => "ListItem",
            "position" => $index + 1,
            "name" => $crumb['name'] ?? '',
            "item" => $crumb['url'] ?? '',
        ];
    }
    
    $data = [
        "@context" => "https://schema.org",
        "@type" => "BreadcrumbList",
        "itemListElement" => $items,
    ];
    
    return '<script type="application/ld+json">' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>';
}

/**
 * Generate JSON-LD for article/blog post
 */
function generate_json_ld_article($article) {
    $data = [
        "@context" => "https://schema.org",
        "@type" => "BlogPosting",
        "mainEntityOfPage" => [
            "@type" => "WebPage",
            "@id" => $article['url'] ?? '',
        ],
        "headline" => $article['title'] ?? '',
        "description" => $article['description'] ?? '',
        "image" => ($article['image'] ?? null) ? [$article['image']] : [],
        "author" => [
            "@type" => "Person",
            "name" => $article['author'] ?? (defined('APP_NAME') ? APP_NAME : 'FLASH ERP SYSTEM'),
        ],
        "publisher" => [
            "@type" => "Organization",
            "name" => (defined('APP_NAME') ? APP_NAME : 'FLASH ERP SYSTEM'),
            "logo" => [
                "@type" => "ImageObject",
                "url" => "https://" . (defined('APP_DOMAIN') ? APP_DOMAIN : 'flasherpsystem.com') . "/assets/images/logo.png",
            ],
        ],
        "datePublished" => $article['published_date'] ?? date('c'),
        "dateModified" => $article['modified_date'] ?? date('c'),
    ];
    
    return '<script type="application/ld+json">' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>';
}

/**
 * Generate JSON-LD for local business
 * Perfect for company/store directory listings
 */
function generate_json_ld_local_business($business) {
    $data = [
        "@context" => "https://schema.org/",
        "@type" => "LocalBusiness",
        "name" => $business['name'] ?? '',
        "image" => $business['image'] ?? '',
        "description" => substr($business['description'] ?? '', 0, 200),
        "url" => $business['website'] ?? '',
        "telephone" => $business['phone'] ?? '',
        "address" => [
            "@type" => "PostalAddress",
            "streetAddress" => $business['address'] ?? '',
            "addressLocality" => $business['city'] ?? '',
            "addressRegion" => $business['state'] ?? '',
            "postalCode" => $business['postal_code'] ?? '',
            "addressCountry" => $business['country'] ?? 'AE',
        ],
        "areaServed" => "AE",
    ];
    
    // Add geo coordinates if available
    if (!empty($business['latitude']) && !empty($business['longitude'])) {
        $data["geo"] = [
            "@type" => "GeoCoordinates",
            "latitude" => $business['latitude'],
            "longitude" => $business['longitude'],
        ];
    }
    
    return '<script type="application/ld+json">' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>';
}

/**
 * Helper to get current URL
 */
function current_url() {
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Helper to get asset URL
 */
function asset_url($path) {
    $base_url = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $base_url .= $_SERVER['HTTP_HOST'];
    return rtrim($base_url, '/') . '/' . ltrim($path, '/');
}


