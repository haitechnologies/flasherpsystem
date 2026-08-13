<?php

declare(strict_types=1);

use App\Core\DB;

/**
 * Shipping Customer Helper Functions
 *
 * Functions to manage shipping customer data (now stored in erp_customers with entity_type='shipping').
 */

/**
 * Parse customer information from Excel address field
 * Extracts phone number, city, country from address string
 *
 * @param string $address_string Full address from Excel
 * @return array Parsed components: address, phone, city, country
 */
function parseCustomerAddress($address_string) {
    $result = [
        'address' => '',
        'phone' => '',
        'city' => '',
        'country' => ''
    ];

    if (empty($address_string)) {
        return $result;
    }

    if (preg_match('/TL?:?\s*(\+[\d\s\-]+)/i', $address_string, $matches)) {
        $result['phone'] = trim($matches[1]);
        $address_string = preg_replace('/,?\s*TL?:?\s*\+[\d\s\-]+/i', '', $address_string);
    }

    $parts = array_map('trim', explode(',', $address_string));

    if (count($parts) > 0) {
        $last_part = end($parts);
        if (preg_match('/^[A-Z]{2}$/i', $last_part)) {
            $result['country'] = $last_part;
            array_pop($parts);
        }
    }

    foreach ($parts as $part) {
        if (preg_match('/\b(CITY|BAKU|ALMATY|DISTRICT)\b/i', $part)) {
            $result['city'] = $part;
            break;
        }
    }

    $result['address'] = implode(', ', $parts);

    return $result;
}

/**
 * Find or create shipping customer in database
 * Stores shipping customers in erp_customers with entity_type='shipping'
 *
 * @param mysqli $mysqli Database connection
 * @param string $customer_name Customer name from Excel
 * @param string $customer_address Full address string from Excel
 * @param int $organizationId Active organization ID
 * @return int Customer ID
 */
function findOrCreateShippingCustomer($mysqli, $customer_name, $customer_address = '', $organizationId = 1) {
    $tbl_name = DB::getPrefix() . 'customers';

    $customer_name = trim($customer_name);
    if (empty($customer_name)) {
        return 0;
    }

    $checkDup = $mysqli->prepare("SELECT id FROM `$tbl_name` WHERE display_name = ? AND entity_type = 'shipping' AND organization_id = ? LIMIT 1");
    $checkDup->bind_param('si', $customer_name, $organizationId);
    $checkDup->execute();
    $checkDup->store_result();

    if ($checkDup->num_rows > 0) {
        $result = $checkDup->get_result();
        $row = $result->fetch_assoc();
        $checkDup->close();
        return (int)$row['id'];
    }
    $checkDup->close();

    $parsed = parseCustomerAddress($customer_address);

    $stmt = $mysqli->prepare("INSERT INTO `$tbl_name` (
        organization_id, entity_type, display_name, company_name, email,
        phone, mobile, address, is_active, created_at, updated_at
    ) VALUES (?, 'shipping', ?, ?, '', ?, '', ?, 1, NOW(), NOW())");
    $stmt->bind_param('issss', $organizationId, $customer_name, $customer_name, $parsed['phone'], $parsed['address']);

    if ($stmt->execute()) {
        $id = $mysqli->insert_id;
        $stmt->close();
        return $id;
    }
    $stmt->close();

    return 0;
}

/**
 * Get shipping customer details by ID
 *
 * @param mysqli $mysqli Database connection
 * @param int $customer_id Customer ID
 * @return array|null Customer data or null if not found
 */
function getShippingCustomer($mysqli, $customer_id) {
    $tbl_name = DB::getPrefix() . 'customers';

    $stmt = $mysqli->prepare(
        "SELECT id, display_name AS customer_name, phone AS customer_phone, address AS customer_address, customer_type, is_active
         FROM `$tbl_name` WHERE id = ? AND entity_type = 'shipping' LIMIT 1"
    );
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Get shipping customer name by ID
 *
 * @param mysqli $mysqli Database connection
 * @param int $customer_id Customer ID
 * @return string Customer name or empty string
 */
function getShippingCustomerName($mysqli, $customer_id)
{
    $customer = getShippingCustomer($mysqli, $customer_id);
    return $customer ? $customer['customer_name'] : '';
}

/**
 * Get all shipping customers for dropdown
 *
 * @param mysqli $mysqli Database connection
 * @param bool $active_only Return only active customers
 * @return array Array of customers with id and customer_name
 */
function getAllShippingCustomers($mysqli, $active_only = true)
{
    $tbl_name = DB::getPrefix() . 'customers';

    $sql = "SELECT id, display_name AS customer_name, address AS customer_address
            FROM `$tbl_name`
            WHERE entity_type = 'shipping'";

    if ($active_only) {
        $sql .= " AND is_active = 1";
    }

    $sql .= " ORDER BY display_name ASC";

    $result = $mysqli->query($sql);
    $customers = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
    }

    return $customers;
}
