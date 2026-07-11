<?php /*

Copyright (c) 2008 Metathinking Ltd.

This file is part of Affiliates For All.

Affiliates For All is free software: you can redistribute it and/or
modify it under the terms of the GNU General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

Affiliates For All is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
General Public License for more details.

You should have received a copy of the GNU General Public License
along with Affiliates For All.  If not, see
<http://www.gnu.org/licenses/>.

*/

$admin_required = TRUE;
require_once '../lib/bootstrap.php';

$template = new Template('admin-overview');

$siteoverview = __('Site Overview');
$template->set('title', "$affiliate_programme_name: $siteoverview");
$template->set('currency', $currency);

$db = new Database();

if(isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK
        && is_uploaded_file($_FILES['file']['tmp_name'])) {
    $success = 0;
    $failure = 0;
    $payments = file($_FILES['file']['tmp_name']);
    foreach($payments as $payment) {
        $fields = preg_split("/\t/", rtrim($payment, "\r\n"));
        $identifiers = isset($fields[3]) ? preg_split('/_/', $fields[3]) : array();
        $amount = isset($fields[1]) ? $fields[1] : '';
        $id = isset($identifiers[1]) ? $identifiers[1] : '';
        if($amount && $id) {
            $db->insert('payments',
                array('affiliate' => $id, 'amount' => $amount));
            $success++;
        } else {
            $failure++;
        }
    }

    $template->set('message', '
        <div id="message" class="dialogue" title="Payment Upload">
          Upload completed.  '.$success.' payments created, '.$failure.'
          errors.
        </div>');
} elseif(isset($_FILES['file'])) {
    $template->set('message', '
        <div id="message" class="dialogue" title="Payment Upload">
          Please choose a payments file to upload.
        </div>');
} else {
    $template->set('message', '');
}

$stmt = $db->get_pdo()->query(
    'select sum(total), sum(commission) from orders ' .
    "where status in ('shipped', 'refunded', 'refund')");

$row = $stmt->fetch();
$template->set('total_orders', $row ? $db->format_currency($row[0]) : '0.00');
$template->set('total_commission',
    $row ? $db->format_currency($row[1]) : '0.00');

$pay_stmt = $db->get_pdo()->query('select sum(amount) from payments');
$pay_row = $pay_stmt->fetch();
$template->set('total_payments', $db->format_currency($pay_row[0]));
$template->set('total_payable', $db->format_currency($row[1] - $pay_row[0]));

$template->render();
