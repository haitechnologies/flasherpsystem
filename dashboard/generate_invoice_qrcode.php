<?php
require_once __DIR__ . '/admin_elements/error_handler_init.php';
session_start();
header("Content-Type: image/png");
require '../config/globals.php';
require '../config/database.php';
include('../config/images.php');
include('admin_elements/grab_vars.php');

require "../vendor/autoload.php";
use App\Core\DB;
use Endroid\QrCode\QrCode;

$qrcode_filename = '';
$qrcode_value = '';

$result_invoices = $mysqli->query("SELECT id FROM `" . DB::INVOICES . "` WHERE qrcode ='' OR qrcode is NULL ORDER BY id DESC");
$total_empty_qrcodes = $result_invoices->num_rows;

if ($total_empty_qrcodes > 0) {
    $row_invoices = $result_invoices->fetch_array();

    $id = $row_invoices['id'];
    $encrypted_qrcode = hash('sha256', rand(1, 99) . $id);

    $salt = '}#f4ga~g%7hjg4&jokho!bj30ab-wi=6gia^7-$^R9F|GaK5Jzxs#E6WT;IOJN';
    $encrypted_filename = hash('sha256', $salt . $id);

    $qrcode_value = "https://www.haitechnologies.com/pdfs/index.php?pdf_invoice=" . $encrypted_filename;

    $qrCode = new QrCode($qrcode_value);
    echo $qrCode->writeString();

    $qrcode_filename = $encrypted_qrcode;
    $qrcode_dir = __DIR__ . '/../qrcodes_invoices';
    if (!is_dir($qrcode_dir)) { mkdir($qrcode_dir, 0755, true); }
    file_put_contents("../qrcodes_invoices/" . $qrcode_filename . '.png', $qrCode->writeString());

    $mysqli->query("UPDATE `" . DB::INVOICES . "` SET qrcode = '" . $qrcode_filename . "' WHERE id=$id");
}
