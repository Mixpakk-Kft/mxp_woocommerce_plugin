<?php

class Mixpakk_Filter
{

    public function __construct()
    {
        // $this->mixpakk_settings_obj = new Mixpakk_Settings();
        add_action('wp_login', array($this, 'updateShippingOptions'));

        add_action('restrict_manage_posts', array($this, 'not_exported_products_filter'));
        add_action('pre_get_posts', array($this, 'apply_not_exported_products_filter'));
        add_filter('manage_edit-shop_order_columns', array($this, 'add_mixpakk_columns_header'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_order_group_code_column_content'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_order_packaging_column_content'));
        add_action('admin_footer-edit.php', array($this, 'custom_bulk_admin_footer'));
        add_action('load-edit.php', array($this, 'custom_bulk_action'));

        add_filter('post_class', function ($classes) {
            $classes[] = 'no-link';
            return $classes;
        });
    }

    public function updateShippingOptions()
    {
        $mixpakk_api = new Mixpakk_API(new Mixpakk_Settings());
        $options = new Mixpakk_Settings();
        // var_dump($options);
        $shipping_options = $mixpakk_api->get_shipping_options();

        $opts = [];
        if ($shipping_options) {
            foreach ($shipping_options as $opt) {
                $opts[] = [
                    'value' => $opt->value,
                    'description' => $opt->description,
                    'alias' => $opt->alias,
                    'shipping_default' => $opt->shipping_default,
                ];
            }
            $options->mixpakk_settings['shipping_options'] = $opts;

            $settings = $options;
            update_option('mixpakk_settings', json_encode($settings->mixpakk_settings));
        }
    }

    public function add_mixpakk_columns_header($columns)
    {

        $new_columns = array();
        $index = 0;

        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;

            if ($index == 1) {
                $new_columns['group_code'] = '<img style="vertical-align:middle" src="' . MIXPAKK_DIR_URL . 'images/mixpakk-icon.png" alt="GroupID" title="' . __('Amelyik rendelésnél ilyen ikont lát, az már fel lett adva Mixpakk API-n keresztül.', 'mixpakk') . '" /> Csoportkód';

                if (json_decode(get_option('mixpakk_settings'))->packaging_unit == 2) {
                    $new_columns['packaging_unit'] = __('Csomagolási egység', 'mixpakk');
                }
            }

            $index += 1;
        }

        return $new_columns;
    }

    public function add_order_group_code_column_content($column)
    {
        global $post;
        $order = new WC_Order($post->ID);
        
        if ($column == 'group_code') {
            $exported = get_metadata('post', $post->ID, '_mixpakk_exported', true);
            $group_code = get_metadata('post', $post->ID, '_group_code', true);

            if ($exported == 'true') {
                echo '<span class="mixpakk-cell" style="cursor:pointer;font-weight:600;color:#0073aa;" data-groupid="' . $group_code . '"> ' . $group_code . '</span>';
            } else {
                $mixpakk_api = new Mixpakk_API(new Mixpakk_Settings());
                $selected = '';
                $opts = json_decode(get_option('mixpakk_settings'))->shipping_options;
                echo "<input type='hidden' name='order[" . $post->ID . "][id]' value='" . $post->ID . "'>";
                echo '<select data-id="' . $post->ID . '" style="max-width:100%" name="order[' . $post->ID . '][option]" title="Válasszon szállítási módot">';

                $delivery_option_default = $mixpakk_api->api_settings_obj->mixpakk_settings["delivery"];
                $delivery_option_preferred = $delivery_option_default;

                if (!empty($mixpakk_api->api_settings_obj->mixpakk_settings["delivery_extra"]))
                {
                    $ppp_id = get_metadata('post', $post->ID, '_pickpack_package_point', true );
                    $postapont_id = get_post_meta( $post->ID, '_postapont', true);
                    $gls_id = get_post_meta( $post->ID, '_gls_package_point', true );
                
                    //$provider = get_post_meta($post->ID, '_vp_woo_pont_point_id', true);

                    $shop_id = $ppp_id . $postapont_id . $gls_id . get_post_meta($post->ID, '_vp_woo_pont_point_id', true);
                    
                    if (!empty($shop_id))
                    {
                        $delivery_option_preferred = $mixpakk_api->api_settings_obj->mixpakk_settings["delivery_extra"];
                    }
                }

                if (!empty($mixpakk_api->api_settings_obj->mixpakk_settings["delivery_abroad"]))
                {                    
                    if ($order->get_shipping_country() != 'HU')
                    {
                        $delivery_option_preferred = $mixpakk_api->api_settings_obj->mixpakk_settings["delivery_abroad"];
                    }
                }

                foreach ($opts as $option) {
                    if ($delivery_option_preferred == $option->value) {
                        $selected = 'selected';
                    } else {
                        $selected = '';
                    }
                    echo '<option  value="' . $option->value . '" ' . $selected . '>' . $option->description . '</option>';
                }
                echo '</select>';
            }
        }
    }

    // Csomagolási egység oszlop hozzáadása
    public function add_order_packaging_column_content($column)
    {
        global $post;

        $order = new WC_Order($post->ID);

        if ($column == 'packaging_unit') {
            $exported = get_metadata('post', $post->ID, '_mixpakk_exported', true);

            $packaking_unit = json_decode(get_option('mixpakk_settings'))->packaging_unit;
            if ($exported != 'true') {
                switch ((int)$packaking_unit) {
                    case 0:
                        echo "<input title='" . __('Csomagolási egység', 'mixpakk') . "' name='order[" . $post->ID . "][unit]' type='number' readonly value='1'>";
                        break;
                    case 1:
                        echo "<input title='" . __('Csomagolási egység', 'mixpakk') . "' name='order[" . $post->ID . "][unit]' type='number' readonly value='" . $order->get_item_count() . "'>";
                        break;
                    case 2:
                        echo "<input title='" . __('Csomagolási egység', 'mixpakk') . "' name='order[" . $post->ID . "][unit]' type='number' value='" . $order->get_item_count() . "' max='" . $order->get_item_count() . "' min='1'>";
                        break;
                    default:
                        echo "<input title='" . __('Csomagolási egység', 'mixpakk') . "' type='number' value='" . $order->get_item_count() . "' max='" . $order->get_item_count() . "' min='1'>";
                        break;
                }
            }
        }
    }

    /* Order filter functions */
    public function not_exported_products_filter($post_type)
    {
        if (isset($_GET['post_type'])) {
            $post_type = $_GET['post_type'];
        }

        $selected_1 = '';
        $selected_2 = '';
        $selected_3 = '';
        $selected_4 = '';

        if (isset($_GET['mixpakk_exported'])) {
            switch ($_GET['mixpakk_exported']) {
                case 'not_exported':
                    $selected_1 = 'selected';
                    break;
                case 'exported':
                    $selected_2 = 'selected';
                    break;
                case 'not_synced':
                    $selected_3 = 'selected';
                    break;
                case 'synced':
                    $selected_4 = 'selected';
                    break;      
            }
        }

        if ($post_type == 'shop_order') {
            echo '
			<select name="mixpakk_exported">
				<option value="">' . __('Mixpakk szűrés: mind', 'mixpakk') . '</option>
				<option value="not_exported" ' . $selected_1 . '>' . __('Feladatlan', 'mixpakk') . '</option>
				<option value="exported" ' . $selected_2 . '>' . __('Feladott', 'mixpakk') . '</option>
                <option value="not_synced" ' . $selected_3 . '>' . __('Nem szinkronizált', 'mixpakk') . '</option>
				<option value="synced" ' . $selected_4 . '>' . __('Szinkronizált', 'mixpakk') . '</option>
			</select>';
        }
    }



    /* In the Orders admin page when the mixpakk filter was applied, this query filter will working  */
    public function apply_not_exported_products_filter($query)
    {
        global $pagenow;
        $meta_key_query = array();

        if ($query->is_admin && $pagenow == 'edit.php' && isset($_GET['mixpakk_exported']) && sanitize_text_field($_GET['mixpakk_exported']) != '' && sanitize_text_field($_GET['post_type']) == 'shop_order') {
          switch (sanitize_text_field($_GET['mixpakk_exported'])) {
              case 'not_exported':
                  $query_filters = array(
                        'key' => '_mixpakk_exported',
                        'compare' => 'NOT EXISTS',
                    );
                    break;

                case 'exported':
                    $query_filters = array(
                        'key' => '_mixpakk_exported',
                        'value' => 'true',
                    );
                    break;
                case 'not_synced':
                    $query_filters = array(
                        'key' => '_mixpakk_synced',
                        'compare' => 'NOT EXISTS',
                    );
                    break;
                case 'synced':
                    $query_filters = array(
                        'key' => '_mixpakk_synced',
                        'value' => 'true',
                    );
                    break;
            }

            $meta_key_query = array($query_filters);

            if (count($meta_key_query) > 0) {
                $query->set('meta_query', $meta_key_query);
            }
        }
    }

    public function custom_bulk_admin_footer()
    {

        global $post_type;

        if ($_GET['post_type'] == 'shop_order') {
?>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    jQuery('select[name="action"]').append("<option style='font-weight:bold;' value='mixpakk_send'>Mixpakk API feladás</option>");
                });
            </script>
<?php
        }
    }
    function custom_bulk_action()
    {

        $wp_list_table = _get_list_table('WP_Posts_List_Table');
        $action = $wp_list_table->current_action();

        switch ($action) {
            case 'mixpakk_send':

                $mixpakk = new Mixpakk(new Mixpakk_Settings());

                ignore_user_abort(true);

                $succeded = 0;
                $failed = 0;
                $failed_message = [];
                $warning_message = [];

                foreach ($_GET['order'] as $order) {

                    if (connection_aborted() == 1)
                    {
                        die();
                    }

                    if (in_array($order['id'], $_GET['post'])) {
                        $send = $mixpakk->send_by_api((int) $order['id'], $order['option'], $order['unit']);

                        if ($send['type'] == "error") 
                        {
                            $failed++;
                            $failed_message[] = 'Rendelés ID: ' . $order['id'] . ' - ' . $send['msg'] . (isset($send['field']) ? ': ' . $send['field'] : '');

                            $order_o = new WC_Order($order['id']);
                            $order_o->update_status('wc-mxp-fail', "MXP API: Hiba a feladás során: " . $send['msg']);
                        }
                        else
                        {
                            $succeded++;
                            if (!empty($send['warnings']))
                            {
                                foreach ($send['warnings'] as $warning)
                                {
                                    $warning_message[] = 'Rendelés ID: ' . $order['id'] . ' - ' . $warning['text'] . (isset($warning['sku']) ? ': ' . $warning['sku'] : '');
                                }
                            }
                        }
                    }
                }
                $feedback = array(
                    'mixpakk_ok' => true,
                    'succeded' => $succeded,
                    'failed' => $failed,
                    'failed_messages' => $failed_message,
                    'warning_messages' => $warning_message,
                );

                // echo "<pre>";
                // var_dump($feedback);
                // die;

                $sendback = add_query_arg($feedback, wp_get_referer());

                break;
            case 'mixpakk_export':
                $mixpakk = new Mixpakk(new Mixpakk_Settings());
                $ids = [];
                if ($_GET['order']) {

                    foreach ($_GET['order'] as $order) {
                        $ids[] = $order['id'];
                    }
                    $mixpakk->generate_csv($ids);
                } else {
                }
                $sendback = add_query_arg(array(
                    'mixpakk_export_failed' => true
                ), wp_get_referer());
                // $sendback = add_query_arg(array('mixpakk_ok' => true, 'succeded' => $succeded, 'failed' => $failed), wp_get_referer());

                break;
            default:
                return;
        }

        wp_redirect($sendback);

        exit();
    }
}
