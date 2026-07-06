<?php

use App\Core\DB;
/**
 * SEO Metadata Auto-Population Script
 * 
 * Intelligently generates and populates SEO metadata for all public-facing tables
 * using best practices for search engine optimization and social media sharing.
 * 
 * @package Scripts
 * @author SEO Team
 * @version 1.0
 */

require_once __DIR__ . '/admin_elements/error_handler_init.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/globals.php';
require_once __DIR__ . '/admin_elements/error_logger.php';

// Register custom error/exception/shutdown handlers for CLI execution
if (function_exists('custom_error_handler')) {
    set_error_handler('custom_error_handler');
}
if (function_exists('custom_exception_handler')) {
    set_exception_handler('custom_exception_handler');
}
if (function_exists('handle_fatal_error')) {
    register_shutdown_function('handle_fatal_error');
}

class SEOAutoPopulator {
    private $conn;
    private $updated_count = 0;
    private $skipped_count = 0;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Generate SEO title from text (50-60 chars optimal)
     */
    private function generateTitle($text, $suffix = '', $maxChars = 60) {
        $text = trim(htmlspecialchars_decode($text));
        if (strlen($text) > $maxChars - strlen($suffix)) {
            $text = substr($text, 0, $maxChars - strlen($suffix) - 3) . '...';
        }
        return $text . $suffix;
    }
    
    /**
     * Generate SEO description from text (150-160 chars optimal)
     */
    private function generateDescription($text, $maxChars = 155) {
        if (empty($text)) {
            return '';
        }
        $text = trim(htmlspecialchars_decode(strip_tags($text)));
        if (strlen($text) > $maxChars) {
            $text = substr($text, 0, $maxChars - 3) . '...';
        }
        return $text;
    }
    
    /**
     * Generate keywords from slug text (5-10 keywords)
     */
    private function generateKeywords($slug, $name = '') {
        $keywords = [];
        
        // Extract from slug (replace dashes with spaces)
        if (!empty($slug)) {
            $slug_words = explode('-', strtolower($slug));
            $keywords = array_merge($keywords, array_filter($slug_words));
        }
        
        // Add main name as keyword
        if (!empty($name)) {
            $keywords[] = strtolower(trim($name));
            
            // Split name into words (up to 5 char min)
            $name_words = preg_split('/\s+/', strtolower($name));
            foreach ($name_words as $word) {
                if (strlen($word) > 3 && !in_array($word, $keywords)) {
                    $keywords[] = $word;
                }
            }
        }
        
        // Limit to 8 keywords and join
        $keywords = array_slice(array_unique($keywords), 0, 8);
        return implode(', ', $keywords);
    }
    
    /**
     * Generate canonical URL
     */
    private function generateCanonicalUrl($slug, $baseUrl = 'https://flasherpsystem.com') {
        return rtrim($baseUrl, '/') . '/' . ltrim($slug, '/');
    }
    
    /**
     * Populate SEO fields for hai_categories
     */
    public function populateCategories() {
        echo "\n=== Populating hai_categories ===\n";
        
        $query = "SELECT id, name, slug, description FROM " . DB::CATEGORIES . " 
                  WHERE is_active = 1 AND is_active = 1 
                  AND (meta_title IS NULL OR meta_description IS NULL)
                  LIMIT 100";
        
        $result = $this->conn->query($query);
        
        if (!$result) {
            echo "Error: " . $this->conn->error . "\n";
            return;
        }
        
        while ($row = $result->fetch_assoc()) {
            $meta_title = $this->generateTitle($row['name'], ' | UAE Business Directory');
            $meta_description = $this->generateDescription($row['description']);
            $meta_keywords = $this->generateKeywords($row['slug'], $row['name']);
            $og_title = substr($row['name'], 0, 55);
            $og_description = substr($meta_description, 0, 130);
            $canonical_url = $this->generateCanonicalUrl('/category/' . $row['slug']);
            
            $update = "UPDATE " . DB::CATEGORIES . " SET 
                       meta_title = ?, 
                       meta_description = ?, 
                       meta_keywords = ?, 
                       og_title = ?, 
                       og_description = ?, 
                       canonical_url = ?,
                       meta_robots = 'index,follow'
                       WHERE id = ?";
            
            $stmt = $this->conn->prepare($update);
            $stmt->bind_param('ssssssi', $meta_title, $meta_description, $meta_keywords, 
                            $og_title, $og_description, $canonical_url, $row['id']);
            
            if ($stmt->execute()) {
                $this->updated_count++;
                echo "âœ“ Updated: {$row['name']}\n";
            } else {
                echo "âœ— Failed: {$row['name']} - " . $stmt->error . "\n";
            }
            $stmt->close();
        }
    }
    
    /**
     * Populate SEO fields for hai_subcategories
     */
    public function populateSubcategories() {
        echo "\n=== Populating hai_subcategories ===\n";
        
        $query = "SELECT id, name, slug, description FROM " . DB::SUBCATEGORIES . " 
                  WHERE is_active = 1 AND is_active = 1 
                  AND (meta_title IS NULL OR meta_description IS NULL)
                  LIMIT 200";
        
        $result = $this->conn->query($query);
        
        if (!$result) {
            echo "Error: " . $this->conn->error . "\n";
            return;
        }
        
        while ($row = $result->fetch_assoc()) {
            $meta_title = $this->generateTitle($row['name'], ' | UAE Business');
            $meta_description = $this->generateDescription($row['description']);
            $meta_keywords = $this->generateKeywords($row['slug'], $row['name']);
            $og_title = substr($row['name'], 0, 55);
            $og_description = substr($meta_description, 0, 130);
            $canonical_url = $this->generateCanonicalUrl('/category-' . $row['slug']);
            
            $update = "UPDATE " . DB::SUBCATEGORIES . " SET 
                       meta_title = ?, 
                       meta_description = ?, 
                       meta_keywords = ?, 
                       og_title = ?, 
                       og_description = ?, 
                       canonical_url = ?,
                       meta_robots = 'index,follow'
                       WHERE id = ?";
            
            $stmt = $this->conn->prepare($update);
            $stmt->bind_param('ssssssi', $meta_title, $meta_description, $meta_keywords, 
                            $og_title, $og_description, $canonical_url, $row['id']);
            
            if ($stmt->execute()) {
                $this->updated_count++;
                echo "âœ“ Updated: {$row['name']}\n";
            } else {
                echo "âœ— Failed: {$row['name']} - " . $stmt->error . "\n";
            }
            $stmt->close();
        }
    }
    
    /**
     * Populate SEO fields for hai_category_items
     */
    public function populateCategoryItems() {
        echo "\n=== Populating hai_category_items ===\n";
        echo "Skipped: erp_category_items table is decommissioned.\n";
        return;
    }
    
    /**
     * Populate SEO fields for hai_blog_categories
     */
    public function populateBlogCategories() {
        echo "\n=== Populating hai_blog_categories ===\n";
        
        $query = "SELECT id, name, slug, description FROM " . DB::BLOG_CATEGORIES . " 
                  WHERE status = 1 
                  AND (meta_title IS NULL OR og_title IS NULL)
                  LIMIT 100";
        
        $result = $this->conn->query($query);
        
        if (!$result) {
            echo "Error: " . $this->conn->error . "\n";
            return;
        }
        
        while ($row = $result->fetch_assoc()) {
            $meta_title = $this->generateTitle($row['name'], ' - Blog Category');
            $og_title = substr($row['name'], 0, 55);
            $og_description = substr($row['description'] ?? $meta_title, 0, 130);
            $canonical_url = $this->generateCanonicalUrl('/blog/category/' . $row['slug']);
            
            $update = "UPDATE " . DB::BLOG_CATEGORIES . " SET 
                       og_title = ?, 
                       og_description = ?, 
                       canonical_url = ?,
                       meta_robots = 'index,follow'
                       WHERE id = ?";
            
            $stmt = $this->conn->prepare($update);
            $stmt->bind_param('sssi', $og_title, $og_description, $canonical_url, $row['id']);
            
            if ($stmt->execute()) {
                $this->updated_count++;
                echo "âœ“ Updated: {$row['name']}\n";
            } else {
                echo "âœ— Failed: {$row['name']} - " . $stmt->error . "\n";
            }
            $stmt->close();
        }
    }
    
    /**
     * Populate SEO fields for hai_hscodes
     */
    public function populateHSCodes() {
        echo "\n=== Populating hai_hscodes ===\n";
        
        $query = "SELECT id, code FROM " . DB::HS_CODES . " 
                  WHERE is_active = 1 
                  AND (meta_title IS NULL OR meta_description IS NULL)
                  LIMIT 1000";
        
        $result = $this->conn->query($query);
        
        if (!$result) {
            echo "Error: " . $this->conn->error . "\n";
            return;
        }
        
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $meta_title = "HS Code {$row['code']} | Import/Export Tariff";
            $meta_description = "Find detailed information about HS Code {$row['code']}, including tariff rates, classification details, and trade regulations for importing and exporting goods.";
            $meta_keywords = "HS Code {$row['code']}, tariff, import, export, commodity code";
            $og_title = "HS Code {$row['code']}";
            $og_description = "Check tariff rates and import/export details for HS Code {$row['code']}";
            $canonical_url = $this->generateCanonicalUrl('/hs-code/' . $row['code']);
            
            $update = "UPDATE " . DB::HS_CODES . " SET 
                       meta_title = ?, 
                       meta_description = ?, 
                       meta_keywords = ?, 
                       og_title = ?, 
                       og_description = ?, 
                       canonical_url = ?,
                       meta_robots = 'index,follow'
                       WHERE id = ?";
            
            $stmt = $this->conn->prepare($update);
            $stmt->bind_param('ssssssi', $meta_title, $meta_description, $meta_keywords, 
                            $og_title, $og_description, $canonical_url, $row['id']);
            
            if ($stmt->execute()) {
                $count++;
                if ($count % 100 == 0) {
                    echo "Processed {$count} HS codes...\n";
                }
            }
            $stmt->close();
        }
        
        echo "Completed {$count} HS codes\n";
        $this->updated_count += $count;
    }
    
    /**
     * Run all population tasks
     */
    public function populateAll() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "  SEO METADATA AUTO-POPULATION\n";
        echo str_repeat("=", 60) . "\n";
        
        $start_time = microtime(true);
        
        $this->populateCategories();
        $this->populateSubcategories();
        $this->populateCategoryItems();
        $this->populateBlogCategories();
        $this->populateHSCodes();
        
        $elapsed = microtime(true) - $start_time;
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "  SUMMARY\n";
        echo str_repeat("=", 60) . "\n";
        echo "Total Records Updated: {$this->updated_count}\n";
        echo "Skipped/Failed: {$this->skipped_count}\n";
        echo "Time Elapsed: " . round($elapsed, 2) . " seconds\n";
        echo "\nâœ… SEO metadata population complete!\n\n";
    }
}

// Execute if run from command line
if (php_sapi_name() === 'cli') {
    $populator = new SEOAutoPopulator($mysqli);
    $populator->populateAll();
} else {
    // If called from web, just output a warning
    echo "<h3>This script should be run from command line:</h3>";
    echo "<code>php dashboard/seo_auto_populate.php</code>";
}

