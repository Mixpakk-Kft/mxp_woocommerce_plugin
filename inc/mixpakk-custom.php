<?php

// Register a custom order status
function mxp_register_custom_order_statuses() 
{
    register_post_status('wc-mxp-fail', array(
        'label' => __( 'Hibás adat', 'woocommerce' ),
        'public' => false,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Hibás adat <span class="count">(%s)</span>', 'Hibás adat <span class="count">(%s)</span>')
    ));
    register_post_status('wc-mxp-refused', array(
        'label' => __( 'Sikertelen kézbesítés', 'woocommerce' ),
        'public' => false,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Sikertelen kézbesítés <span class="count">(%s)</span>', 'Sikertelen kézbesítés <span class="count">(%s)</span>')
    ));
    register_post_status('wc-mxp-in-progress', array(
        'label' => __( 'Kiszállítás alatt', 'woocommerce' ),
        'public' => false,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Kiszállítás alatt <span class="count">(%s)</span>', 'Kiszállítás alatt <span class="count">(%s)</span>')
    ));
    register_post_status( 'wc-mxp', array(
            'label' => _x( 'Összekészítés', 'Order Status' ),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_all_admin_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop( 'Összekészítés <span class="count">(%s)</span>', 'Összekészítés <span class="count">(%s)</span>' )
        )
    );
    register_post_status( 'wc-utanrendelve', array(
            'label' => _x( 'Utánrendelve', 'Order Status' ),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_all_admin_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop( 'Utánrendelve <span class="count">(%s)</span>', 'Utánrendelve <span class="count">(%s)</span>' )
        )
    );
}
add_action('init', 'mxp_register_custom_order_statuses');

function mxp_list_order_status($order_statuses)
{
    $order_statuses['wc-mxp-fail'] = _x('Hibás adat', 'Order Status');
    $order_statuses['wc-mxp-refused'] = _x( 'Sikertelen kézbesítés', 'Order Status');
    $order_statuses['wc-mxp-in-progress'] = _x('Kiszállítás alatt', 'Order Status');
    $order_statuses['wc-mxp'] = _x('Összekészítés', 'Order Status');
    $order_statuses['wc-utanrendelve'] = _x('Utánrendelve', 'Order Status');

    return $order_statuses;
}
add_filter( 'wc_order_statuses', 'mxp_list_order_status');

function mxp_add_to_bulk_actions_orders() 
{
    global $post_type;

    if( 'shop_order' == $post_type ) {
        ?>
            <script type="text/javascript">
                jQuery(document).ready(function(){
                    jQuery('<option>').val('mark_mxp').text('<?php _e( 'Módosítás Összekészítés állapotra' ); ?>').appendTo("select[name='action']");
                    jQuery('<option>').val('mark_mxp').text('<?php _e( 'Módosítás Összekészítés állapotra' ); ?>').appendTo("select[name='action2']");
                    jQuery('<option>').val('mark_utanrendelve').text('<?php _e( 'Módosítás Utánrendelve állapotra' ); ?>').appendTo("select[name='action']");
                    jQuery('<option>').val('mark_utanrendelve').text('<?php _e( 'Módosítás Utánrendelve állapotra' ); ?>').appendTo("select[name='action2']");
                });
            </script>
        <?php
    }
}
add_action( 'admin_footer', 'mxp_add_to_bulk_actions_orders' );



?>