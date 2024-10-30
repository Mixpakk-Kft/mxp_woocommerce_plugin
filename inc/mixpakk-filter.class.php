<?php

class Mixpakk_Filter
{
    protected $mixpakk_o = null;
    protected $mixpakk_settings_o = null;
    protected $queue_warning = false;

    public function __construct($mixpakk, $mixpakk_settings_obj)
    {
        $this->mixpakk_o = $mixpakk;
        $this->mixpakk_settings_o = $mixpakk_settings_obj;

        // On login query all the available shipping options available from deliveo
        add_action('wp_login', array($this, 'updateShippingOptions'));

        $hpos = false;

        if (get_option('woocommerce_custom_orders_table_enabled') === 'yes')
        {
            $hpos = true;
        }

        if ($hpos)
        {
            // Admin order table add columns (HPOS)
            add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_mixpakk_columns_header'), 1);
            add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'add_order_group_code_column_content'), 20, 2);
            add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'add_order_packaging_column_content'), 20, 2);

            // Admin order page add action header "Exported filter" (HPOS)
            add_action('woocommerce_order_list_table_restrict_manage_orders', array($this, 'not_exported_products_filter'), 20, 1);
            add_action('woocommerce_order_list_table_prepare_items_query_args', array($this, 'apply_not_exported_products_filter'));

            // Admin order page add bulk actions (HPOS)
            add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'add_bulk_order_submit_option'), 1000, 1);

            // Admin order page handle bulk actions (HPOS)
            add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'do_bulk_order_submit_option'), 10, 3);

            // Admin order page add label print function button (HPOS)
            add_action('woocommerce_order_list_table_extra_tablenav', array($this, 'print_labels_button'), 10, 2);

            // Add order page meta box with deliveo ID (HPOS)
            add_action('add_meta_boxes', array($this, 'deliveo_id_meta_box_wrapper'));
        }
        else
        {
            // Admin order table add columns (Classic)
            add_filter('manage_edit-shop_order_columns', array($this, 'add_mixpakk_columns_header'), 1);
            add_action('manage_shop_order_posts_custom_column', array($this, 'add_order_group_code_column_content_classic'), 20, 2);
            add_action('manage_shop_order_posts_custom_column', array($this, 'add_order_packaging_column_content_classic'), 20, 2);

            // Admin order page add action header "Exported filter" (Classic)
            add_action('restrict_manage_posts', array($this, 'not_exported_products_filter'), 20, 1);
            add_action('pre_get_posts', array($this, 'apply_not_exported_products_filter_classic'));

            // Admin order page add bulk actions (Classic)
            add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_order_submit_option'), 1000, 1);
            
            // Admin order page handle bulk actions (Classic)
            add_filter('handle_bulk_actions-edit-shop_order', array($this, 'do_bulk_order_submit_option'), 10, 3);

            // Admin order page add label print function button (Classic)
            add_action('manage_posts_extra_tablenav', array($this, 'print_labels_button_classic'), 10, 1);

            // Add order page meta box with deliveo ID (Classic)
            add_action('add_meta_boxes', array($this, 'deliveo_id_meta_box_wrapper_classic'));
        }

        // For "queue" option register heartbeat filter.
        if (($this->mixpakk_settings_o->mixpakk_settings['post_method'] ?? 'compat') == 'queue')
        {
            // Handle heartbeat to update submitted order data.
            add_filter('heartbeat_received', array($this, 'handle_heartbeat'), 10, 2);
        }

        // Add admin notice handler
        add_filter('removable_query_args', array($this, 'removable_notice_args'));
        add_action('admin_notices', array($this, 'display_messages'), 50);

        // Add ajax methods for meta box actions
        add_action('wp_ajax_mixpakk_submit_order', array($this, 'order_submit_ajax'));
        add_action('wp_ajax_mixpakk_delete_order', array($this, 'order_delete_ajax'));
    }

    protected function is_order_submitting($order)
    {
        if (($this->mixpakk_settings_o->mixpakk_settings['post_method'] ?? 'compat') == 'queue')
        {
            $queue = WC()->queue()->search(
                [
                    'group' => 'mixpakk_submit_' . $order->get_id(),
                    'order' => 'DESC',
                    'per_page' => 1,
                ],
                OBJECT
            );

            if (!empty($queue))
            {
                try
                {
                    if (in_array(ActionScheduler_Store::instance()->get_status(array_key_first($queue)), [ActionScheduler_Store::STATUS_PENDING, ActionScheduler_Store::STATUS_RUNNING]))
                    {
                        return true;
                    }
                }
                catch (\Exception $ex)
                {
                    if (function_exists('wp_admin_notice') && $this->queue_warning == false)
                    {
                        wp_admin_notice('MXP: ' . __('Egyedi Woocommerce Queue implementáció érzékelve, sajnos a rendelés feladás állapotát nem tudjuk frissíteni.', 'mixpakk'), [
                            'type' => 'warning'
                        ]);
                        $this->queue_warning = true;
                    }
                }
            }
        }
        return false;
    }

    protected function generate_meta_box_content($order, $is_submitting = null)
    {
        $group_code = $order->get_meta('_group_code', true);

        if (is_null($is_submitting))
        {
            $is_submitting = $this->is_order_submitting($order);
        }

        $ret = 
            '<input type="hidden" id="mxp_nonce" value="' . wp_create_nonce('mxp_action') . '">' .
            '<div class="mxp-groupid-container">' . 
                $this->generate_order_group_code_content($order, $is_submitting) . 
                $this->generate_order_packaging_unit_content($order, $is_submitting);

        if (empty($group_code))
        {
            if ($is_submitting)
            {
                $ret .= '<button id="mxp-submit" class="button-primary" disabled>' . __('Feltöltés', 'mixpakk') . '</button>';
                $ret .= '<button id="mxp-groupid-delete" class="button-primary mixpakk-busy">' . __('Törlés', 'mixpakk') . '</button>';
            }
            else
            {
                $ret .= '<button id="mxp-submit" class="button-primary">' . __('Feltöltés', 'mixpakk') . '</button>';
            }
        }
        else
        {
            $ret .= '<button id="mxp-groupid-delete" class="button-primary">' . __('Törlés', 'mixpakk') . '</button>';
        }

        $ret .= '</div>';
        
        return $ret;
    }

    public function deliveo_id_meta_box_content($order)
    {
        ?><?=
            $this->generate_meta_box_content($order);
        ?><?
    }

    public function deliveo_id_meta_box_wrapper() 
    {
        add_meta_box('mxp_deliveo_id_box', __('MXP Csomagfeladás', 'mixpakk'), array($this, 'deliveo_id_meta_box_content'), wc_get_page_screen_id('shop-order'), 'side', 'high');
    }

    public function deliveo_id_meta_box_wrapper_classic() 
    {
        add_meta_box('mxp_deliveo_id_box', __('MXP Csomagfeladás', 'mixpakk'), array($this, 'deliveo_id_meta_box_content_classic'), 'shop_order', 'side', 'high');
    }

    public function deliveo_id_meta_box_content_classic($post) 
    {
        $order = wc_get_order($post->ID);
        $this->deliveo_id_meta_box_content($order);
    }

    public function updateShippingOptions()
    {
        $mixpakk_api = new Mixpakk_API($this->mixpakk_settings_o);
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
            $this->mixpakk_settings_o->mixpakk_settings['shipping_options'] = $opts;

            update_option('mixpakk_settings', json_encode($this->mixpakk_settings_o->mixpakk_settings));
        }
    }

    public function add_mixpakk_columns_header($columns)
    {
        $new_columns = array();

        foreach ($columns as $column_name => $column_info) 
        {
            $new_columns[$column_name] = $column_info;

            if ($column_name == 'order_number') 
            {
                $new_columns['group_code'] = '<img style="vertical-align:middle" src="' . MIXPAKK_DIR_URL . 'images/mixpakk-icon.png" alt="GroupID" title="' . __('Amelyik rendelésnél ilyen ikont lát, az már fel lett adva Mixpakk API-n keresztül.', 'mixpakk') . '" /> Csoportkód';

                if ($this->mixpakk_settings_o->mixpakk_settings['packaging_unit'] == 2) 
                {
                    $new_columns['packaging_unit'] = __('Cs.e', 'mixpakk');
                }
            }
        }

        return $new_columns;
    }

    protected function generate_order_group_code_content($order, $is_submitting = null)
    {
        $ret = '';
        $group_code = $order->get_meta('_group_code', true);

        if (!empty($group_code)) 
        {
            $ret .= '<span class="mixpakk-cell no-link" data-groupid="' . $group_code . '"> ' . $group_code . '</span>';
        } 
        else
        {
            if (is_null($is_submitting))
            {
                $is_submitting = $this->is_order_submitting($order);
            }

            $selected = '';
            $opts = $this->mixpakk_settings_o->mixpakk_settings['shipping_options'];
            $ret .= '<select class="no-link' . ($is_submitting ? ' mixpakk-busy' : '') . '" data-id="' . $order->get_id() . '" style="max-width:100%" name="m_option[' . $order->get_id() . ']" title="Válasszon szállítási módot">';

            $delivery_option_default = $this->mixpakk_settings_o->mixpakk_settings["delivery"];
            $delivery_option_preferred = $delivery_option_default;

            if (!empty($this->mixpakk_settings_o->mixpakk_settings["delivery_abroad"]))
            {                    
                if ($order->get_shipping_country() != 'HU')
                {
                    $delivery_option_preferred = $this->mixpakk_settings_o->mixpakk_settings["delivery_abroad"];
                }
            }

            if (!empty($this->mixpakk_settings_o->mixpakk_settings["delivery_extra"]))
            {
                try
                {
                    // On default behaviour empty package data should work too, not needing to populate package data.
                    $package = [];
                    $package = apply_filters('mixpakk_order_filter_shipping_data', $package, $order);
                    if (!empty($package['shop_id']))
                    {
                        $delivery_option_preferred = $this->mixpakk_settings_o->mixpakk_settings["delivery_extra"];
                    }
                }
                catch(\Exception $ex)
                {
                    // Do not change the default delivery method.
                }
            }

            $delivery_option_preferred = $_REQUEST['m_option'][$order->get_id()] ?? $delivery_option_preferred;

            foreach ($opts as $option) 
            {
                if ($delivery_option_preferred == $option['value']) 
                {
                    $selected = 'selected';
                } 
                else 
                {
                    $selected = '';
                }
                $ret .= '<option  value="' . $option['value'] . '" ' . $selected . '>' . $option['description'] . '</option>';
            }

            $ret .= '</select>';
        
            if ($is_submitting)
            {
                $ret .= '<span class="mixpakk-submitting no-link" data-order-id="' . $order->get_id() . '"><img src="/wp-admin/images/loading.gif"></img>' . __('Feladás alatt..', 'mixpakk') . '</span>';
            }
        }
        return $ret;
    }

    public function add_order_group_code_column_content($column, $order)
    {
        if ($column == 'group_code') 
        {
            echo $this->generate_order_group_code_content($order);
        }
    }

    public function add_order_group_code_column_content_classic($column, $order_id)
    {
        if ($column == 'group_code') 
        {
            $this->add_order_group_code_column_content($column, new WC_Order($order_id));
        }
    }
    
    protected function generate_order_packaging_unit_content($order, $is_submitting = null)
    {
        $ret = '';
        $group_code = $order->get_meta('_group_code', true);

        if (empty($group_code)) 
        {
            $packaking_unit = $this->mixpakk_settings_o->mixpakk_settings['packaging_unit'];
            if (is_null($is_submitting))
            {
                $is_submitting = $this->is_order_submitting($order);
            }
            $max_package_count = 0;

            try
            {
                $order_items = apply_filters('mixpakk_order_filter_items', $order->get_items(), $order);
                foreach ($order_items as $order_item)
                {
                    $max_package_count += $order_item->get_quantity();
                }
                $max_package_count = max(1, $max_package_count);
            }
            catch(\Exception $ex)
            {
                $max_package_count = 1;
            }

            switch ((int)$packaking_unit) 
            {
            case 0:
                $ret = '<input class="no-link' . ($is_submitting ? ' mixpakk-busy' : '') . '" title="' . __('Csomagolási egység', 'mixpakk') . '" name="m_unit[' . $order->get_id() . ']" type="number" value="' . ($_REQUEST['m_unit'][$order->get_id()] ?? 1) . '" max="' . $max_package_count . '" min="1">';
                break;
            case 1:
            case 2:
            default:
                $ret = '<input class="no-link' . ($is_submitting ? ' mixpakk-busy' : '') . '" title="' . __('Csomagolási egység', 'mixpakk') . '" name="m_unit[' . $order->get_id() . ']" type="number" value="' . ($_REQUEST['m_unit'][$order->get_id()] ?? $max_package_count) . '" max="' . $max_package_count . '" min="1">';
                break;
            }
        }

        return $ret;
    }

    // Add packaging unit column
    public function add_order_packaging_column_content($column, $order)
    {
        if ($column == 'packaging_unit') 
        {
            echo $this->generate_order_packaging_unit_content($order);
        }
    }

    public function add_order_packaging_column_content_classic($column, $order_id)
    {
        if ($column == 'packaging_unit') 
        {
            $this->add_order_packaging_column_content($column, new WC_Order($order_id));
        }
    }

    /* Order filter functions */
    public function not_exported_products_filter($post_type)
    {
		if('shop_order' !== $post_type) 
        {
			return;
		}

        ?>
            <select name="mixpakk_exported">
                <option value=""><?= __('Mixpakk szűrés: mind', 'mixpakk') ?></option>
                <option value="not_exported" <?php if (($_GET['mixpakk_exported'] ?? '') == 'not_exported') { ?> selected <?php } ?>><?= __('Feladatlan', 'mixpakk') ?></option>
                <option value="exported" <?php if (($_GET['mixpakk_exported'] ?? '') == 'exported') { ?> selected <?php } ?>><?= __('Feladott', 'mixpakk') ?></option>
            </select>
        <?php
    }

    /* In the Orders admin page when the mixpakk filter was applied, this query filter will working  */
    public function apply_not_exported_products_filter_classic($query)
    {
        global $pagenow;

        if ($query->is_admin && $pagenow == 'edit.php' && isset($_GET['mixpakk_exported']) && sanitize_text_field($_GET['mixpakk_exported']) != '' && sanitize_text_field($_GET['post_type']) == 'shop_order') 
        {
            switch (sanitize_text_field($_GET['mixpakk_exported'])) 
            {
            case 'not_exported':
                $query->set('meta_query', 
                    [
                        [
                            'key' => '_group_code',
                            'compare' => 'NOT EXISTS',
                        ]
                    ]
                );
                break;
            case 'exported':
                $query->set('meta_query', 
                    [
                        [
                            'key' => '_group_code',
                            'compare' => 'EXISTS',
                        ]
                    ]
                );
                break;
            }
        }
    }

    public function apply_not_exported_products_filter($query_args)
    {
        if (isset($_GET['mixpakk_exported']) && sanitize_text_field($_GET['mixpakk_exported']) != '') 
        {
            switch (sanitize_text_field($_GET['mixpakk_exported'])) 
            {
            case 'not_exported':
                if (!isset($query_args['meta_query']))
                {
                    $query_args['meta_query'] = [];
                }
                $query_args['meta_query'][] = 
                    [
                        'key' => '_group_code',
                        'compare' => 'NOT EXISTS',
                    ];
                break;
            case 'exported':
                if (!isset($query_args['meta_query']))
                {
                    $query_args['meta_query'] = [];
                }
                $query_args['meta_query'][] = 
                    [
                        'key' => '_group_code',
                        'compare' => 'EXISTS',
                    ];
                break;
            }
        }

        return $query_args;
    }

    public function add_bulk_order_submit_option($actions)
    {
        $actions['mixpakk_submit'] = __('Mixpakk API feladás', 'mixpakk');
        return $actions;
    }

    function do_bulk_order_submit_option($redirect_to, $action, $post_ids)
    {
        if ($action == 'mixpakk_submit')
        {
            $messages = [];
            if (!(($this->mixpakk_settings_o->mixpakk_settings['post_method'] ?? 'compat') == 'queue' || get_option('woocommerce_custom_orders_table_enabled') === 'yes'))
            {
                // Set ignore_user_abort for php runtime in case classic POST method is selected, so order submit process doesn't get halted midway during critical operations.
                ignore_user_abort(true);
                // Counters
                $succeded = 0;
                $failed = 0;
            }

            foreach ($post_ids as $order_id)
            {
                if (!($order_id instanceof WC_Order))
                {
                    $order = wc_get_order($order_id);
                }
                else
                {
                    $order = $order_id;
                    $order_id = $order->get_id();
                }

                if ($order == false)
                {
                    continue;
                }

                $shipping_option = filter_var($_REQUEST['m_option'][$order_id] ?? null, FILTER_VALIDATE_INT, [ 'flags' => FILTER_NULL_ON_FAILURE, 'min_range' => 0]);
                $packaging_unit = filter_var($_REQUEST['m_unit'][$order_id] ?? null, FILTER_VALIDATE_INT, [ 'flags' => FILTER_NULL_ON_FAILURE, 'min_range' => 1]);

                if (($this->mixpakk_settings_o->mixpakk_settings['post_method'] ?? 'compat') == 'queue' || get_option('woocommerce_custom_orders_table_enabled') === 'yes')
                {
                    if (empty($order->get_meta('_group_code', true, 'edit')))
                    {
                        WC()->queue()->add('mixpakk_submit', [ 'id' => $order_id, 'shipping_option' => $shipping_option, 'packaging_unit' => $packaging_unit ], 'mixpakk_submit_' . $order_id);
                    }
                }
                else
                {
                    // Each order we test connection. If client disconnected do not continue.
                    if (connection_aborted() == 1)
                    {
                        wp_die();
                    }

                    try
                    {
                        $result = $this->mixpakk_o->send_by_api($order, $shipping_option, $packaging_unit);
                        
                        if ($result['type'] == "error") 
                        {
                            $failed++;
                            $messages[] = [
                                'type' => 'error',
                                'msg' => sprintf('MXP: ' . __('Rendelés %1$s - %2$s%3$s', 'mixpakk'), $order_id, $result['msg'], (isset($result['field']) ? ': ' . $result['field'] : '')),
                            ];
                            $order->add_order_note(__("MXP API: Hiba a feladás során: ", 'mixpakk') . $result['msg']);
                            $order->save();
                        }
                        else
                        {
                            if (!empty($result['data']))
                            {
                                $meta_success = is_int(update_post_meta($order_id, '_mixpakk_exported', 'true', '')) && is_int(update_post_meta($order_id, '_group_code', $result['data'][0], ''));
                                if (!$meta_success)
                                {
                                    $messages[] = [
                                        'type' => 'warning',
                                        'msg' => sprintf('MXP: ' . __('Rendelés %1$s - Ez a rendelés párhuzamosan fel lett adva!' , 'mixpakk'), $order_id),
                                    ];
                                    (new \Mixpakk_API($this->mixpakk_settings_o))->delete_order($result['data'][0]);
                                }
                                else
                                {
                                    $order->add_order_note(sprintf('MXP API: ' . __('Csomagadatok rögzítve %1$s azonosítóval.', 'mixpakk'), $result['data'][0]));
                                    $this->mixpakk_settings_o->set_delivered_order_status($order);
                                    $succeded++;
                                }
                            }
                        }
                        
                        if (!empty($result['warnings']))
                        {
                            foreach ($result['warnings'] as $warning)
                            {
                                $messages[] = [
                                    'type' => 'error',
                                    'msg' => sprintf('MXP: ' . __('Rendelés %1$s - %2$s%3$s', 'mixpakk'), $order_id, $warning['text'], (isset($warning['sku']) ? ': ' . $warning['sku'] : '')),
                                ];
                            }
                        }

                    }
                    catch (\Mixpakk_Exception $ex)
                    {
                        $failed++;

                        $order->add_order_note(__("MXP API: Hiba a feladás során: ", 'mixpakk') . $ex->getMessage());
                        $order->save();

                        $messages[] = [
                            'type' => 'error',
                            'msg' => sprintf('MXP: ' . __('Rendelés %1$s - %2$s%3$s' , 'mixpakk'), $order_id, $ex->getMessage(), ''),
                        ];

                        if ($ex->doChangeStatus())
                        {
                            $order->update_status('wc-mxp-fail', 'MXP API:');
                        }
                    }
                    catch (\RuntimeException $ex)
                    {
                        $failed++;

                        $order->add_order_note(__("MXP API: Hiba a feladás során: ", 'mixpakk') . $ex->getMessage() . ' ' . $ex->getFile() . ':' . $ex->getLine());
                        $order->save();

                        $messages[] = [
                            'type' => 'error',
                            'msg' => sprintf('MXP: ' . __('Rendelés %1$s - %2$s%3$s', 'mixpakk'), $order_id, __("MXP API: Hiba a feladás során: ", 'mixpakk'), $ex->getMessage() . ' ' . $ex->getFile() . ':' . $ex->getLine()),
                        ];
                    }
                }
            }

            if (!(($this->mixpakk_settings_o->mixpakk_settings['post_method'] ?? 'compat') == 'queue' || get_option('woocommerce_custom_orders_table_enabled') === 'yes'))
            {
                $messages[] = [
                    'type' => 'info',
                    'msg' => sprintf('MXP: ' . __('sikeres %1$s, sikertelen %2$s' , 'mixpakk'), $succeded, $failed),
                ];
                
                if (get_option('woocommerce_custom_orders_table_enabled') !== 'yes')
                {
                    return add_query_arg(['mxp_notices' => $messages], false, $redirect_to);
                }
            }
        }

        return $redirect_to;
    }

    function handle_heartbeat(array $response, array $data) 
    {
        if (!is_admin())
        {
            return $response;
        }

        // Validate
        if (!is_array($data['mixpakkPendingStatus']) || empty($data['mixpakkPendingStatus']))
        {
            return $response;
        }

        $response['mixpakkUpdateStatus'] = [];

        foreach($data['mixpakkPendingStatus'] as $order_id)
        {
            $order_id = filter_var($order_id, FILTER_VALIDATE_INT, [ 'flags' => FILTER_NULL_ON_FAILURE, 'min_range' => 0]);

            if (is_null($order_id))
            {
                continue;
            }

            $queue = WC()->queue()->search(
                [
                    'group' => 'mixpakk_submit_' . $order_id,
                    'order' => 'DESC',
                    'per_page' => 1,
                ],
                OBJECT
            );

            if (!empty($queue))
            {
                try
                {
                    $status = ActionScheduler_Store::instance()->get_status(array_key_first($queue));
                    if ($status == ActionScheduler_Store::STATUS_COMPLETE)
                    {
                        $order = wc_get_order($order_id);

                        $group_code = null;
                        if ($order !== false)
                        {
                            $group_code = $order->get_meta('_group_code', true, 'view');
                        }

                        if (empty($group_code))
                        {
                            $group_code = null;
                        }

                        $response['mixpakkUpdateStatus'][$order_id] = $group_code;
                    }
                    else if ($status == ActionScheduler_Store::STATUS_FAILED)
                    {
                        $response['mixpakkUpdateStatus'][$order_id] = null;
                    }
                }
                catch (\Exception $ex)
                {
                    $order = wc_get_order($order_id);
                    
                    $group_code = null;
                    if ($order !== false)
                    {
                        $group_code = $order->get_meta('_group_code', true, 'view');
                    }

                    if (!empty($group_code))
                    {
                        $response['mixpakkUpdateStatus'][$order_id] = $group_code;
                    }
                }
            }
            else
            {
                $order = wc_get_order($order_id);

                $group_code = null;
                if ($order !== false)
                {
                    $group_code = $order->get_meta('_group_code', true, 'view');
                }

                if (!empty($group_code))
                {
                    $response['mixpakkUpdateStatus'][$order_id] = $group_code;
                }
            }
        }

        if (count($response['mixpakkUpdateStatus']) < count($data['mixpakkPendingStatus']))
        {
            $response['heartbeat_interval'] = $response['heartbeat_interval'] ?? 'fast';
        }
        else
        {
            $response['heartbeat_interval'] = $response['heartbeat_interval'] ?? 'standard';
        }

        return $response;
    }

    function print_labels_button($post_type, $which)
    {
        if ('top' === $which)
        {
            ?>
            <span class="actions custom">
                <div style="height:32px;" class="button" id="mixpakk_print_labels">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16" style="vertical-align: middle;">
                        <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                        <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
                    </svg>
                    <span><?=__('Etikett nyomtatás', 'mixpakk')?></span>
                </div>
                <iframe id="mixpakk_label_print_preview" style="display: none;"></iframe>
            </span>
            <?php
        }
    }

    function print_labels_button_classic($which)
    {
        $post_type = get_post_type();

        if ('shop_order' === $post_type)
        {
            $this->print_labels_button($post_type, $which);
        }
    }

    function removable_notice_args($args)
    {
        array_push($args, 'mxp_notices');
        return $args;
    }

    function display_messages()
    {
        if (is_array($_GET['mxp_notices'] ?? null))
        {
            foreach ($_GET['mxp_notices'] as $message)
            {
                if (is_string($message['type']) && is_string($message['msg']))
                {
                    ?>
                        <div class="notice notice-<?= $message['type'] ?> is-dismissible">
                            <p>
                                <?=
                                    $message['msg'];
                                ?>
                            </p>
                        </div>
                    <?php
                }
            }
        }
    }

    public function order_submit_ajax()
    {
        $output_json = [
            'result' => 0,
            'messages' => [],
            'data' => [],
        ];

        try
        {
            if (!isset($_POST['nonce']) || !is_string($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mxp_action'))
            {
                throw new \Exception(__('Hibás paraméterek', 'mixpakk'));
            }
    
            if (!isset($_POST['order']) || !is_numeric($_POST['order']))
            {
                throw new \Exception(__('Hibás paraméterek', 'mixpakk'));
            }
    
            if (!current_user_can('edit_shop_order', $_POST['order']))
            {
                throw new \Exception(__('Nem megfelelő jogosultságok', 'mixpakk'));
            }
    
            $order = wc_get_order($_POST['order']);
    
            if (empty($order))
            {
                throw new \Exception(__('Nem létező rendelés', 'mixpakk'));
            }

            if (empty($order->get_meta('_group_code')))
            {
                $this->do_bulk_order_submit_option(null, 'mixpakk_submit', [ $order ]);
                // Reload
                $order = wc_get_order($order->get_id());
            }
            else
            {
                $output_json['messages'][] = [
                    'type' => 'warning',
                    'msg' => sprintf('MXP: ' . __('Ez a rendelés már feladva!' , 'mixpakk')),
                ];
            }
    
            $output_json['data']['dom'] = $this->generate_meta_box_content($order);
        }
        catch (\Throwable $ex)
        {
            $output_json['messages'] = [
                'type' => 'error',
                'msg' => $ex->getMessage(),
            ];
        }

        header('Content-Type: application/json');
        ?><?=json_encode($output_json)?><?php
        wp_die();
    }

    public function order_delete_ajax()
    {
        $output_json = [
            'result' => 0,
            'messages' => [],
            'data' => [],
        ];

        try
        {
            if (!isset($_POST['nonce']) || !is_string($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mxp_action'))
            {
                throw new \Exception(__('Hibás paraméterek', 'mixpakk'));
            }

            if (!isset($_POST['order']) || !is_numeric($_POST['order']))
            {
                throw new \Exception(__('Hibás paraméterek', 'mixpakk'));
            }

            if (!current_user_can('edit_shop_order', $_POST['order']))
            {
                throw new \Exception(__('Nem megfelelő jogosultságok', 'mixpakk'));
            }

            $order = wc_get_order($_POST['order']);

            if (empty($order))
            {
                throw new \Exception(__('Nem létező rendelés', 'mixpakk'));
            }

            if (!empty($order->get_meta('_group_code')))
            {
                $order->delete_meta_data('_group_code');
                $order->delete_meta_data('_mixpakk_exported');
                $order->add_order_note(__("MXP API: Csomagazonosító törölve a rendelésből", 'mixpakk'));
                $order->save();
                $output_json['messages'][] = [
                    'type' => 'success',
                    'msg' => sprintf('MXP: ' . __('MXP rendelés azonosító törölve' , 'mixpakk')),
                ];
            }
            else
            {
                $output_json['messages'][] = [
                    'type' => 'warning',
                    'msg' => sprintf('MXP: ' . __('Ez a rendelés nincs feladva!' , 'mixpakk')),
                ];
            }

            $output_json['data']['dom'] = $this->generate_meta_box_content($order);
        }
        catch (\Throwable $ex)
        {
            $output_json['messages'] = [
                'type' => 'error',
                'msg' => $ex->getMessage(),
            ];
        }

        header('Content-Type: application/json');
        ?><?=json_encode($output_json)?><?php
        wp_die();
    }
}
