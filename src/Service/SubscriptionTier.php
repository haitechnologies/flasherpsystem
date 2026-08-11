<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;
use App\Core\Database;
use App\Core\DB;
use App\Core\ErrorCapture;
use Throwable;

class SubscriptionTier
{
    public const TIER_FREE = 'free';
    public const TIER_REGISTERED = 'registered';
    public const TIER_SILVER = 'silver';
    public const TIER_GOLD = 'gold';
    public const TIER_PLATINUM = 'platinum';

    public const TIER_PRO = self::TIER_GOLD;
    public const TIER_ENTERPRISE = self::TIER_PLATINUM;

    private static array $tierFeatures = [
        'free' => [
            'results_per_search' => 100,
            'saved_searches' => 5,
            'company_views_per_day' => 5,
            'csv_export_rows_per_month' => 0,
            'api_access' => false,
            'email_leads' => false,
            'phone_numbers' => false,
            'contact_emails' => false,
            'advanced_filters' => false,
            'direct_messaging' => false,
            'priority_support' => false,
            'bulk_operations' => false,
        ],
        'registered' => [
            'results_per_search' => 1000,
            'saved_searches' => 20,
            'company_views_per_day' => 20,
            'csv_export_rows_per_month' => 100,
            'api_access' => false,
            'email_leads' => false,
            'phone_numbers' => false,
            'contact_emails' => true,
            'advanced_filters' => true,
            'direct_messaging' => true,
            'priority_support' => false,
            'bulk_operations' => false,
        ],
        'silver' => [
            'results_per_search' => 5000,
            'saved_searches' => 9999,
            'company_views_per_day' => 9999,
            'csv_export_rows_per_month' => 9999,
            'api_access' => true,
            'email_leads' => true,
            'phone_numbers' => true,
            'contact_emails' => true,
            'advanced_filters' => true,
            'direct_messaging' => true,
            'priority_support' => false,
            'bulk_operations' => true,
        ],
        'gold' => [
            'results_per_search' => 25000,
            'saved_searches' => 9999,
            'company_views_per_day' => 9999,
            'csv_export_rows_per_month' => 9999,
            'api_access' => true,
            'email_leads' => true,
            'phone_numbers' => true,
            'contact_emails' => true,
            'advanced_filters' => true,
            'direct_messaging' => true,
            'priority_support' => true,
            'bulk_operations' => true,
        ],
        'platinum' => [
            'results_per_search' => 100000,
            'saved_searches' => 9999,
            'company_views_per_day' => 9999,
            'csv_export_rows_per_month' => 9999,
            'api_access' => true,
            'email_leads' => true,
            'phone_numbers' => true,
            'contact_emails' => true,
            'advanced_filters' => true,
            'direct_messaging' => true,
            'priority_support' => true,
            'bulk_operations' => true,
        ],
    ];

    private ?Database $db = null;

    public function __construct(?Database $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $container = Container::getInstance();
                if ($container->has(Database::class)) {
                    $this->db = $container->get(Database::class);
                }
            } catch (Throwable $e) {
            }
        }
    }

    public function getUserTier(?int $userId = null): string
    {
        if (!$userId || $this->db === null) {
            return self::TIER_FREE;
        }

        try {
            $row = $this->db->fetchOne(
                "SELECT subscription_tier, subscription_expires_at 
                 FROM " . DB::CUSTOMERS . " 
                 WHERE id = :id",
                ['id' => $userId]
            );
        } catch (Throwable $e) {
            ErrorCapture::record("SubscriptionTier::getUserTier() - Fallback to registered tier: " . $e->getMessage());
            return self::TIER_REGISTERED;
        }

        if (!$row) {
            return self::TIER_REGISTERED;
        }

        $tier = self::normalizeTier($row['subscription_tier'] ?? self::TIER_REGISTERED);

        if (!in_array($tier, [self::TIER_FREE, self::TIER_REGISTERED], true) && !empty($row['subscription_expires_at'])) {
            if (strtotime($row['subscription_expires_at']) < time()) {
                $this->setUserTier($userId, self::TIER_REGISTERED);
                return self::TIER_REGISTERED;
            }
        }

        return $tier;
    }

    public function setUserTier(int $userId, string $newTier, ?string $expiresAt = null): bool
    {
        $normalizedTier = self::normalizeTier($newTier);
        if ($normalizedTier === self::TIER_REGISTERED && !in_array($newTier, [self::TIER_REGISTERED, 'registered'], true)) {
            ErrorCapture::record("SubscriptionTier::setUserTier() - Invalid tier: $newTier");
            return false;
        }

        if ($this->db === null) {
            return false;
        }

        $oldTier = $this->getUserTier($userId);

        try {
            $this->db->execute(
                "UPDATE " . DB::CUSTOMERS . " 
                 SET subscription_tier = :tier, subscription_expires_at = :expires 
                 WHERE id = :id",
                [
                    'tier' => $normalizedTier,
                    'expires' => $expiresAt,
                    'id' => $userId
                ]
            );

            if ($oldTier !== $normalizedTier) {
                $this->logTierChange($userId, $oldTier, $normalizedTier);
            }

            return true;
        } catch (Throwable $e) {
            ErrorCapture::record("SubscriptionTier::setUserTier() - Update error: " . $e->getMessage());
            return false;
        }
    }

    public static function getFeatureValue(string $tier, string $feature): mixed
    {
        $tier = self::normalizeTier($tier);
        return self::$tierFeatures[$tier][$feature] ?? null;
    }

    public function hasFeature(int|string $userIdOrTier, string $feature): bool
    {
        if (is_string($userIdOrTier)) {
            $tier = self::normalizeTier($userIdOrTier);
        } else {
            $tier = $this->getUserTier((int)$userIdOrTier);
        }

        $value = self::getFeatureValue($tier, $feature);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value > 0;
        }

        return false;
    }

    public static function getTierFeatures(string $tier): array
    {
        $tier = self::normalizeTier($tier);
        return self::$tierFeatures[$tier] ?? self::$tierFeatures[self::TIER_FREE];
    }

    public static function getPricing(): array
    {
        return [
            self::TIER_FREE => [
                'name' => 'Free',
                'price' => 0,
                'billing_period' => null,
                'description' => '100 results per search',
                'recommended' => false,
            ],
            self::TIER_REGISTERED => [
                'name' => 'Free Account',
                'price' => 0,
                'billing_period' => null,
                'description' => '1,000 results per search (logged in)',
                'recommended' => false,
            ],
            self::TIER_SILVER => [
                'name' => 'Silver',
                'price' => 50,
                'billing_period' => 'month',
                'description' => '5,000 results per search',
                'recommended' => false,
            ],
            self::TIER_GOLD => [
                'name' => 'Gold',
                'price' => 150,
                'billing_period' => 'month',
                'description' => '25,000 results per search',
                'recommended' => true,
            ],
            self::TIER_PLATINUM => [
                'name' => 'Platinum',
                'price' => 250,
                'billing_period' => 'month',
                'description' => '100,000 results per search',
                'recommended' => false,
            ],
        ];
    }

    public static function getTrialDays(): int
    {
        return 14;
    }

    public static function getTrialExpiration(): string
    {
        $expirationTime = time() + (self::getTrialDays() * 86400);
        return date('Y-m-d H:i:s', $expirationTime);
    }

    private function logTierChange(int $userId, string $tierFrom, string $tierTo): void
    {
        if ($this->db === null) {
            return;
        }

        try {
            $this->db->execute(
                "INSERT INTO " . DB::SUBSCRIPTION_LOGS . " 
                 (customer_id, tier_from, tier_to, changed_at) 
                 VALUES (:userId, :tierFrom, :tierTo, NOW())",
                [
                    'userId' => $userId,
                    'tierFrom' => $tierFrom,
                    'tierTo' => $tierTo
                ]
            );
        } catch (Throwable $e) {
            ErrorCapture::record("SubscriptionTier::logTierChange() - Error logging tier change: " . $e->getMessage());
        }
    }

    private static function normalizeTier(?string $tier): string
    {
        $tier = strtolower(trim((string)$tier));

        if ($tier === 'pro') {
            return self::TIER_GOLD;
        }
        if ($tier === 'enterprise') {
            return self::TIER_PLATINUM;
        }

        if (in_array($tier, [self::TIER_FREE, self::TIER_REGISTERED, self::TIER_SILVER, self::TIER_GOLD, self::TIER_PLATINUM], true)) {
            return $tier;
        }

        return self::TIER_REGISTERED;
    }

    public function isOnTrial(int $userId): bool
    {
        $tier = $this->getUserTier($userId);

        if (!in_array($tier, [self::TIER_SILVER, self::TIER_GOLD, self::TIER_PLATINUM], true)) {
            return false;
        }

        if ($this->db === null) {
            return false;
        }

        try {
            $row = $this->db->fetchOne(
                "SELECT created_at FROM " . DB::CUSTOMERS . " WHERE id = :id",
                ['id' => $userId]
            );
        } catch (Throwable $e) {
            return false;
        }

        if (!$row) {
            return false;
        }

        $trialDays = self::getTrialDays();
        $trialEndTime = strtotime($row['created_at']) + ($trialDays * 86400);

        return time() < $trialEndTime;
    }

    public static function getUpgradeOptions(string $currentTier): array
    {
        $upgrades = [];
        $currentTier = self::normalizeTier($currentTier);

        switch ($currentTier) {
            case self::TIER_FREE:
                $upgrades[] = self::TIER_REGISTERED;
                $upgrades[] = self::TIER_SILVER;
                $upgrades[] = self::TIER_GOLD;
                $upgrades[] = self::TIER_PLATINUM;
                break;
            case self::TIER_REGISTERED:
                $upgrades[] = self::TIER_SILVER;
                $upgrades[] = self::TIER_GOLD;
                $upgrades[] = self::TIER_PLATINUM;
                break;
            case self::TIER_SILVER:
                $upgrades[] = self::TIER_GOLD;
                $upgrades[] = self::TIER_PLATINUM;
                break;
            case self::TIER_GOLD:
                $upgrades[] = self::TIER_PLATINUM;
                break;
            case self::TIER_PLATINUM:
                break;
        }

        $pricing = self::getPricing();
        $result = [];

        foreach ($upgrades as $tier) {
            $result[$tier] = $pricing[$tier];
        }

        return $result;
    }
}
