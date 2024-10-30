<?php

class Mixpakk
{
    public $mixpakk_settings_obj;
    public $mixpakk_settings;

    public function __construct($mixpakk_settings_obj)
    {
        $this->mixpakk_settings_obj   = $mixpakk_settings_obj;
        $this->mixpakk_settings       = $this->mixpakk_settings_obj->get_mixpakk_settings();

        add_action('admin_enqueue_scripts', array($this, 'footer_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'header_scripts'));
        add_action('wp_ajax_label_download', array($this, 'label_download'));
        add_action('wp_ajax_view_signature', array($this, 'view_signature'));
        add_action('wp_ajax_view_package_log', array($this, 'view_package_log'));
        add_action('wp_ajax_package_details', array($this, 'package_details'));
        add_action('wp_ajax_mixpakk_generate_labels', array($this, 'generate_labels'));

        add_action('admin_notices', array($this, 'mixpakk_success_msg'));
        
        // Custom Filters
        add_filter('mixpakk_order_filter_items', array($this, 'filterOrderItems'), 10, 2);

        add_filter('mixpakk_order_filter_shipping_data', array($this, 'filterShipping_VisztCsomagpontok'), 10, 2);
        add_filter('mixpakk_order_filter_shipping_data', array($this, 'filterShipping_PickPackCsomagpont'), 10, 2);
        add_filter('mixpakk_order_filter_shipping_data', array($this, 'filterShipping_SzathmariCsomagpont'), 10, 2);
        add_filter('mixpakk_order_filter_shipping_data', array($this, 'filterShipping_FoxPostCsomagpont'), 10, 2);
        add_filter('mixpakk_order_filter_shipping_data', array($this, 'filterShipping_SprinterPPP'), 10, 2);
        add_filter('mixpakk_order_filter_shipping_data', array($this, 'filterShipping_FurgeRomania'), 10, 2);
        
        add_action('mixpakk_submit', array($this, 'process_queue_item'), 10, 3);
    }

    public function footer_scripts($hook)
    {
        $post_type = get_query_var('post_type', '');
        wp_register_script('mixpakk-admin-js', MIXPAKK_DIR_URL . 'js/admin.js', array(), false, true);
        wp_enqueue_script('mixpakk-admin-js');

        if ($hook == 'woocommerce_page_mixpakk-settings') {
            wp_register_script('mixpakk-validation-js', MIXPAKK_DIR_URL . 'js/validation.js', array(), false, true);
            wp_enqueue_script('mixpakk-validation-js');

            wp_register_script('mixpakk-admin-js', MIXPAKK_DIR_URL . 'js/admin.js', array(), false, true);
            wp_enqueue_script('mixpakk-admin-js');
        }

        if ($hook == 'edit.php' && $post_type == 'shop_order') {
            wp_register_script('mixpakk-export-js', MIXPAKK_DIR_URL . 'js/export.js', array(), false, true);
            wp_enqueue_script('mixpakk-export-js');
        }
    }

    public function header_scripts()
    {
        wp_register_style('mixpakk-admin-css', MIXPAKK_DIR_URL . 'css/mixpakk.css', false, '1.0.0');
        wp_enqueue_style('mixpakk-admin-css');
    }

    /**
     * https://wordpress.org/plugins/woo-product-bundle/
     * https://woocommerce.com/products/product-bundles/
     */
    public function filterOrderItems($order_items, $order)
    {
        foreach ($order_items as $item_id => $item_data) 
        {
            // If item is a parent product of a bundle, ignore it and dont send it over.
            // https://wordpress.org/plugins/woo-product-bundle/
            // https://woocommerce.com/products/product-bundles/
            if ($item_data->get_meta('_bundle_group_mode') === 'parent' ||
                !empty($item_data->get_meta('_woosb_ids')))
            {
                $order->remove_item($item_id);
                unset($order_items[$item_id]);
            }
        }

        return $order_items;
    }

    /**
     * https://hu.wordpress.org/plugins/hungarian-pickup-points-for-woocommerce/
     */
    public function filterShipping_VisztCsomagpontok($customer_data, $order)
    {
        $shop_id = $order->get_meta('_vp_woo_pont_point_id', true);
        
        if (!empty($shop_id))
        {
            $customer_data['consignee'] = $order->get_billing_last_name() . ' ' . $order->get_billing_first_name() . ' ' . $order->get_billing_company();
            $customer_data['consignee_country'] = $order->get_billing_country();
            $customer_data['consignee_zip'] = $order->get_billing_postcode();
            $customer_data['consignee_city'] = $order->get_billing_city();
            $customer_data['consignee_address'] = $order->get_billing_address_1();
            $customer_data['consignee_apartment'] = $order->get_billing_address_2();
            $customer_data['shop_id'] = $shop_id;
        }
        
        return $customer_data;
    }

    /**
     * https://weboldalneked.eu/wordpress-bovitmenyek/
     */
    public function filterShipping_PickPackCsomagpont($customer_data, $order)
    {
        $ppp_id = $order->get_meta('_pickpack_package_point', true);
        $postapont_id = $order->get_meta('_postapont', true);
        $gls_id = $order->get_meta('_gls_package_point', true);
    
        $shop_id = $ppp_id . $postapont_id . $gls_id;

        if (!empty($shop_id))
        {
            $customer_data['shop_id'] = $shop_id;
        }
        
        return $customer_data;
    }

    /**
     * https://szathmari.hu/wordpress/15-csomagpont-bovitmeny-csomagkezeles-szallitas
     */
    public function filterShipping_SzathmariCsomagpont($customer_data, $order)
    {
        $shop_id = $order->get_meta('wc_selected_pont', true);

        if (!empty($shop_id) && preg_match('/\|(?<point>[^\|]+)$/', $shop_id, $matches, PREG_UNMATCHED_AS_NULL) === 1)
        {
            $customer_data['shop_id'] = $matches['point'];
        }

        return $customer_data;
    }

    /**
     * https://foxpost.hu/uzleti-partnereknek/integracios-segedlet#woocommerce-plugin
     */
    public function filterShipping_FoxPostCsomagpont($customer_data, $order)
    {
        $shop_id = $order->get_meta('_foxpost_wc_shipping_apt_id', true);
        
        if (!empty($shop_id))
        {
            $customer_data['consignee'] = $order->get_billing_last_name() . ' ' . $order->get_billing_first_name() . ' ' . $order->get_billing_company();
            $customer_data['consignee_country'] = $order->get_billing_country();
            $customer_data['consignee_zip'] = $order->get_billing_postcode();
            $customer_data['consignee_city'] = $order->get_billing_city();
            $customer_data['consignee_address'] = $order->get_billing_address_1();
            $customer_data['consignee_apartment'] = $order->get_billing_address_2();
            $customer_data['shop_id'] = $shop_id;
        }
        
        return $customer_data;
    }

    /**
     * https://www.sprinter.hu/segedanyagok/sprinter-pick-pack-pont-woocommerce-plugin/
     */
    public function filterShipping_SprinterPPP($customer_data, $order)
    {
        $shop_json = $order->get_meta('_sprinter_kivalasztott_pickpackpont', true);

        if (!empty($shop_json))
        {
            $shop_json = json_decode(str_replace("'", '"', $shop_json), true);
            if (!is_null($shop_json))
            {
                $customer_data['shop_id'] = $shop_json['shopCode'];
            }
        }
        
        return $customer_data;
    }

    /**
     * Pactic Romania order submission.
     * These orders need to have a romanian source location, otherwise the order sending request will fail.
     * In this case it's a Pactic location.
     * This might change and needs to be disabled if the carrier of Pactic changes down the line.
     */
    public function filterShipping_FurgeRomania($customer_data, $order)
    {
        // Delivery method ID has to be 52 and also the recipient's 
        if (($customer_data['consignee_country'] ?? '') == 'RO' && ($customer_data['delivery'] ?? null) == 52)
        {
            $customer_data['sender_country'] = 'RO'; 
            $customer_data['sender_zip'] = '417078';
            $customer_data['sender_city'] = 'Sântion';
            $customer_data['sender_address'] = 'Santion nr 702B';
            $customer_data['sender_apartment'] = 'C2';
        }

        return $customer_data;
    }
    
    public function process_queue_item($order_id, $shipping, $unit)
    {
        $order = wc_get_order($order_id);

        if ($order == false)
        {
            return;
        }

        try
        {
            $result = $this->send_by_api($order, $shipping, $unit);
            
            if ($result['type'] == "error") 
            {
                throw new \Mixpakk_Exception(sprintf('%2$s%3$s', $order_id, $result['msg'], (isset($result['field']) ? ': ' . $result['field'] : '')), false);
            }
            else
            {
                if (!empty($result['data']))
                {
                    $order->update_meta_data('_mixpakk_exported', 'true');
                    $order->update_meta_data('_group_code', $result['data'][0]);
                    $order->add_order_note(sprintf('MXP API: ' . __('Csomagadatok rögzítve %1$s azonosítóval.', 'mixpakk'), $result['data'][0]));
                    $order->save();
                }
                $this->mixpakk_settings_obj->set_delivered_order_status($order);
            }
        }
        catch (\Mixpakk_Exception $ex)
        {
            $order->add_order_note(__("MXP API: Hiba a feladás során: ", 'mixpakk') . $ex->getMessage());
            $order->save();

            if ($ex->doChangeStatus())
            {
                $order->update_status('wc-mxp-fail', 'MXP API:');
            }
            
            throw $ex;
        }
        catch (\RuntimeException $ex)
        {
            $order->add_order_note(__("MXP API: Hiba a feladás során: ", 'mixpakk') . $ex->getMessage() . ' ' . $ex->getFile() . ':' . $ex->getLine());
            $order->save();
            
            throw $ex;
        }
    }

    public function send_by_api($order, $shipping = null, $unit = null)
    {
        $settings = $this->mixpakk_settings;

        // Get the packaging unit for the order
        if (!is_null($unit)) 
        {
            $packaging_unit = $unit;
        } 
        else 
        {
            $packaging_unit = $this->getPackagingUnit($order);
        }

        if (empty($order->get_meta('_group_code', true, 'edit')))
        {
            $shipping     = $shipping ? $shipping : $settings['delivery'];
            $cod          = $this->get_cod($order);
            $insurance    = (int)$settings['insurance'];
            
            if ($insurance == 1) 
            {
                $insurance = $order->get_total();
            }

            $cartProducts = array();
            $comment      = $order->get_customer_note();

            try
            {
                $order_items = apply_filters('mixpakk_order_filter_items', $order->get_items(), $order);
            }
            catch (\Mixpakk_Exception $ex)
            {
                throw new \Mixpakk_Exception($ex->getMessage() . ' (filter: ' . $ex->getFile() . ':' . $ex->getLine() . ')', $ex->doChangeStatus(), true);
            }

            // Iterate every order item line
            foreach ($order_items as $item_id => $item_data) 
            {
                $product = $item_data->get_product();

                $sku = $product->get_sku();

                // Weight and size parameter processing
                $weight_divider = get_option('woocommerce_weight_unit') == 'g' ? 1000 : 1;
                $weight = $product->get_weight();
                if (empty($weight)) 
                {
                    $weight = 0.1;
                }
                else
                {
                    $weight = floatval($weight) / $weight_divider;
                }

                switch(get_option('woocommerce_dimension_unit'))
                {
                case 'mm':
                    $length_divider = 10;
                    break;
                case 'm':
                    $length_divider = 0.01;
                default:
                    $length_divider = 1;
                }

                $x = $product->get_width();
                if (empty($x)) {
                    $x = $settings['x'];
                }
                else
                {
                    $x = floatval($x) / $length_divider;
                }
                $y = $product->get_height();
                if (empty($y)) {
                    $y = $settings['y'];
                }
                else
                {
                    $y = floatval($y) / $length_divider;
                }
                $z = $product->get_length();
                if (empty($z)) {
                    $z = $settings['z'];
                }
                else
                {
                    $z = floatval($z) / $length_divider;
                }

                // Add the item the required times
                for ($i = 0; $i < $item_data->get_quantity(); $i++) 
                {
                    $cartProducts[] = array(
                        "x"          => $x ?: 1,
                        "y"          => $y ?: 1,
                        "z"          => $z ?: 1,
                        "weight"     => $weight ?: 1,
                        "customcode" => $this->get_customcode_id($order) ?: '',
                        "item_no"    => $sku ?: $product->get_name(),
                    );
                }
            }

            if (method_exists($order, 'get_shipping_phone'))
            {
                $phone = $order->get_shipping_phone();
            }
            if (empty($phone))
            {
                $phone = $order->get_billing_phone();
            }

            $package = 
            [
                'sender'               => $settings['sender'],
                'sender_country'       => $settings['sender_country_code'],
                'sender_zip'           => $settings['sender_zip'],
                'sender_city'          => $settings['sender_city'],
                'sender_address'       => $settings['sender_address'],
                'sender_apartment'     => $settings['sender_apartment'],
                'sender_phone'         => $settings['sender_phone'],
                'sender_email'         => $settings['sender_email'],
                'consignee'            => $order->get_shipping_last_name() . ' ' . $order->get_shipping_first_name() . ' ' . $order->get_shipping_company(),
                'consignee_country'    => $order->get_shipping_country(),
                'consignee_zip'        => $order->get_shipping_postcode(),
                'consignee_city'       => $order->get_shipping_city(),
                'consignee_address'    => $order->get_shipping_address_1(),
                'consignee_apartment'  => $order->get_shipping_address_2(),
                'consignee_phone'      => $phone,
                'consignee_email'      => $order->get_billing_email(),
                'delivery'             => $shipping,
                'priority'             => $settings['priority'],
                'optional_parameter_3' => $settings['saturday'],
                'optional_parameter_2' => $insurance,
                'cod'                  => $cod * ($settings['currency_multiplier'] ?? 1),
                'currency'             => $order->get_currency()/*get_option('woocommerce_currency')*/,
                'freight'              => $settings['freight'],
                'comment'              => $comment,
                'tracking'             => $this->get_tracking_id($order),
                'packaging_unit'       => $packaging_unit,
                'colli'                => $packaging_unit, // Deliveo update as of 2024 january
                'packages'             => $cartProducts,
                'API Connect Version'  => 'MXP Woocommerce Plugin v1.4.0',
            ];

            try
            {
                $package = apply_filters('mixpakk_order_filter_shipping_data', $package, $order);
            }
            catch (\Mixpakk_Exception $ex)
            {
                throw new \Mixpakk_Exception($ex->getMessage() . ' (filter: ' . $ex->getFile() . ':' . $ex->getLine() . ')', $ex->doChangeStatus(), true);
            }

            $mixpakk_api      = new Mixpakk_API($this->mixpakk_settings_obj);
            $mixpakk_progress = $mixpakk_api->send_order_items($order, $package);

            return $mixpakk_progress;
        }
    }

    public function getPackagingUnit(WC_Order $order_obj)
    {
        $packaging_unit = $this->mixpakk_settings['packaging_unit'];

        switch ((int) $packaging_unit) {
            case 0:
                return 1;
                break;
            case 1:
                return $order_obj->get_item_count();
                break;
            case 2:
                return false;
                break;
            default:
                return false;
                break;
        }
    }

    public function mixpakk_success_msg()
    {
        if (isset($_GET['mixpakk_ok'])) {
            echo '<div class="notice notice-success is-dismissible">
            <p><strong><h2>Mixpakk csomagfeladás befejezve</h2></strong> Sikeres: ' . $_GET['succeded'] . ', sikertelen: ' . $_GET['failed'] . '.</p>
            </div>';
        }
        if (isset($_GET['failed_messages'])) {
            foreach ($_GET['failed_messages'] as $msg) {
                echo '<div class="notice notice-error is-dismissible">
                <p>' . $msg . '</p>
                </div>';
            }
        }

        if (isset($_GET['warning_messages'])) {
            foreach ($_GET['warning_messages'] as $msg) {
                echo '<div class="notice notice-warning is-dismissible">
                <p>' . $msg . '</p>
                </div>';
            }
        }
    }

    /* if payment type is COD the cost will be the payment total */
    protected function get_cod(WC_Order $order_obj)
    {
        $ret = 0;

        if ($order_obj->get_payment_method() == 'cod') 
        {
            $ret = $order_obj->get_total();
        }

        return $ret;
    }

    /** If user check the customcode id equal to order id on Mixpakk settings, the function return with order id as customcode id */
    protected function get_customcode_id(WC_Order $order_obj)
    {
        $ret = '';

        if ($this->mixpakk_settings['customcode_id_is_order_id'] ?? false == true) 
        {
            $ret = '#' . $order_obj->get_order_number();
        }

        return $ret;
    }

    /** If user check the tracking id equal to order id on Mixpakk settings, the function return with order id as tracking id */
    protected function get_tracking_id(WC_Order $order_obj)
    {
        $ret = '';

        if ($this->mixpakk_settings['tracking_id_is_order_id'] ?? false == true) 
        {
            $ret = '#' . $order_obj->get_order_number();
        }

        return $ret;
    }

    public function label_download()
    {
        $settings    = $this->mixpakk_settings;
        $label_url   = "https://api.deliveo.eu/label/" . $_POST["group_id"] . "?licence=" . $settings["licence_key"] . "&api_key=" . $settings["api_key"] . "&format=" . $settings["print_format"];
        $package_url = "https://api.deliveo.eu/package/" . $_POST["group_id"] . "?licence=" . $settings["licence_key"] . "&api_key=" . $settings["api_key"];

        $tmpfile = download_url($label_url, $timeout = 300);

        $check_signature = json_decode(wp_remote_fopen($package_url), true);
        if ($check_signature['data'][0]['deliveo_id'] == $_POST["group_id"]) {
            $permfile = $_POST['group_id'] . '.pdf';
            $destfile = wp_get_upload_dir()['path'] . "/" . $_POST['group_id'] . '.pdf';
            $dest_url = wp_get_upload_dir()['url'] . "/" . $_POST['group_id'] . '.pdf';
            copy($tmpfile, $destfile);
            unlink($tmpfile);
            $package =
                '<a target="blank" href="' . $dest_url . '"><img title="Csomagcímke letöltése ehhez a csoportkódhoz: ' . $_POST['group_id'] . '"  style="vertical-align:middle;height:36px;" src="' . MIXPAKK_DIR_URL . 'images/mixpakk-package-barcode.png" ></a>';
            echo $package;
        } else {
            $package =
                '<img title="Csomagcímke nem található ehhez a csoportkódhoz: ' . $_POST['group_id'] . '"  style="vertical-align:middle;height:36px;filter:grayscale(100%);" src="' . MIXPAKK_DIR_URL . 'images/mixpakk-package-barcode.png" >';
            echo $package;
        }
        wp_die();
    }

    public function view_signature()
    {
        $settings      = $this->mixpakk_settings;
        $signature_url = "https://api.deliveo.eu/signature/" . $_POST["group_id"] . "?licence=" . $settings["licence_key"] . "&api_key=" . $settings["api_key"];

        $tmpfile = download_url($signature_url, $timeout = 300);

        $check_signature = json_decode(wp_remote_fopen($signature_url), true);
        if ($check_signature['type'] == 'error') {
            $response = array(
                "error" => "no_signature",
                "img"   => '<img title="Nem tartozik aláíráslap ehhez a csoportkódhoz: ' . $_POST['group_id'] . '"  style="vertical-align:middle;height:24px; filter: grayscale(100%);" src="' . MIXPAKK_DIR_URL . 'images/mixpakk-signature.png" ></a>',
            );
            echo json_encode($response);
        } else {
            $permfile = $_POST['group_id'] . '_sign.pdf';
            $destfile = wp_get_upload_dir()['path'] . "/" . $_POST['group_id'] . '_sign.pdf';
            $dest_url = wp_get_upload_dir()['url'] . "/" . $_POST['group_id'] . '_sign.pdf';
            copy($tmpfile, $destfile);
            unlink($tmpfile);
            $package = array(
                'url' => $dest_url,
                'img' => '<a href="' . $dest_url . '" target="blank"><img title="Aláíráslap letöltése ehhez a csoportkódhoz: ' . $_POST['group_id'] . '"  style="vertical-align:middle;height:24px;" src="' . MIXPAKK_DIR_URL . 'images/mixpakk-signature.png" ></a>',
            );
            echo json_encode($package);
        }
        wp_die();
    }

    public function view_package_log()
    {
        $settings        = $this->mixpakk_settings;
        $package_log_url = "https://api.deliveo.eu/package_log/" . $_POST["group_id"] . "?licence=" . $settings["licence_key"] . "&api_key=" . $settings["api_key"];

        $package_log = json_decode(wp_remote_fopen($package_log_url), true)['data'];

        if (isset($package_log[0])) {
            $row = '<h4>Csomagnapló</h4>';
        } else {
            $row = '<h4 style="background-color:red">A csomagnapló nem található</h4>';
        }

        foreach ($package_log as $entry) {
            $status =
            $row .= '<div align="left" class="row">';
            $row .= date('Y-m-d H:i', $entry['timestamp']) . '<br>';
            $row .= $this->displayStatus($entry['status'], $entry['status_text']);
            $row .= '</div>';
            // unset($row);
            $row .= '</div>';
        }
        echo $row;

        wp_die();
    }

    public function displayStatus($status, $status_text)
    {
        return $status_text != "" ? $status_text : $status;

    }

    public function package_details()
    {
        $settings        = $this->mixpakk_settings;
        $package_log_url = "https://api.deliveo.eu/package/" . $_POST["group_id"] . "?licence=" . $settings["licence_key"] . "&api_key=" . $settings["api_key"];
        // $signature_url = "https://api.deliveo.eu/signature/" . $_POST["group_id"] . "?licence=" . $settings["licence_key"] . "&api_key=" . $settings["api_key"];

        $package_details = json_decode(wp_remote_fopen($package_log_url), true)['data'];
        if (isset($package_details[0])) {

            if ($package_details[0]['dropped_off'] != null) {
                $delivered = 'Átvette (' . $package_details[0]['dropped_off'] . ')';
            } else {
                $delivered = 'A csomag átvétele még nem történt meg!';
            }

            echo '<div class="content">' . $delivered . '<br></div>';
            echo '<div class="group_info" style="background-image:url(' . MIXPAKK_DIR_URL . 'images/mixpakk-icon.png' . ')">';

            echo '<h4>Címzett</h4>';
            echo '<div class="content">[' . $package_details[0]['consignee_zip'] . '] ' . $package_details[0]['consignee_city'] . '</div>';
            echo '<div class="content">' . $package_details[0]['consignee_address'] . ' ' . $package_details[0]['consignee_apartment'] . '</div>';
            echo '<div class="content">' . $package_details[0]['consignee_phone'] . ' | <a href="mailto:' . $package_details[0]['consignee_email'] . '">' . $package_details[0]['consignee_email'] . '</a></div>';

            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="group_info" style="background-image:url(' . MIXPAKK_DIR_URL . 'images/mixpakk-icon.png' . ')">';
            echo '<h4>A csomag nem található a Mixpakk rendszerben!</h4>';
            echo '</div>';
        }
        wp_die();
    }

    public function generate_labels()
    {
        $output_json = array();
        $output_json['result'] = 0;
        $output_json['messages'] = array();
        $output_json['data'] = array();

        $request_url = "https://api.deliveo.eu/label?licence=" . $this->mixpakk_settings["licence_key"] . "&api_key=" . $this->mixpakk_settings["api_key"] . "&format=" . ($this->mixpakk_settings["print_format"] ?? 'A4');

        try
        {
            $group_ids = array();
            $order_ids = array();
            foreach ($_POST['post'] as $order_id)
            {
                $order_o = new WC_Order($order_id);
                $group_id = $order_o->get_meta('_group_code');
                if (!empty($group_id))
                {
                    $group_ids[] = $group_id;
                    $order_ids[] = $order_id;
                }
            }
    
            if (empty($group_ids))
            {
                throw new \Exception(__('Nincs rendelés kijelölve', 'mixpakk'));
            }
    
            $current_group_ids = array_slice($group_ids, 0, 25);
            $order_ids = array_slice($order_ids, 25);
            
            set_time_limit(300);

            $curl = curl_init($request_url . '&deliveo_ids=' . implode(',', $current_group_ids));

            curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 240);
            curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
    
            $result = curl_exec($curl);
    
            if (curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200)
            {
                throw new \Exception(__('Deliveo válasz nem 200!', 'mixpakk'));
            }

            $content_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

            curl_close($curl);
            
            if ($content_type != 'application/pdf')
            {
                $output_json['data']['remaining'] = $order_ids;
                throw new \Exception(__('Nincs címke!', 'mixpakk'));
            }

            $output_json['data']['content'] = base64_encode($result);
            $output_json['data']['remaining'] = $order_ids;
        }
        catch (\Exception $ex)
        {
            $output_json['result'] = 1;
            $output_json['messages'][] = array(
                'display_target' => 'message',
                'severity' => 'error',
                'message' => $ex->getMessage(),
            );
        }

        header('Content-Type: application/json');
        ?><?=json_encode($output_json)?><?php
        wp_die();
    }
}
