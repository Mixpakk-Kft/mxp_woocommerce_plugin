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
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(),
            'body' => $order,
            'cookies' => array(),
        ));

        if (is_wp_error($response)) 
        {
            return array('type' => 'error', 'msg' => $response->get_error_message());
        }

        $result = json_decode($response['body'], true);

        if (is_null($result))
        {
            return array('type' => 'error', 'msg' => 'Invalid Deliveo JSON');
        }

        $general_present = false;
        if (!empty($result['warnings']))
        {
            foreach ($result['warnings'] as $warning)
            {
                if ($warning['type'] === 'general')
                {
                    $result['msg'] = $warning['text'];
                    $general_present = true;
                    unset($result['warnings']);
                    break;
                }
            }
        }

        $meta_success = false;
        if (!($result['error_code'] !== 0 || $general_present === true))
        {
            $meta_success = update_post_meta($order_id, '_mixpakk_exported', 'true', '');
        }

        if ($result['error_code'] !== 0 || $general_present === true || $meta_success === false)
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

            if (!($result['error_code'] !== 0 || $general_present === true))
            {
                $result['type'] = 'warning';
                
                if (!isset($result['warnings']))
                {
                    $result['warnings'] = array();
                }

                $result['warnings'][]['text'] = "Párhuzamosan már fel lett adva!";
            }

            return $result;
        }

        if (empty($result['data'])) 
        {
            return $result;
        }

        //update_post_meta($order_id, '_mixpakk_exported', 'true');
        update_post_meta($order_id, '_group_code', $result['data'][0]);
        $this->api_settings_obj->set_delivered_order_status($order_id);

        $this->group_id = $result['data'][0];
        $this->api_package_get_url = $this->set_api_url('package/' . $this->group_id);
        $response_get = wp_remote_get($this->api_package_get_url);
        $resp_get = json_decode($response_get['body'], true);
        //var_dump($resp_get["data"][0]); exit;
        
        if (isset($resp_get["data"][0]["shipment_id"])) 
        {
            update_post_meta($order_id, '_mixpakk_synced', 'true');
            update_post_meta($order_id, '_shipment_id', $resp_get["data"][0]["shipment_id"]);
        }

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

    private function set_exported_metas($order_details)
    {

        if (isset($order_details['group_code'])) {
            update_post_meta($order_id, '_mixpakk_exported', 'true');
            update_post_meta($order_id, '_group_code', $order_details['group_code']);
        }

        $this->api_settings_obj->set_delivered_order_status($order_id);
    }

    private function set_synced_metas($order_details)
    {

        if (isset($order_details['shipment_id'])) {
            update_post_meta($order_id, '_mixpakk_synced', 'true');
            update_post_meta($order_id, '_shipment_id', $order_details['shipment_id']);
        }

    }

    public function session_start()
    {
        session_start();
    }
}
