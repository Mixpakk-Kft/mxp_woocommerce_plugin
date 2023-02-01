<?php

class Mixpakk_API
{

    public function __construct($api_settings_obj)
    {
        $this->api_settings_obj = $api_settings_obj;
        $this->api_settings = $this->api_settings_obj->get_mixpakk_settings();
        $this->groupid = get_metadata('post', $order_id, '_group_code', true );
        $this->licence = $this->api_settings['licence_key'];
        $this->api_key = $this->api_settings['api_key'];
        $this->api_url = 'https://api.deliveo.eu/[TYPE]?licence=[LICENCE]&api_key=[API_KEY]';
        $this->api_package_post_url = $this->set_api_url('package/create');
        $this->api_shipping_options_url = $this->set_api_url('delivery');
        $this->result_message = '';
        $this->admin_orders_url = get_bloginfo('url') . '/wp-admin/edit.php?post_type=shop_order';

        add_action('init', array($this, 'session_start'));
    }


    public function send_order_items($order_id, $order, $export_allowed)
    {
        set_time_limit(120);
        $response = wp_remote_post($this->api_package_post_url, array(
            'method' => 'POST',
            'timeout' => 90,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(),
            'body' => $order,
            'cookies' => array(),
        ));

        if (is_wp_error($response)) 
        {
            $order_o = new WC_Order($order_id);
            $order_o->add_order_note("MXP API: Deliveo kapcsolódási hiba - " . $response->get_error_message());
            $order_o->save();
            return array('type' => 'error', 'msg' => $response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) != 200)
        {
            $order_o = new WC_Order($order_id);
            $order_o->add_order_note("MXP API: Deliveo kapcsolódási hiba - HTTP hibakód: " . wp_remote_retrieve_response_code($response));
            $order_o->save();
            return array('type' => 'error', 'msg' => "HTTP hibakód: " . wp_remote_retrieve_response_code($response));
        }

        $result = json_decode($response['body'], true);

        if (is_null($result))
        {
            $order_o = new WC_Order($order_id);
            $order_o->add_order_note("MXP API: Deliveo hiba, hibás deliveo JSON válasz!");
            $order_o->save();
            return array('type' => 'error', 'msg' => "Hibás deliveo JSON válasz!");
        }

        $should_delete = false;
        $stock_error = false;
        if (!empty($result['warnings']))
        {
            foreach ($result['warnings'] as $warning)
            {
                if ($warning['type'] === 'general')
                {
                    if ($should_delete == false)
                    {
                        $result['msg'] = $warning['text'];
                        $should_delete = true;
                    }
                    else
                    {
                        $result['msg'] .= '; ' . $warning['text'];
                    }
                }
                elseif ($warning['type'] === 'quantity' && ($this->api_settings['no_send_on_no_stock'] ?? false) == true)
                {
                    $stock_error = true;
                }
            }

            if ($should_delete)
            {
                unset($result['warnings']);
            }
        }

        if (!$should_delete && $stock_error)
        {
            $result['msg'] = __('Egy vagy több termék nincs készleten.', 'mixpakk');
            $should_delete = true;
        }
        else
        {
            $stock_error = false;
        }

        $meta_success = false;
        if (!($result['error_code'] !== 0 || $should_delete === true))
        {
            $meta_success = update_post_meta($order_id, '_mixpakk_exported', 'true', '');
        }

        if ($result['error_code'] !== 0 || $should_delete === true || $meta_success === false)
        {
            $result['type'] = 'error';
            if (!empty($result['data']))
            {
                wp_remote_post($this->set_api_url('package/' . $result['data'][0] . '/delete'), array(
                    'method' => 'POST',
                    'timeout' => 90,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array(),
                    'cookies' => array(),
                ));
            }

            if (!($result['error_code'] !== 0 || $should_delete === true))
            {
                $result['type'] = 'warning';
                
                if (!isset($result['warnings']))
                {
                    $result['warnings'] = array();
                }

                $result['warnings'][]['text'] = "Párhuzamosan már fel lett adva!";
            }

            if ($result['type'] == 'error')
            {
                $order_o = new WC_Order($order_id);
                if ($stock_error)
                {
                    $order_o->update_status('wc-mxp-no-stock');
                }
                else
                {
                    $order_o->update_status('wc-mxp-fail');
                }
                $order_o->add_order_note("MXP API: Hiba a feladás során: " . $result['msg']);
                $order_o->save();
            }

            return $result;
        }

        if (empty($result['data'])) 
        {
            return $result;
        }

        //update_post_meta($order_id, '_group_code', $result['data'][0]);
        $order_o = new WC_Order($order_id);
        $order_o->update_meta_data('_group_code', $result['data'][0]);
        $order_o->save();
        $this->api_settings_obj->set_delivered_order_status($order_id);

        $this->group_id = $result['data'][0];
        $this->api_package_get_url = $this->set_api_url('package/' . $this->group_id);
        $response_get = wp_remote_get($this->api_package_get_url);
        $resp_get = json_decode($response_get['body'], true);

        return $result;
    }


    /** Get Shipping options by DELIVEO API */
    public function get_shipping_options()
    {
        $shipping_options = false;

        $result = json_decode(wp_remote_fopen($this->api_shipping_options_url));

        if (isset($result->data) && $result->type == 'success') {
            $shipping_options = $result->data;
        }
        return $shipping_options;
    }

    private function set_api_url($type)
    {
        $api_url = $this->api_url;

        return str_replace(array('[TYPE]', '[LICENCE]', '[API_KEY]'), array($type, $this->licence, $this->api_key), $api_url);
    }

    public function session_start()
    {
        session_start();
    }
}
