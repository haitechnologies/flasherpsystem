<?php
/**
 * UAE Geographic Data Constants
 * This file contains hardcoded UAE country and states data
 * Replacing the geo_countries and geo_states database tables
 */

// UAE Country Data
define('UAE_COUNTRY_ID', 56);
define('UAE_COUNTRY_NAME', 'United Arab Emirates');
define('UAE_COUNTRY_NAME_AR', 'الإمارات العربية المتحدة');
define('UAE_COUNTRY_ISO2', 'AE');
define('UAE_COUNTRY_ISO3', 'ARE');
define('UAE_COUNTRY_PHONECODE', '971');
define('UAE_COUNTRY_ALPHA3_CODE', 'ARE');

// UAE States/Emirates Data (English and Arabic)
const UAE_STATES = [
    3797 => [
        'id' => 3797,
        'name' => 'Ajman',
        'name_ar' => 'عجمان',
        'country_id' => 56,
        'state_name' => 'Ajman'
    ],
    4121 => [
        'id' => 4121,
        'name' => 'Abu Dhabi',
        'name_ar' => 'أبو ظبي',
        'country_id' => 56,
        'state_name' => 'Abu Dhabi'
    ],
    3798 => [
        'id' => 3798,
        'name' => 'Dubai',
        'name_ar' => 'دبي',
        'country_id' => 56,
        'state_name' => 'Dubai'
    ],
    4124 => [
        'id' => 4124,
        'name' => 'Fujairah',
        'name_ar' => 'الفجيرة',
        'country_id' => 56,
        'state_name' => 'Fujairah'
    ],
    4123 => [
        'id' => 4123,
        'name' => 'Ras Al Khaimah',
        'name_ar' => 'رأس الخيمة',
        'country_id' => 56,
        'state_name' => 'Ras Al Khaimah'
    ],
    3800 => [
        'id' => 3800,
        'name' => 'Sharjah',
        'name_ar' => 'الشارقة',
        'country_id' => 56,
        'state_name' => 'Sharjah'
    ],
    4122 => [
        'id' => 4122,
        'name' => 'Umm Al Quwain',
        'name_ar' => 'أم القيوين',
        'country_id' => 56,
        'state_name' => 'Umm Al Quwain'
    ],
];

/**
 * Get UAE state name by ID
 * Replaces: getTableAttr('state_name', DB::GEO_STATES, $state_id)
 * 
 * @param int $state_id
 * @return string
 */
function getUAEStateName($state_id) {
    if (empty($state_id)) return '';
    return UAE_STATES[$state_id]['name'] ?? '';
}

/**
 * Get UAE state name in Arabic by ID
 * 
 * @param int $state_id
 * @return string
 */
function getUAEStateNameAr($state_id) {
    if (empty($state_id)) return '';
    return UAE_STATES[$state_id]['name_ar'] ?? '';
}

/**
 * Get UAE country name
 * Replaces: getTableAttr('country_name', DB::GEO_COUNTRIES, $country_id)
 * 
 * @param int $country_id
 * @return string
 */
function getUAECountryName($country_id = null) {
    // Always return UAE since we only support one country
    return UAE_COUNTRY_NAME;
}

/**
 * Get UAE country name in Arabic
 * 
 * @param int $country_id
 * @return string
 */
function getUAECountryNameAr($country_id = null) {
    return UAE_COUNTRY_NAME_AR;
}

/**
 * Get UAE country alpha3 code
 * Replaces: getTableAttr('alpha3_code', DB::GEO_COUNTRIES, $country_id)
 * 
 * @param int $country_id
 * @return string
 */
function getUAECountryAlpha3Code($country_id = null) {
    return UAE_COUNTRY_ALPHA3_CODE;
}

/**
 * Get all UAE states as array
 * For dropdowns and selects
 * 
 * @return array
 */
function getAllUAEStates() {
    return UAE_STATES;
}

/**
 * Get UAE states for dropdown HTML
 * Replaces SELECT * FROM geo_states queries
 * 
 * @param int $selected_id
 * @return string HTML options
 */
function getUAEStatesDropdown($selected_id = null) {
    $html = '<option value="">Select Emirate</option>';
    foreach (UAE_STATES as $state) {
        $selected = ($selected_id == $state['id']) ? 'selected' : '';
        $html .= '<option value="' . $state['id'] . '" ' . $selected . '>' . $state['name'] . '</option>';
    }
    return $html;
}

/**
 * Get UAE states as result set for legacy while loop compatibility
 * Allows iteration: while ($row = getUAEStatesResult()->fetch_array())
 * 
 * @return object|array Returns UAE_STATES array wrapped for compatibility
 */
function getUAEStatesResult() {
    return (object) [
        'states' => array_values(UAE_STATES),
        'index' => 0,
        'fetch_array' => function() {
            static $index = 0;
            $states = array_values(UAE_STATES);
            if ($index < count($states)) {
                return $states[$index++];
            }
            $index = 0; // Reset for reuse
            return null;
        },
        'num_rows' => count(UAE_STATES)
    ];
}

/**
 * Get UAE country for dropdown (always returns UAE since single country)
 * 
 * @param int $selected_id
 * @return string HTML option
 */
function getUAECountryDropdown($selected_id = null) {
    $selected = ($selected_id == UAE_COUNTRY_ID || $selected_id == 1) ? 'selected' : '';
    return '<option value="' . UAE_COUNTRY_ID . '" ' . $selected . '>' . UAE_COUNTRY_NAME . '</option>';
}

/**
 * Backward compatibility function
 * Maps old getTableAttr calls for geo data
 */
function getGeoAttr($attr, $table, $id) {
    if (empty($id)) {
        return '';
    }

    $mysqli = $GLOBALS['DB']['MSQLI'] ?? null;
    if ($mysqli === null) {
        return '';
    }

    if (strpos($table, 'geo_states') !== false) {
        $map = [
            'state_name' => 'state',
            'name'       => 'state',
            'state'      => 'state',
            'state_ar'   => 'state_ar',
            'name_ar'    => 'state_ar',
            'slug'       => 'slug',
            'country_id' => 'country_id',
        ];
    } elseif (strpos($table, 'geo_countries') !== false) {
        $map = [
            'country_name' => 'country',
            'name'         => 'country',
            'country'      => 'country',
            'country_ar'   => 'country_ar',
            'name_ar'      => 'country_ar',
            'alpha3_code'  => 'alpha3_code',
            'alpha2_code'  => 'alpha2_code',
            'abbr'         => 'abbr',
            'dialing_code' => 'dialing_code',
            'slug'         => 'slug',
            'numeric_code' => 'numeric_code',
        ];
    } else {
        return '';
    }

    if (!isset($map[$attr])) {
        return '';
    }

    $column = $map[$attr];

    $stmt = $mysqli->prepare("SELECT `" . $column . "` FROM `" . $table . "` WHERE `id` = ?");
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_row() : null;
    $stmt->close();

    return ($row !== null && $row[0] !== null) ? (string)$row[0] : '';
}
