<?php

class Mixpakk_API
{
    public $api_settings_obj;
    public $api_settings;
    public $licence;
    public $api_key;
    public $api_url;
    public $api_package_post_url;
    public $api_shipping_options_url;

    public function __construct($api_settings_obj)
    {
        $this->api_settings_obj = $api_settings_obj;
        $this->api_settings = $this->api_settings_obj->get_mixpakk_settings();
        $this->licence = $this->api_settings['licence_key'];
        $this->api_key = $this->api_settings['api_key'];
        $this->api_url = 'https://api.deliveo.eu/[TYPE]?licence=[LICENCE]&api_key=[API_KEY]';
        $this->api_package_post_url = $this->set_api_url('package/create');
        $this->api_shipping_options_url = $this->set_api_url('delivery');
    }

    public function send_order_items($order, $post_body)
    {
        set_time_limit(120);
        $response = wp_remote_post($this->api_package_post_url, array(
            'method' => 'POST',
            'timeout' => 90,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(),
            'body' => $post_body,
            'cookies' => array(),
        ));

        if (is_wp_error($response)) 
        {
            return array('type' => 'error', 'msg' => "Deliveo kapcsolódási hiba - " . $response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) != 200)
        {
            return array('type' => 'error', 'msg' => "Deliveo kapcsolódási hiba - HTTP hibakód: " . wp_remote_retrieve_response_code($response));
        }

        $result = json_decode($response['body'], true);

        if (is_null($result))
        {
            return array('type' => 'error', 'msg' => "Hibás deliveo JSON válasz!");
        }

        $should_delete = false;
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
            }

            if ($should_delete)
            {
                unset($result['warnings']);
            }
        }

        if ($result['error_code'] !== 0 || $should_delete === true)
        {
            $result['type'] = 'error';
            if (!empty($result['data']))
            {
                $this->delete_order($result['data'][0]);
            }

            if ($result['type'] == 'error')
            {
                if ($result['error_code'] == 4014)
                {
                    $order->update_status('wc-mxp-no-stock', 'MXP API:');
                }
                else
                {
                    $order->update_status('wc-mxp-fail', 'MXP API:');
                }
            }

            return $result;
        }

        if (empty($result['data'])) 
        {
            return $result;
        }

        //update_post_meta($order_id, '_group_code', $result['data'][0]);
        //$order->update_meta_data('_mixpakk_exported', 'true');
        //$order->update_meta_data('_group_code', $result['data'][0]);
        //$order->save();
        //$this->api_settings_obj->set_delivered_order_status($order);

        return $result;
    }

    public function delete_order($group_id)
    {
        $response = wp_remote_post($this->set_api_url('package/' . $group_id . '/delete'), array(
            'method' => 'POST',
            'timeout' => 90,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(),
            'cookies' => array(),
        ));

        if (is_wp_error($response)) 
        {
            return false;
        }

        if (wp_remote_retrieve_response_code($response) != 200)
        {
            return false;
        }

        $result = json_decode($response['body'], true);

        if (is_null($result))
        {
            return false;
        }

        if ($result['error_code'] !== 0)
        {
            return false;
        }

        return true;
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
}
