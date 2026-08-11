<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Container;
use App\Core\Database;
use App\Core\DB;
use App\Core\ErrorCapture;
use Exception;
use mysqli;
use Throwable;

/**
 * DisposableEmailValidator
 *
 * Validates email addresses against disposable/temporary domains.
 * Priority: Database table -> config files fallback.
 */
class DisposableEmailValidator
{
    private static ?array $blocklist = null;
    private static ?array $allowlist = null;
    private static string $loadedFrom = 'none';

    private string $blocklist_path;
    private string $allowlist_path;
    private mixed $conn;

    /**
     * @param string|null $blocklist_path Path to blocklist file
     * @param string|null $allowlist_path Path to allowlist file
     * @param mixed|null $conn Database connection (optional)
     */
    public function __construct(?string $blocklist_path = null, ?string $allowlist_path = null, mixed $conn = null)
    {
        if (!$blocklist_path) {
            $blocklist_path = __DIR__ . '/../../config/disposable_email_blocklist.conf';
        }
        if (!$allowlist_path) {
            $allowlist_path = __DIR__ . '/../../config/disposable_email_allowlist.conf';
        }

        $this->blocklist_path = $blocklist_path;
        $this->allowlist_path = $allowlist_path;
        $this->conn = $conn;

        if ($this->conn === null) {
            try {
                $container = Container::getInstance();
                if ($container->has(Database::class)) {
                    $this->conn = $container->get(Database::class);
                }
            } catch (Throwable $e) {
                // Ignore container errors
            }
        }

        if (self::$blocklist === null || self::$allowlist === null) {
            $loaded = false;

            if ($this->conn instanceof Database || $this->conn instanceof mysqli) {
                $loaded = $this->loadFromDatabase();
            }

            if (!$loaded) {
                $this->loadFromFiles();
            }
        }
    }

    /**
     * Load blocklist and allowlist from database table.
     */
    private function loadFromDatabase(): bool
    {
        $table = class_exists('DB') ? DB::DISPOSABLE_EMAIL_DOMAINS : 'erp_disposable_email_domains';
        $sql = "SELECT domain, is_disposable, is_allowlisted
                FROM `{$table}`
                WHERE status = 1";

        $block = [];
        $allow = [];

        try {
            if ($this->conn instanceof Database) {
                $rows = $this->conn->fetchAll($sql);
                foreach ($rows as $row) {
                    $domain = mb_strtolower(trim((string)($row['domain'] ?? '')));
                    if ($domain === '') {
                        continue;
                    }

                    if ((int)$row['is_allowlisted'] === 1) {
                        $allow[$domain] = true;
                        continue;
                    }

                    if ((int)$row['is_disposable'] === 1) {
                        $block[$domain] = true;
                    }
                }
            } elseif ($this->conn instanceof mysqli) {
                $result = @$this->conn->query($sql);
                if (!$result) {
                    return false;
                }

                while ($row = $result->fetch_assoc()) {
                    $domain = mb_strtolower(trim((string)($row['domain'] ?? '')));
                    if ($domain === '') {
                        continue;
                    }

                    if ((int)$row['is_allowlisted'] === 1) {
                        $allow[$domain] = true;
                        continue;
                    }

                    if ((int)$row['is_disposable'] === 1) {
                        $block[$domain] = true;
                    }
                }
            }
        } catch (Throwable $e) {
            ErrorCapture::record("DisposableEmailValidator::loadFromDatabase() - Error loading: " . $e->getMessage());
            return false;
        }

        // Consider DB source valid only when it contains data.
        if (empty($block) && empty($allow)) {
            return false;
        }

        self::$blocklist = $block;
        self::$allowlist = $allow;
        self::$loadedFrom = 'database';

        return true;
    }

    /**
     * Load disposable domain lists from local files.
     */
    private function loadFromFiles(): void
    {
        if (!file_exists($this->blocklist_path)) {
            throw new Exception("Disposable email blocklist file not found: {$this->blocklist_path}");
        }

        $domains = file($this->blocklist_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $block = array_filter(
            array_map('trim', $domains),
            function ($line) {
                return !empty($line) && strpos($line, '#') !== 0;
            }
        );

        self::$blocklist = array_fill_keys(array_map('mb_strtolower', $block), true);

        if (file_exists($this->allowlist_path)) {
            $domains = file($this->allowlist_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $allow = array_filter(
                array_map('trim', $domains),
                function ($line) {
                    return !empty($line) && strpos($line, '#') !== 0;
                }
            );
            self::$allowlist = array_fill_keys(array_map('mb_strtolower', $allow), true);
        } else {
            self::$allowlist = [];
        }

        self::$loadedFrom = 'files';
    }

    /**
     * Check if email is from a disposable domain.
     */
    public function isDisposable(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $email = mb_strtolower(trim($email));
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }

        $domain = $parts[1];

        if (!empty(self::$allowlist) && isset(self::$allowlist[$domain])) {
            return false;
        }

        // Check full and parent domains for blocklist entries.
        $domain_parts = explode('.', $domain);
        for ($i = 0; $i < count($domain_parts) - 1; $i++) {
            $check_domain = implode('.', array_slice($domain_parts, $i));
            if (isset(self::$blocklist[$check_domain])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate email and return tuple [is_valid, message].
     */
    public function validate(string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Please provide a valid email address.'];
        }

        if ($this->isDisposable($email)) {
            return [false, 'Disposable or temporary email addresses are not allowed. Please use your permanent email address.'];
        }

        return [true, 'Email address is valid.'];
    }

    public function getStats(): array
    {
        return [
            'blocklist_path' => $this->blocklist_path,
            'allowlist_path' => $this->allowlist_path,
            'blocklist_count' => count(self::$blocklist ?? []),
            'allowlist_count' => count(self::$allowlist ?? []),
            'blocklist_loaded' => self::$blocklist !== null,
            'allowlist_loaded' => self::$allowlist !== null,
            'loaded_from' => self::$loadedFrom,
        ];
    }

    public function reload(): void
    {
        self::$blocklist = null;
        self::$allowlist = null;
        self::$loadedFrom = 'none';

        $loaded = false;
        if ($this->conn instanceof Database || $this->conn instanceof mysqli) {
            $loaded = $this->loadFromDatabase();
        }
        if (!$loaded) {
            $this->loadFromFiles();
        }
    }
}
