<?php

// Register a custom order status
add_action('init', function ()
{
    register_post_status('wc-mxp-fail', 
        [
            'label' => _x('Hibás adat', 'Order Status', 'mixpakk'),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Hibás adat <span class="count">(%s)</span>', 'Hibás adat <span class="count">(%s)</span>')
        ]
    );
    register_post_status('wc-mxp-refused', 
        [
            'label' => _x('Sikertelen kézbesítés', 'Order Status', 'mixpakk'),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Sikertelen kézbesítés <span class="count">(%s)</span>', 'Sikertelen kézbesítés <span class="count">(%s)</span>')
        ]
    );
    register_post_status('wc-mxp-in-progress', 
        [
            'label' => _x('Kiszállítás alatt', 'Order Status', 'mixpakk'),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Kiszállítás alatt <span class="count">(%s)</span>', 'Kiszállítás alatt <span class="count">(%s)</span>')
        ]
    );
    register_post_status( 'wc-mxp',
        [
            'label' => _x('Összekészítés', 'Order Status', 'mixpakk'),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_all_admin_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Összekészítés <span class="count">(%s)</span>', 'Összekészítés <span class="count">(%s)</span>')
        ]
    );
    register_post_status( 'wc-utanrendelve', 
        [
            'label' => _x( 'Utánrendelve', 'Order Status', 'mixpakk'),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_all_admin_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Utánrendelve <span class="count">(%s)</span>', 'Utánrendelve <span class="count">(%s)</span>')
        ]
    );
    register_post_status('wc-mxp-no-stock', 
        [
            'label' => _x('Nincs készleten', 'Order Status', 'mixpakk'),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_all_admin_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Nincs készleten <span class="count">(%s)</span>', 'Nincs készleten <span class="count">(%s)</span>')
        ]
    );
});

add_filter('wc_order_statuses', function ($order_statuses)
    {
        $order_statuses['wc-mxp-fail'] = _x('Hibás adat', 'Order Status', 'mixpakk');
        $order_statuses['wc-mxp-refused'] = _x( 'Sikertelen kézbesítés', 'Order Status', 'mixpakk');
        $order_statuses['wc-mxp-in-progress'] = _x('Kiszállítás alatt', 'Order Status', 'mixpakk');
        $order_statuses['wc-mxp'] = _x('Összekészítés', 'Order Status', 'mixpakk');
        $order_statuses['wc-utanrendelve'] = _x('Utánrendelve', 'Order Status', 'mixpakk');
        $order_statuses['wc-mxp-no-stock'] = _x('Nincs készleten', 'Order Status', 'mixpakk');

        return $order_statuses;
    }
);

// Admin order page add bulk actions (Classic)
add_filter('bulk_actions-edit-shop_order', 'mixpakk_bulk_actions_register_order_statuses', 20, 1);
// Admin order page add bulk actions (HPOS)
add_filter('bulk_actions-woocommerce_page_wc-orders', 'mixpakk_bulk_actions_register_order_statuses', 20, 1);

function mixpakk_bulk_actions_register_order_statuses($actions)
{
    $index = 0;
    foreach ($actions as $key => $value)
    {
        if ($key == 'trash')
        {
            break;
        }
        $index++;
    }

    // Insert the bulk status change options before the trash option.
    $tail = array_splice($actions, $index);

    $actions = array_merge($actions, 
        [
            'mark_mxp-fail' => __('Módosítás hibás adatt állapotra', 'mixpakk'),
            'mark_mxp-refused' => __('Módosítás sikertelen kézbesítés állapotra', 'mixpakk'),
            'mark_mxp-in-progress' => __('Módosítás kiszállítás alatt állapotra', 'mixpakk'),
            'mark_mxp' => __('Módosítás összekészítés állapotra', 'mixpakk'),
            'mark_utanrendelve' => __('Módosítás utánrendelve állapotra', 'mixpakk'),
            'mark_mxp-no-stock' => __('Módosítás nincs készleten állapotra', 'mixpakk'),
        ],
        $tail
    );

    return $actions;
}

//// Admin order page handle bulk actions (Classic)
//add_filter('handle_bulk_actions-edit-shop_order', 'mixpakk_bulk_actions_register_order_statuses', 10, 3);
//// Admin order page handle bulk actions (HPOS)
//add_filter('handle_bulk_actions-woocommerce_page_wc-orders', 'mixpakk_bulk_actions_register_order_statuses', 10, 3);
//
//function mixpakk_bulk_actions_register_order_statuses($redirect_to, $action, $post_ids)
//{
//
//}
