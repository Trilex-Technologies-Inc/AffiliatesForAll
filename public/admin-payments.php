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

class Payments extends Template {
    private function make_date_time_field($details) {
        echo '<div id="details_'.$details[0].'" ' .
            'class="detailsdate detailsfield input-group">';
        echo '<input class="date form-control" type="date"> ';
        echo '<input class="hours form-control" type="text" size="2">';
        echo '<span class="input-group-text">:</span>';
        echo '<input class="minutes form-control" type="text" size="2">';
        echo '<span class="input-group-text">:</span>';
        echo '<input class="seconds form-control" type="text" size="2">';
        echo '</div>';
    }

    private function make_text_field($details) {
        echo '<input id="details_'.$details[0].'" ' .
            'class="detailsfield form-control" type="text" ' .
            'size="'.$details[2].'">';
    }

    protected function make_data_field($details) {
        if($details[0] == 'date_entered') {
            echo $this->make_date_time_field($details);
        } else {
            echo $this->make_text_field($details);
        }
    }
}

$template = new Payments('admin-payments');

$template->set('fields', new Editor(
    Database::$payment_fields, Database::$payment_headings,
    Database::$payment_sizes));

$allpayments = __('All Payments');
$template->set('title', "$affiliate_programme_name: $allpayments");
$template->render();
