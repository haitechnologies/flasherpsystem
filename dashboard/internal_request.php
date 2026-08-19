<?php

require_once __DIR__ . '/admin_elements/error_handler_init.php';

use App\Core\DB;
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

include_once('../config/globals.php');
include_once('../config/database.php');
include_once('admin_elements/error_logger.php');

require_once __DIR__ . '/../config/session.php';
startDashboardSession();

// Register custom error/exception/shutdown handlers for AJAX (returning JSON on exceptions/fatals)
if (function_exists('custom_error_handler')) {
    set_error_handler('custom_error_handler');
}

set_exception_handler(function (\Throwable $exception) {
    log_error('[AJAX:internal_request] Exception: ' . $exception->getMessage(), 'ERROR', $exception->getFile(), $exception->getLine(), [
        'module' => 'internal_request',
        'module_slug' => 'ajax',
        'stack_trace' => $exception->getTraceAsString(),
    ]);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        log_error('[AJAX:internal_request] Fatal Error: ' . $error['message'], 'CRITICAL', $error['file'], $error['line'], [
            'module' => 'internal_request',
            'module_slug' => 'ajax',
        ]);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
        exit;
    }
});


/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

$ajax_action = '';
if (isset($_REQUEST['ajax_action']) && !empty($_REQUEST['ajax_action'])) {
	$ajax_action = e_s__($_REQUEST['ajax_action']);
}

$csrf_write_actions = ['add_shipper', 'add_consignee', 'save_dimensions', 'delete_dimension_item'];
if (in_array($ajax_action, $csrf_write_actions)) {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
		exit;
	}
	if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
		echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
		exit;
	}
}


/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

switch ($ajax_action) {

 

		/*
		|--------------------------------------------------------------------------
		| 	Populate Services Drop Downs
		|--------------------------------------------------------------------------
		|
		*/
	case 'populate_services':

		$response = array();

		$result		= $mysqli->query("SELECT * FROM `" . $GLOBALS['TBL']['PREFIX'] . "items` WHERE is_active=1 AND item_type='services' ORDER BY item_name");

		// IF ROW EXISTS
		////////////////////////////////
		if (($result->num_rows >= 1)) {

			while ($row		= $result->fetch_array()) {
				if (!empty($row[0])) {

					$id				= s__($row['id']);
					$service_name	= s__($row['item_name']);

					$subArray['id'] 				= $id;
					$subArray['service_name'] 		= $service_name;

					$response[] =  $subArray;
				}
			} // while


		}

		echo json_encode($response);

		break;


 


	/*
	|--------------------------------------------------------------------------
	| 	Populate Item Rate
	|--------------------------------------------------------------------------
	|
	*/
	case 'populate_item_rate':

		$response = array();

		$item_id 	= '0';
		$row_no 	= '0';

		if (isset($_POST['item_id'])) {
			$item_id 	= e_s__($_POST['item_id']);
		}		
		if (isset($_POST['row_no'])) {
			$row_no 	= e_s__($_POST['row_no']);
		}		

			$result		= $mysqli->query("SELECT * FROM `" . tbl_items . "`  WHERE id=$item_id LIMIT 1");
			// --------------------------------
			// IF ROW EXISTS
			if (($result->num_rows >= 1)) {
	
				$row		= $result->fetch_array();
				if (!empty($row[0])) {
	
						$unit_price			= s__($row['unit_price']);
						
						$subArray['item_rate']		= $unit_price;
						$subArray['row_no'] 		= $row_no;
	
						$response =  $subArray;
					}
	
			}

			echo json_encode($response);

		break;
 

 


		/*
	|--------------------------------------------------------------------------
	| 	Populate Customers
	|--------------------------------------------------------------------------
	|
	*/
	case 'populate_customers':

		$new_pax 	= '';
		$old_pax 	= '';

		if (isset($_POST['new_pax'])) {
			$new_pax 	= e_s__($_POST['new_pax']);
		}

		if (isset($_POST['old_pax'])) {
			$old_pax 	= e_s__($_POST['old_pax']);
		}

		$arr = array('new_pax' => $new_pax, 'old_pax' => $old_pax);

		echo json_encode($arr);

		break;


	/*
	|--------------------------------------------------------------------------
	| 	Add Shipper
	|--------------------------------------------------------------------------
	|
	*/
	case 'add_shipper':

		$shipper_name 			= '';
		$shipper_address_line1 	= '';
		$shipper_address_line2 	= '';
		$shipper_city 			= '';
		$shipper_zipcode 		= '';
		$shipper_province 		= '';
		$shipper_country 		= '';
		$shipper_email 			= '';
		$shipper_telephone 		= '';
		$shipper_mobile 		= '';
		$shipper_fax 			= '';


		if (isset($_POST['shipper_name']) && !empty($_POST['shipper_name'])) {
			$shipper_name 	= e_s__($_POST['shipper_name']);
		}
		if (isset($_POST['shipper_address_line1']) && !empty($_POST['shipper_address_line1'])) {
			$shipper_address_line1 	= e_s__($_POST['shipper_address_line1']);
		}
		if (isset($_POST['shipper_address_line2']) && !empty($_POST['shipper_address_line2'])) {
			$shipper_address_line2 	= e_s__($_POST['shipper_address_line2']);
		}
		if (isset($_POST['shipper_city']) && !empty($_POST['shipper_city'])) {
			$shipper_city 	= e_s__($_POST['shipper_city']);
		}
		if (isset($_POST['shipper_zipcode']) && !empty($_POST['shipper_zipcode'])) {
			$shipper_zipcode 	= e_s__($_POST['shipper_zipcode']);
		}
		if (isset($_POST['shipper_province']) && !empty($_POST['shipper_province'])) {
			$shipper_province 	= e_s__($_POST['shipper_province']);
		}
		if (isset($_POST['shipper_country']) && !empty($_POST['shipper_country'])) {
			$shipper_country 	= e_s__($_POST['shipper_country']);
		}
		if (isset($_POST['shipper_email']) && !empty($_POST['shipper_email'])) {
			$shipper_email 	= e_s__($_POST['shipper_email']);
		}
		if (isset($_POST['shipper_telephone']) && !empty($_POST['shipper_telephone'])) {
			$shipper_telephone 	= e_s__($_POST['shipper_telephone']);
		}
		if (isset($_POST['shipper_mobile']) && !empty($_POST['shipper_mobile'])) {
			$shipper_mobile 	= e_s__($_POST['shipper_mobile']);
		}
		if (isset($_POST['shipper_fax']) && !empty($_POST['shipper_fax'])) {
			$shipper_fax 	= e_s__($_POST['shipper_fax']);
		}

		$id 	= '';

		$response = [
			'shipper_id'     => '',
			'shipper_name'   => '',
			'error_message'  => ''
		];

		if (checkDuplicateRow(tbl_shippers, 'shipper_name', $shipper_name) && $shipper_name != getTableAttr('shipper_name', tbl_shippers, $id)) {
			$response['error_message'] = 'Duplicate Shipper name. Please enter different.';
		
		} else if (!empty($shipper_name)) {

				$shipper_country	= (($shipper_country == '') ? 0 : $shipper_country);

				$result = $mysqli->query("INSERT INTO `" . tbl_shippers . "` (shipper_name, address_line1, address_line2, city, zipcode, province, country, email, telephone, mobile, fax) VALUES ('" . $shipper_name . "', '" . $shipper_address_line1 . "', '" . $shipper_address_line2 . "', '" . $shipper_city . "', '" . $shipper_zipcode . "', '" . $shipper_province . "', '" . $shipper_country . "', '" . $shipper_email . "', '" . $shipper_telephone . "', '" . $shipper_mobile . "', '" . $shipper_fax . "')");

					$id = $mysqli->insert_id;

					$response['shipper_id']   = $id;
					$response['shipper_name'] = $shipper_name;

		} else {
			$response['error_message'] = 'Shipper Name is required.';
		}

		echo json_encode($response);

		break;


	/*
	|--------------------------------------------------------------------------
	| 	Add Consignee
	|--------------------------------------------------------------------------
	|
	*/
	case 'add_consignee':

		$consignee_name 			= '';
		$consignee_address_line1 	= '';
		$consignee_address_line2 	= '';
		$consignee_city 			= '';
		$consignee_zipcode 		= '';
		$consignee_province 		= '';
		$consignee_country 		= '';
		$consignee_email 			= '';
		$consignee_telephone 		= '';
		$consignee_mobile 		= '';
		$consignee_fax 			= '';


		if (isset($_POST['consignee_name']) && !empty($_POST['consignee_name'])) {
			$consignee_name 	= e_s__($_POST['consignee_name']);
		}
		if (isset($_POST['consignee_address_line1']) && !empty($_POST['consignee_address_line1'])) {
			$consignee_address_line1 	= e_s__($_POST['consignee_address_line1']);
		}
		if (isset($_POST['consignee_address_line2']) && !empty($_POST['consignee_address_line2'])) {
			$consignee_address_line2 	= e_s__($_POST['consignee_address_line2']);
		}
		if (isset($_POST['consignee_city']) && !empty($_POST['consignee_city'])) {
			$consignee_city 	= e_s__($_POST['consignee_city']);
		}
		if (isset($_POST['consignee_zipcode']) && !empty($_POST['consignee_zipcode'])) {
			$consignee_zipcode 	= e_s__($_POST['consignee_zipcode']);
		}
		if (isset($_POST['consignee_province']) && !empty($_POST['consignee_province'])) {
			$consignee_province 	= e_s__($_POST['consignee_province']);
		}
		if (isset($_POST['consignee_country']) && !empty($_POST['consignee_country'])) {
			$consignee_country 	= e_s__($_POST['consignee_country']);
		}
		if (isset($_POST['consignee_email']) && !empty($_POST['consignee_email'])) {
			$consignee_email 	= e_s__($_POST['consignee_email']);
		}
		if (isset($_POST['consignee_telephone']) && !empty($_POST['consignee_telephone'])) {
			$consignee_telephone 	= e_s__($_POST['consignee_telephone']);
		}
		if (isset($_POST['consignee_mobile']) && !empty($_POST['consignee_mobile'])) {
			$consignee_mobile 	= e_s__($_POST['consignee_mobile']);
		}
		if (isset($_POST['consignee_fax']) && !empty($_POST['consignee_fax'])) {
			$consignee_fax 	= e_s__($_POST['consignee_fax']);
		}

		$id 	= '';

		$response = [
			'consignee_id'     => '',
			'consignee_name'   => '',
			'error_message'  => ''
		];

		if (checkDuplicateRow(tbl_consignees, 'consignee_name', $consignee_name) && $consignee_name != getTableAttr('consignee_name', tbl_consignees, $id)) {
			$response['error_message'] = 'Duplicate consignee name. Please enter different.';
		
		} else if (!empty($consignee_name)) {

				$consignee_country	= (($consignee_country == '') ? 0 : $consignee_country);

				$result = $mysqli->query("INSERT INTO `" . tbl_consignees . "` (consignee_name, address_line1, address_line2, city, zipcode, province, country, email, telephone, mobile, fax) VALUES ('" . $consignee_name . "', '" . $consignee_address_line1 . "', '" . $consignee_address_line2 . "', '" . $consignee_city . "', '" . $consignee_zipcode . "', '" . $consignee_province . "', '" . $consignee_country . "', '" . $consignee_email . "', '" . $consignee_telephone . "', '" . $consignee_mobile . "', '" . $consignee_fax . "')");

					$id = $mysqli->insert_id;

					$response['consignee_id']   = $id;
					$response['consignee_name'] = $consignee_name;

		} else {
			$response['error_message'] = 'Consignee Name is required.';
		}

		echo json_encode($response);

		break;


	/*
	|--------------------------------------------------------------------------
	| 	SELECT PORT COUNTRY
	|--------------------------------------------------------------------------
	|
	*/
	case 'select_port_country':

		$port_type 			= '';
		$port_id 			= '';


		if (isset($_POST['port_type']) && !empty($_POST['port_type'])) {
			$port_type 	= e_s__($_POST['port_type']);
		}
		if (isset($_POST['port_id']) && !empty($_POST['port_id'])) {
			$port_id 	= e_s__($_POST['port_id']);
		}

		$response = [
			'country_id'     => '',
			'country_name'   => '',
			'port_type'  	 => ''
		];

		if (!empty($port_type) && !empty($port_id)) {

				$country_id 	= getTableAttr('country_id', tbl_ports, $port_id);
				$country_name 	= getTableAttr('country_name', tbl_geo_countries, $country_id);

				$response['port_type'] 		= $port_type;
				$response['country_id']   	= $country_id;
				$response['country_name'] 	= $country_name;
		}

		echo json_encode($response);

		break;


	/*
	|--------------------------------------------------------------------------
	| SELECT COUNTRY PORTS
	|--------------------------------------------------------------------------
	*/
	case 'select_country_ports':

		$country_type = isset($_POST['country_type']) ? e_s__($_POST['country_type']) : '';
		$country_id   = isset($_POST['country_id']) ? (int)e_s__($_POST['country_id']) : 0;

		$response = [];

		if (!empty($country_type) && !empty($country_id)) {
			$query = "SELECT id, port_name, port_code 
                  FROM `" . tbl_ports . "` 
                  WHERE publish = 1 AND country_id = $country_id 
                  ORDER BY port_name ASC";

			$result = $mysqli->query($query);

			if ($result && $result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					$response[] = [
						'id'        => s__($row['id']),
						'port_name' => s__($row['port_name']),
						'port_code' => s__($row['port_code'])
					];
				}
			}
		}

		echo json_encode($response);
		break;


	/*
	|--------------------------------------------------------------------------
	| 	SELECT2 PORTS (Select2 AJAX search for port dropdowns)
	|--------------------------------------------------------------------------
	*/
	case 'select2_ports':

		$searchTerm = isset($_REQUEST['q']) ? s__((string)$_REQUEST['q']) : '';
		$countryId  = isset($_REQUEST['country_id']) ? (int)s__((string)$_REQUEST['country_id']) : 0;

		$results = [];

		$where  = "WHERE publish = 1 AND is_active = 1";
		$types  = '';
		$params = [];

		if ($countryId > 0) {
			$where   .= " AND country_id = ?";
			$types   .= 'i';
			$params[] = $countryId;
		}

		$orderBy = ' ORDER BY port_name ASC';
		if ($searchTerm !== '') {
			$exact   = addcslashes($searchTerm, '%_\\');
			$like    = $exact . '%';
			$where  .= " AND (port_name LIKE ? OR port_code LIKE ?)";
			$types  .= 'ss';
			$params[] = $like;
			$params[] = $like;
			$orderBy = ' ORDER BY CASE WHEN port_code = ? THEN 0 WHEN port_code LIKE ? THEN 1 ELSE 2 END, port_name ASC';
			$types  .= 'ss';
			$params[] = $exact;
			$params[] = $like;
		}

		$query = "SELECT id, port_name, port_code
		          FROM `" . tbl_ports . "`
		          " . $where . "
		          " . $orderBy . "
		          LIMIT 50";

		$stmt = $mysqli->prepare($query);

		if ($stmt) {
			if ($types !== '') {
				$stmt->bind_param($types, ...$params);
			}
			$stmt->execute();
			$result = $stmt->get_result();

			if ($result && $result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					$code = !empty($row['port_code']) ? s__($row['port_code']) : '';
					$label = $code !== '' ? $code . ' - ' . s__($row['port_name']) : s__($row['port_name']);
					$results[] = [
						'id'   => s__($row['id']),
						'text' => $label
					];
				}
			}
			$stmt->close();
		}

		echo json_encode([
			'results'    => $results,
			'pagination' => ['more' => false]
		]);
		break;


	/*
	|--------------------------------------------------------------------------
	| 	Get Subcategories by Category ID
	|--------------------------------------------------------------------------
	|
	*/
	case 'get_subcategories':

		$response = array('success' => false, 'data' => array());
		$category_id = 0;

		if (isset($_POST['category_id'])) {
			$category_id = (int)$_POST['category_id'];
		}

		if ($category_id > 0) {
			$result = $mysqli->query("SELECT id, subcategory FROM `" . DB::SUBCATEGORIES . "` WHERE is_active=1 AND category_id=" . $category_id . " ORDER BY subcategory");

			if ($result && $result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					$response['data'][] = array(
						'id' => $row['id'],
						'subcategory' => $row['subcategory']
					);
				}
				$response['success'] = true;
			}
		}

		echo json_encode($response);

		break;


	/*
	|--------------------------------------------------------------------------
	| 	Save Dimensions
	|--------------------------------------------------------------------------
	|
	*/
	case 'save_dimensions':

		$response = ['status' => '', 'saved_rows' => 0, 'item_ids' => [], 'error_message' => ''];
		$orgId = \App\Core\Session::orgId();
		$userId = \App\Core\Session::userId();
		$db = new \App\Core\Database();
		$dimensions = $_POST['dimensions'] ?? [];

		if (!empty($dimensions)) {
			foreach ($dimensions as $idx => $dim) {
				$module_type = $dim['module_type'] ?? 'sale_orders';
				$record_id = (int)($dim['record_id'] ?? $dim['sale_order_id'] ?? 0);
				$item_id = (int)($dim['item_id'] ?? 0);
				$pcs = (float)($dim['pcs'] ?? 0);
				$unit = $dim['unit'] ?? 'cm';
				$length = (float)($dim['length'] ?? 0);
				$width = (float)($dim['width'] ?? 0);
				$height = (float)($dim['height'] ?? 0);
				$formula = (int)($dim['formula'] ?? 6000);
				$cbm = (float)($dim['cbm'] ?? 0);
				$volume = (float)($dim['volume'] ?? 0);
				$row_no = $dim['row_no'] ?? $idx;

				if ($record_id > 0 && $pcs > 0 && $length > 0 && $width > 0 && $height > 0) {
					if ($item_id > 0) {
						$db->execute(
							"UPDATE `" . DB::DIMENSION_ITEMS . "` 
							 SET module_type = :module_type, record_id = :record_id,
							     pcs = :pcs, unit = :unit, length = :length, width = :width,
							     height = :height, formula = :formula, cbm = :cbm, volume = :volume,
							     updated_by = :updated_by, updated_at = NOW()
							 WHERE id = :id AND organization_id = :org_id",
							[
								'module_type' => $module_type, 'record_id' => $record_id,
								'pcs' => $pcs, 'unit' => $unit, 'length' => $length,
								'width' => $width, 'height' => $height, 'formula' => $formula,
								'cbm' => $cbm, 'volume' => $volume, 'updated_by' => $userId,
								'id' => $item_id, 'org_id' => $orgId,
							]
						);
						$response['item_ids'][] = ['row_no' => $row_no, 'id' => $item_id];
					} else {
						$newId = (int)$db->insert(
							"INSERT INTO `" . DB::DIMENSION_ITEMS . "` 
							 (organization_id, quotation_id, module_type, record_id,
							  pcs, unit, length, width, height, formula, cbm, volume,
							  created_by, updated_by, created_at, updated_at)
							 VALUES (:org_id, :quotation_id, :module_type, :record_id,
							         :pcs, :unit, :length, :width, :height, :formula, :cbm, :volume,
							         :created_by, :updated_by, NOW(), NOW())",
							[
								'org_id' => $orgId, 'quotation_id' => null,
								'module_type' => $module_type, 'record_id' => $record_id,
								'pcs' => $pcs, 'unit' => $unit, 'length' => $length,
								'width' => $width, 'height' => $height, 'formula' => $formula,
								'cbm' => $cbm, 'volume' => $volume,
								'created_by' => $userId, 'updated_by' => $userId,
							]
						);
						$response['item_ids'][] = ['row_no' => $row_no, 'id' => $newId];
					}
					$response['saved_rows']++;
				}
			}
		}

		$response['status'] = $response['saved_rows'] > 0 ? 'success' : 'no_data';
		echo json_encode($response);
		break;

	/*
	|--------------------------------------------------------------------------
	| 	Delete Dimension Item
	|--------------------------------------------------------------------------
	|
	*/
	case 'delete_dimension_item':

		$response = ['status' => '', 'id' => 0, 'row_no' => 0];
		$orgId = \App\Core\Session::orgId();

		$dim_id = (int)($_POST['id'] ?? 0);
		$row_no = $_POST['row_no'] ?? '';

		if ($dim_id > 0) {
			$db = new \App\Core\Database();
			$db->execute(
				"DELETE FROM `" . DB::DIMENSION_ITEMS . "` WHERE id = :id AND organization_id = :org_id",
				['id' => $dim_id, 'org_id' => $orgId]
			);
			$response['status'] = 'success';
			$response['id'] = $dim_id;
			$response['row_no'] = $row_no;
		} else {
			$response['status'] = 'error';
			$response['error_message'] = 'Invalid dimension ID';
		}

		echo json_encode($response);
		break;

}//switch
