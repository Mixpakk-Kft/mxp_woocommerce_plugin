<?php

class Mixpakk_Settings
{
    public function __construct()
    {
        $this->mixpakk_settings = $this->get_mixpakk_settings();
        add_action('admin_menu', array($this, 'settings_page'), 99);

        add_action('admin_notices', array($this, 'missing_mixpakk_settings_message'));
        add_action('wp_ajax_save_api_key_and_licence', array($this, 'save_api_key_and_licence'));
    }

    private function init_mixpakk_settings()
    {
        $init_settings = array(
            'api_key' => '',
            'licence_key' => '',
            'sender' => '',
            'sender_country_code' => '',
            'sender_zip' => '',
            'sender_city' => '',
            'sender_address' => '',
            'sender_apartment' => '',
            'sender_phone' => '',
            'sender_email' => '',
            'x' => '',
            'y' => '',
            'z' => '',
            'priority' => '',
            'saturday' => '',
            'insurance' => '',
            'freight' => '',
            'delivery' => '',
            'delivery_extra' => '',
            'delivery_abroad' => '',
            'mixpakk_settings' => '',
            'delivered_status' => '',
            'currency_multiplier' => '',
            'shop_id' => '',
            'tracking_id_is_order_id' => '',
            'shipping_options' => '',
            'packaging_unit' => '',
            'customcode_id_is_order_id' => '',
        );

        $init_settings = json_encode($init_settings);
        update_option('mixpakk_settings', $init_settings);
        $this->mixpakk_settings = $init_settings;

        return $init_settings;
    }

    /* Add Mixpakk settings page Woocommerce submenu */
    public function settings_page()
    {
        add_submenu_page('woocommerce', 'Mixpakk', 'Mixpakk', 'manage_options', 'mixpakk-settings', array($this, 'settings_page_content'));
    }

    /* Settings page content (Save settings form) */
    public function settings_page_content()
    {
        $this->save_mixpakk_settings();
        $settings = $this->get_mixpakk_settings();
        $shipping_options_selector = $this->shipping_options_selector($settings);
        $shipping_options_selector_extra = $this->shipping_options_selector_extra($settings);
        $shipping_options_selector_abroad = $this->shipping_options_selector_abroad($settings);
        $order_status_selector = $this->order_status_selector($settings);

        /* Declare variables because of undefined index errors, or create a parser function */
        if ((strlen($shipping_options_selector) > 200)) {
            $packagesettings = '<tr>
            <td colspan="2"><h2>' . __('Felad?? be??ll??t??sa | <small>Az itt megadott param??terek ker??lnek a csomag adataiba mint "Felad??".', 'mixpakk') . '</small></h2></td>
        </tr>
        <tr>
            <td>' . __('Felad?? neve', 'mixpakk') . '</td>
            <td><input type="text" name="sender" value="' . $settings['sender'] . '" class="required" data-message="' . __('Felad?? n??v k??telez??', 'mixpakk') . '" /></td>
        </tr>
        <tr>
            <td>' . __('Felad?? orsz??g??nak k??tjegy?? k??dja. Pl: "HU", "DE"', 'mixpakk') . '</td>
            <td><input type="text" maxlength="2" name="sender_country_code" value="' . $settings['sender_country_code'] . '" class="required" data-message="' . __('Orsz??g k??d k??telez??', 'mixpakk') . '" /></td>
        </tr>
        <tr>
            <td>' . __('Felad?? telep??l??s??nek az ir??ny??t??sz??ma', 'mixpakk') . '</td>
            <td><input type="text" name="sender_zip" value="' . $settings['sender_zip'] . '" class="required" data-message="' . __('Felad?? ir??ny??t??sz??m k??telez??', 'mixpakk') . '" /></td>
        </tr>
        <tr>
            <td>' . __('Felad?? telep??l??s neve', 'mixpakk') . '</td>
            <td><input type="text" name="sender_city" value="' . $settings['sender_city'] . '" class="required" data-message="' . __('Telep??l??s neve k??telez??', 'mixpakk') . '" /></td>
        </tr>
        <tr>
            <td>' . __('Felad?? k??zter??let neve, h??zsz??m', 'mixpakk') . '</td>
            <td><input type="text" name="sender_address" value="' . $settings['sender_address'] . '" class="required" data-message="' . __('Felad?? k??zter??let n??v k??telez??', 'mixpakk') . '" /></td>
        </tr>
        <tr>
            <td>' . __('Felad?? ??p??let, l??pcs??h??z, emelet, ajt??', 'mixpakk') . '</td>
            <td><input type="text" name="sender_apartment" value="' . $settings['sender_apartment'] . '" /></td>
        </tr>
        <tr>
            <td>' . __('Felad?? telefonsz??m', 'mixpakk') . '</td>
            <td><input type="text" name="sender_phone" value="' . $settings['sender_phone'] . '" class="required" data-message="' . __('Felad?? telefonsz??m k??telez??', 'mixpakk') . '" /></td>
        </tr>
        <tr>
            <td>' . __('Felad?? email', 'mixpakk') . '</td>
            <td><input type="text" name="sender_email" value="' . $settings['sender_email'] . '" class="required" data-message="' . __('Felad?? email k??telez??', 'mixpakk') . '" /></td>
        </tr>
        <tr>
            <td colspan="2"><h2>' . __('Alap??rtelmezett csomag m??retek | <small>Ha hi??nyoznak a term??k m??retei, akkor ezeket haszn??ljuk felad??skor.', 'mixpakk') . '</small></h2></td>
        </tr>
        <tr>
            <td>' . __('X = sz??less??g, Y = magass??g, Z = m??lys??g (cm)', 'mixpakk') . '</td>
            <td>X: <input type="number" name="x" value="' . $settings['x'] . '" class="required" data-message="' . __('Alap??rtelmezett csomag magass??g k??telez??', 'mixpakk') . '" /> Y: <input type="number" name="y" value="' . $settings['y'] . '" class="required" data-message="' . __('Alap??rtelmezett csomag sz??less??g k??telez??', 'mixpakk') . '" /> Z: <input type="number" name="z" value="' . $settings['z'] . '" class="required" data-message="' . __('Alap??rtelmezett csomag m??lys??g k??telez??', 'mixpakk') . '" /></td>
        </tr>
                <tr>
            <td colspan="2"><h2>' . __('Ut??nv??t korrekci??') . '</h2></td>
        </tr>
        <td>' . __('Korrekci??s szorz??', 'mixpakk') . '</td>
            <td><input type="number" min="1" step="0.0001" name="currency_multiplier" value="' . ($settings['currency_multiplier'] ?? 1) . '" style="width:500px;" class="required" data-message="' . __('Korrekci??s mez?? k??telez??', 'mixpakk') . '" />
            <br><small>' . __('Amennyiben web??ruh??za rendel??seit nem forintban kezeli, k??rj??k haszn??lja ez a mez??t. (pl.: EUR eset??ben ~0,003 ??rt??ket adjon meg.)', 'mixpakk') . '</small>
            </td>
        <tr>
            <td colspan="2"><h2>Sz??ll??t??si param??terek | <small>Ezekkel a param??terekkel lesz feladva minden csomag.</small></h2></td>
        </tr>
        <tr>
            <td>' . __('Priorit??s (els??bbs??gi k??zbes??t??s)', 'mixpakk') . '</td>
            <td>
                <input type="hidden" name="priority" value="0" />
                <input type="checkbox" name="priority" value="1" ' . mxp_is_option_checked($settings['priority']) . ' />
            </td>
        </tr>
        <tr>
            <td>' . __('Szombati sz??ll??t??s', 'mixpakk') . '</td>
            <td>
                <input type="hidden" name="saturday" value="0" />
                <input type="checkbox" name="saturday" value="1" ' . mxp_is_option_checked($settings['saturday']) . ' />
            </td>
        </tr>
        <tr>
            <td>' . __('Biztos??t??s', 'mixpakk') . '</td>
            <td>
                <input type="hidden" name="insurance" value="0" />
                <input type="checkbox" name="insurance" value="1" ' . mxp_is_option_checked($settings['insurance']) . ' />
            </td>
        </tr>
        <tr>
            <td>' . __('Rendel??ssz??m ker??lj??n a K??vet??k??d mez??be', 'mixpakk') . '</td>
            <td>
                <input type="hidden" name="tracking_id_is_order_id" value="0" />
                <input type="checkbox" name="tracking_id_is_order_id" value="1" ' . mxp_is_option_checked($settings['tracking_id_is_order_id']) . ' />
            </td>
        </tr>
        <tr>
            <td>' . __('Rendel??ssz??m ker??lj??n az Egyedi azonos??t?? mez??be', 'mixpakk') . '</td>
            <td>
                <input type="hidden" name="customcode_id_is_order_id" value="0" />
                <input type="checkbox" name="customcode_id_is_order_id" value="1" ' . mxp_is_option_checked($settings['customcode_id_is_order_id']) . ' />
            </td>
        </tr>
        <tr>
        <tr>
            <td>' . __('Ki fizeti a sz??ll??t??si k??lts??get?', 'mixpakk') . '</td>
            <td>
                <label for="freight-felado">' . __('Felad??', 'mixpakk') . '</label>
                <input type="radio" name="freight" value="felado" id="freight-felado" ' . mxp_is_radio_checked($settings['freight'], 'felado') . ' />

                <label for="freight-cimzett">' . __('C??mzett', 'mixpakk') . '</label>
                <input type="radio" name="freight" value="cimzett" id="freight-cimzett" ' . mxp_is_radio_checked($settings['freight'], 'cimzett') . ' />
            </td>
        </tr>
        <tr>
            <td>' . __('A feladottak ??llapot??t erre m??dos??tsa:', 'mixpakk') . '</td>
            <td>' . $order_status_selector . '</td>
        </tr>
        <tr>
            <td colspan="2"><h2>Csomag tulajdons??gok</small></h2></td>
        </tr>
        <tr>
            <td>' . __('Csomagol??si egys??g', 'mixpakk') . '</td>
            <td><select name="packaging_unit">
                <option ' . ($settings['packaging_unit'] == 0 ? 'selected' : '') . ' value="0">Mindig egy</option>
                <option ' . ($settings['packaging_unit'] == 1 ? 'selected' : '') . ' value="1">T??telenk??nt egy</option>
                <option ' . ($settings['packaging_unit'] == 2 ? 'selected' : '') . ' value="2">Mindig manu??lisan adom meg</option>
            </select>
            </td>
        </tr>
        <tr>
            <td colspan="2"><h2>Sz??ll??t??si m??d</small></h2></td>
        </tr>
        <tr>
            <td>' . __('Alap??rtelmezett sz??ll??t??si opci??:', 'mixpakk') . '</td>
            <td>' . $shipping_options_selector . '</td>
        </tr>
        <tr>
            <td>' . __('Csomagpont sz??ll??t??si opci??:', 'mixpakk') . '</td>
            <td>' . $shipping_options_selector_extra . '</td>
        </tr>
        <tr>
            <td>' . __('K??lf??ldi sz??ll??t??si opci??:', 'mixpakk') . '</td>
            <td>' . $shipping_options_selector_abroad . '</td>
        </tr>

        <tr>
            <td></td>
            <td><button name="mixpakk_settings" type="submit" class="button button-primary mixpakk_settings_save">' . __('Ment??s', 'mixpakk') . '</button></td>
        </tr>';
        } else {
            $this->admin_notice_api_error();
            $packagesettings = '';
        }
        $content = '
        <h1>' . __('Be??ll??t??sok', 'mixpakk') . '</h1>
        <tr>
        <td colspan="2"><h2>Technikai seg??ts??gny??jt??s: <a href="mailto:helpdesk@mxp.hu">helpdesk@mxp.hu</a> </small></h2></td>
                </tr>
        <form action="" method="post" id="mixpakk-settings-form" class="mixpakk-settings-form">
            <div class="validation-messages hidden"></div>
            <table>
                <tr>
                    <td>' . __('API kulcs (a szerz??d??tt fut??rszolg??lat adja meg)', 'mixpakk') . '</td>
                    <td>
                    <input type="text" id="mixpakk-api-key" name="api_key" value="' . $settings['api_key'] . '" class="required" data-message="' . __('API kulcs k??telez??', 'mixpakk') . '" />
                    </td>
                </tr>
                <tr>
                    <td>' . __('Licensz (a szerz??d??tt fut??rszolg??lat adja meg)', 'mixpakk') . '</td>
                    <td>
                        <input type="text" id="mixpakk-licence-key" name="licence_key" value="' . $settings['licence_key'] . '" class="required" data-message="' . __('Licence k??telez??', 'mixpakk') . '" />
                        <button type="button" class="mixpakk-save-button" id="mixpakk-save-api-licence" title="' . __('API kulcs ??s Licensz ment??s') . '"></button>
                    </td>
                </tr>'
            . $packagesettings .
            '</table>
        </form>';

        echo $content;
    }

    /* Get Mixpakk settings from DB and parse to array */
    public function get_mixpakk_settings()
    {

        $settings = get_option('mixpakk_settings', '');

        if (empty($settings)) {
            $settings = $this->init_mixpakk_settings();
        }

        return json_decode($settings, true);
    }

    /* Parse Mixpakk options array to json strings and save to DB */
    public function save_mixpakk_settings()
    {
        if (isset($_POST['mixpakk_settings'])) {
            $mixpakk_api = new Mixpakk_API(new Mixpakk_Settings());
            $shipping_options = $mixpakk_api->get_shipping_options();
            $_POST['shipping_options'] = $shipping_options;

            $settings = json_encode($_POST);
            update_option('mixpakk_settings', $settings);
        }

        if (isset($_POST['action']) && $_POST['action'] == 'save_api_key_and_licence') {
            $settings = $this->get_mixpakk_settings();

            $settings['api_key'] = $_POST['api_key'];
            $settings['licence_key'] = $_POST['licence_key'];

            $this->admin_notice__success();

            $settings = json_encode($settings);
            update_option('mixpakk_settings', $settings);
        }
    }

    /** Build a selector by Shipping options details from DELIVEO API */
    public function shipping_options_selector($mixpakk_settings)
    {
        $mixpakk_api = new Mixpakk_API(new Mixpakk_Settings());
        $shipping_options = $mixpakk_api->get_shipping_options();

        $api_key = mxp_get_value($mixpakk_settings['api_key']);
        $saved_shipping = mxp_get_value($mixpakk_settings['delivery']);
        $selector = '';

        if (empty($api_key)) {
            $selector = '<input type="hidden" name="delivery" value="" />';
            $selector .= __('A sz??ll??t??si opci?? be??ll??t??s??hoz k??rj??k el??bb adja meg az API kulcsot ??s mentse el a be??ll??t??sokat', 'mixpakk');
        } else {
            $selector = '
             <select name="delivery" class="required" data-message="' . __('Sz??ll??t??si opci?? k??telez??', 'mixpakk') . '">
                 <option value="">' . __('V??lasszon sz??ll??t??si opci??t', 'mixpakk') . '</option>';

            foreach ($shipping_options as $shipping_option) {
                $selected = mxp_is_selector_selected($shipping_option->value, $saved_shipping);

                $selector .= '
                <option value="' . $shipping_option->value . '" ' . $selected . '>' . $shipping_option->description . '</option>';
            }

            $selector .= '
            </select>';
        }

        return $selector;
    }

    /** Build a selector by Shipping options details from DELIVEO API */
    public function shipping_options_selector_extra($mixpakk_settings)
    {
        $mixpakk_api = new Mixpakk_API(new Mixpakk_Settings());
        $shipping_options = $mixpakk_api->get_shipping_options();

        $api_key = mxp_get_value($mixpakk_settings['api_key']);
        $saved_shipping = mxp_get_value($mixpakk_settings['delivery_extra']);
        $selector = '';

        if (empty($api_key)) {
            $selector = '<input type="hidden" name="delivery_extra" value="" />';
            $selector .= __('A sz??ll??t??si opci?? be??ll??t??s??hoz k??rj??k el??bb adja meg az API kulcsot ??s mentse el a be??ll??t??sokat', 'mixpakk');
        } else {
            $selector = '
             <select name="delivery_extra">
                 <option value="">' . __('V??lasszon sz??ll??t??si opci??t', 'mixpakk') . '</option>';

            foreach ($shipping_options as $shipping_option) {
                $selected = mxp_is_selector_selected($shipping_option->value, $saved_shipping);

                $selector .= '
                <option value="' . $shipping_option->value . '" ' . $selected . '>' . $shipping_option->description . '</option>';
            }

            $selector .= '
            </select>';
        }

        return $selector;
    }

    /** Build a selector by Shipping options details from DELIVEO API */
    public function shipping_options_selector_abroad($mixpakk_settings)
    {
        $mixpakk_api = new Mixpakk_API(new Mixpakk_Settings());
        $shipping_options = $mixpakk_api->get_shipping_options();

        $api_key = mxp_get_value($mixpakk_settings['api_key']);
        $saved_shipping = mxp_get_value($mixpakk_settings['delivery_abroad']);
        $selector = '';

        if (empty($api_key)) {
            $selector = '<input type="hidden" name="delivery_abroad" value="" />';
            $selector .= __('A sz??ll??t??si opci?? be??ll??t??s??hoz k??rj??k el??bb adja meg az API kulcsot ??s mentse el a be??ll??t??sokat', 'mixpakk');
        } else {
            $selector = '
             <select name="delivery_abroad">
                 <option value="">' . __('V??lasszon sz??ll??t??si opci??t', 'mixpakk') . '</option>';

            foreach ($shipping_options as $shipping_option) {
                $selected = mxp_is_selector_selected($shipping_option->value, $saved_shipping);

                $selector .= '
                <option value="' . $shipping_option->value . '" ' . $selected . '>' . $shipping_option->description . '</option>';
            }

            $selector .= '
            </select>';
        }

        return $selector;
    }

    public function shipping_options_menu()
    {
        $settings = $this->init_mixpakk_settings();
        return ((array) $settings['shipping_options']);
    }

    public function order_status_selector($mixpakk_settings)
    {
        $status_types = wc_get_order_statuses();
        $delivered_status = $mixpakk_settings['delivered_status'];

        $selector = '
        <select name="delivered_status">
            <option value="">' . __('Nem v??ltoztat', 'mixpakk') . '</option>';

        foreach ($status_types as $status_key => $status_value) {
            $selected = '';

            if ($delivered_status == $status_key) {
                $selected = 'selected="selected"';
            }

            $selector .= '
            <option value="' . $status_key . '" ' . $selected . '>' . $status_value . '</option>';
        }

        $selector .= '
        </select>';

        return $selector;
    }

    /* Check if some important details are missing from mixpakk settings: API key, Licence key,  */
    public function mixpakk_setting_missing()
    {
        $settings = $this->mixpakk_settings;
        $setting_missing = false;

        foreach ($settings as $setting_key => $setting_value) {
            if (
                // kiz??runk az ellen??rz??sb??l p??r param??tert
                $setting_key != 'mixpakk_settings' &&
                $setting_key != 'sender_apartment' &&
                $setting_key != 'delivered_status' &&
                $setting_key != 'delivery_extra' &&
                $setting_key != 'delivery_abroad' &&
                $setting_key != 'shipping_options' &&

                //a t??bbi ??rt??ke nem lehet 0
                strlen($setting_value) < 0
            ) {
                $setting_missing = true;
            }
            // tesztel??shez a mez??k ??rt??keinek ki??rat??sa
            //echo $setting_key." - ".$setting_value."<br>";
        }

        return $setting_missing;
    }

    public function missing_mixpakk_settings_message()
    {
        $setting_missing = $this->mixpakk_setting_missing();

        if ($setting_missing) {
            $class = 'notice notice-warning';
            $message = __('Hi??nyz?? Mixpakk be??ll??t??s! Az export haszn??lat??hoz k??rj??k menjen a Woocommerce -> Mixpakk oldalra a hi??nyz?? adatok megad??s??hoz', 'mixpakk');

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        }
    }

    public function set_delivered_order_status($order_id)
    {
        $settings = $this->mixpakk_settings;
        $delivered_status = $settings['delivered_status'];
        $order = new WC_Order($order_id);

        if (!empty($delivered_status)) 
        {
            $order->update_status($delivered_status, 'MXP API:');
        }
    }

    public function admin_notice__success()
    {

        $class = 'notice notice-success is-dismissible';
        $message = __('Be??ll??t??sok mentve, authentik??ci??s adatok m??dos??t??sakor ellen??rizz??k a sz??ll??t??si opci??kat!', 'mixpakk');

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    public function admin_notice_api_error()
    {

        $class = 'notice notice-warning is-dismissible';
        $message = __('Sikertelen csatlakoz??s, k??rj??k ellen??rizze a licenc adatokat!', 'mixpakk');

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    public function save_api_key_and_licence()
    {
        $this->save_mixpakk_settings();
        wp_die();
    }
}
