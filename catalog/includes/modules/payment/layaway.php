<?php



class layaway extends base {

    /**
     * $code determines the internal 'code' name used to designate "this" shipping module
     *
     * @var string
     */
    var $code;

    /**
     * $title is the displayed name for this shipping method
     *
     * @var string
     */
    var $title;

    /**
     * $description is a soft name for this shipping method
     *
     * @var string
     */
    var $description;

    /**
     * module's icon
     *
     * @var string
     */
    var $icon;

    /**
     * $enabled determines whether this module shows or not... during checkout.
     *
     * @var boolean
     */
    var $enabled;

    /**
     * constructor
     *
     * @return layaway
     */
    function layaway() {
        global $order;

        $this->code = 'layaway';
        $this->title = MODULE_PAYMENT_LAYAWAY_TEXT_TITLE . '<br/>' . MODULE_PAYMENT_LAYAWAY_CUSTOMER_DESCRIPTION;
        $this->description = MODULE_PAYMENT_LAYAWAY_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_LAYAWAY_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_LAYAWAY_STATUS == 'True') ? true : false);
        if ((int) MODULE_PAYMENT_LAYAWAY_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_LAYAWAY_ORDER_STATUS_ID;
        }
        if (is_object($order))
            $this->update_status();
        $this->email_footer = MODULE_PAYMENT_LAYAWAY_TEXT_EMAIL_FOOTER." \n ".$_SESSION['layaway_term'];
        $this->layaway_payment_lengths_list = array();
        $this->layaway_payment_lengths_list = explode(';', MODULE_PAYMENT_LAYAWAY_LENGTH_LIST);
        foreach ($this->layaway_payment_lengths_list as $id => $term) {
            $this->layaway_payment_lengths_list_dropdown[] = array(
                'id' => $id,
                'text' => $term
            );
        }
    }

    /**
     * Perform various checks to see whether this module should be visible
     */
    function update_status() {
        global $order, $db;
        if (!$this->enabled)
            return;
        if (IS_ADMIN_FLAG === true)
            return;

        if (isset($order->delivery) && (int) MODULE_PAYMENT_LAYAWAY_ZONE > 0) {
            $check_flag = false;
            $check = $db->Execute("SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES . "
                             WHERE geo_zone_id = '" . MODULE_PAYMENT_LAYAWAY_ZONE . "'
                             AND zone_country_id = '" . $order->delivery['country']['id'] . "'
                             ORDER BY zone_id");
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] == $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    /**
     * Check to see whether module is installed
     *
     * @return boolean
     */
    function check() {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_LAYAWAY_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        if ($this->_check > 0 && !defined('MODULE_PAYMENT_LAYAWAY_LENGTH_LIST')) {
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Pickup Locations', 'MODULE_PAYMENT_LAYAWAY_LENGTH_LIST', '3 Months; 6 Months; 12 Months', 'Enter a list of term lengths, separated by semicolons (;).', '6', '0', now())");
        }
        return $this->_check;
    }

    function javascript_validation() {
        return false;
    }

    function selection() {

        return array('id' => $this->code,
            'module' => $this->title,
            'fields' => array(array('title' => MODULE_PAYMENT_LAYAWAY_PAYMENT_LENGTH,
                    'field' => zen_draw_pull_down_menu('layaway_payment_term', $this->layaway_payment_lengths_list_dropdown, $this->layaway_payment_lengths_list_dropdown[0]['id'], 'id="layaway_dropdown"'),
                    'tag' => 'layaway_payment_term'))
        );
    }

    function pre_confirmation_check() {
        return false;
    }

    function confirmation() {
        $_SESSION['layaway_term'] = $this->layaway_payment_lengths_list[(int) $_POST['layaway_payment_term']];
        return array('title' => MODULE_PAYMENT_LAYAWAY_TEXT_DESCRIPTION,
            'fields' => array(array('title' => MODULE_PAYMENT_LAYAWAY_PAYMENT_LENGTH,
                    'field' => $this->layaway_payment_lengths_list[(int) $_POST['layaway_payment_term']])));
    }

    function process_button() {
        $this->selected_layaway_term = $this->layaway_payment_lengths_list[(int) $_POST['layaway_payment_term']];
        $this->title = MODULE_PAYMENT_LAYAWAY_PAYMENT_LENGTH."\n".$this->layaway_payment_lengths_list[(int) $this->selected_layaway_term]."\n".MODULE_PAYMENT_LAYAWAY_CUSTOMER_DESCRIPTION;
        return false;
    }

    function before_process() {
        return false;
    }

    function after_process() {
        global $insert_id, $db;
        $sql = "INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, customer_notified, date_added) values (:orderComments, :orderID, :orderStatus, -1, now() )";
        $sql = $db->bindVars($sql, ':orderComments', 'Layaway Term: ' . $_SESSION['layaway_term'], 'string');
        $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
        $sql = $db->bindVars($sql, ':orderStatus', $this->order_status, 'integer');
        $db->Execute($sql);
        return false;
    }

    function get_error() {
        return false;
    }

    /**
     * Install the shipping module and its configuration settings
     *
     */
    function install() {
        global $db;
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable Store Pickup Shipping', 'MODULE_PAYMENT_LAYAWAY_STATUS', 'True', 'Do you want to offer In Store rate shipping?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Pickup Locations', 'MODULE_PAYMENT_LAYAWAY_LENGTH_LIST', '3 Months; 6 Months; 12 Months', 'Enter a list of term lengths, separated by semicolons (;).', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Description at Checkout', 'MODULE_PAYMENT_LAYAWAY_CUSTOMER_DESCRIPTION', 'This breaks the cost of your order into monthly payments, your order will NOT ship until paid in full', 'Enter a description of your layway terms', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Shipping Zone', 'MODULE_PAYMENT_LAYAWAY_ZONE', '0', 'If a zone is selected, only enable this shipping method for that zone.', '6', '0', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort Order', 'MODULE_PAYMENT_LAYAWAY_SORT_ORDER', '0', 'Sort order of display.', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Order Status', 'MODULE_PAYMENT_LAYAWAY_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
    }

    /**
     * Remove the module and all its settings
     *
     */
    function remove() {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_PAYMENT\_LAYAWAY\_%'");
    }

    /**
     * Internal list of configuration keys used for configuration of the module
     *
     * @return array
     */
    function keys() {
        return array('MODULE_PAYMENT_LAYAWAY_STATUS', 'MODULE_PAYMENT_LAYAWAY_LENGTH_LIST', 'MODULE_PAYMENT_LAYAWAY_CUSTOMER_DESCRIPTION', 'MODULE_PAYMENT_LAYAWAY_ZONE', 'MODULE_PAYMENT_LAYAWAY_SORT_ORDER', 'MODULE_PAYMENT_LAYAWAY_ORDER_STATUS_ID');
    }

}
