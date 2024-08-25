<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlac\App\Controllers\Site;

defined( 'ABSPATH' ) or die;

use Wlac\App\Controllers\Base;
use Wlac\App\Helpers\Input;
use Wlac\App\Helpers\Util;
use Wlac\App\Helpers\Woocommerce;

class Main extends Base {
    public static $active_plugin_list = array();

    public static function addMenu() {
        if ( Woocommerce::hasAdminPrivilege() ) {
            add_menu_page( __( 'WPLoyalty: Auto Currency Change', 'wp-loyalty-auto-currency' ), __( 'WPLoyalty: Auto Currency Change', 'wp-loyalty-auto-currency' ), 'manage_woocommerce', WLAC_PLUGIN_SLUG,
                'Wlac\App\Controllers\Site\Main::manageLoyaltyPages'
            , 'dashicons-megaphone', 57 );
        }
    }

    public static function adminScripts() {
        if ( ! Woocommerce::hasAdminPrivilege() ) {
            return;
        }
        $page = Input::get( 'page', null );
        if ( $page != WLAC_PLUGIN_SLUG ) {
            return;
        }
        $suffix = '.min';
        if ( defined( 'SCRIPT_DEBUG' ) ) {
            $suffix = SCRIPT_DEBUG ? '' : '.min';
        }
        if ( $page == WLAC_PLUGIN_SLUG ) {
            remove_all_actions( 'admin_notices' );
        }

        wp_enqueue_style( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Css/alertify' . $suffix . '.css', array(), WLR_PLUGIN_VERSION );
        wp_enqueue_script( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Js/alertify' . $suffix . '.js', array(), WLR_PLUGIN_VERSION . '&t=' . time() );

        wp_register_script( WLAC_PLUGIN_SLUG . '-main', WLAC_PLUGIN_URL . 'Assets/Admin/Js/wlac-admin.js', array( 'jquery' ), WLAC_PLUGIN_VERSION . '&t=' . time() );
        wp_enqueue_script( WLAC_PLUGIN_SLUG . '-main' );
        wp_enqueue_style( WLAC_PLUGIN_SLUG . '-main', WLAC_PLUGIN_URL . 'Assets/Admin/Css/wlac-admin.css', array(), WLAC_PLUGIN_VERSION );
        $localize = array(
            'home_url'            => get_home_url(),
            'admin_url'           => admin_url(),
            'ajax_url'            => admin_url( 'admin-ajax.php' ),
            'saving_button_label' => __( "Saving...", "wp-loyalty-auto-currency" ),
            'saved_button_label'  => __( "Save Changes", "wp-loyalty-auto-currency" ),
        );
        wp_localize_script( WLAC_PLUGIN_SLUG . '-main', 'wlac_localize_data', $localize );
    }

    public static function menuHide() {
        ?>
        <style>
            #toplevel_page_wp-loyalty-auto-currency {
                display: none !important;
            }
        </style>
        <?php
    }

    public static function manageLoyaltyPages() {
        if ( ! Woocommerce::hasAdminPrivilege() ) {
            wp_die( __( "Don't have access permission", 'wp-loyalty-auto-currency' ) );
        }
        //it will automatically add new table column,via auto generate alter query
        if ( ! isset( $_GET['page'] ) || ( $_GET['page'] != WLAC_PLUGIN_SLUG ) ) {
            wp_die( __( 'Page query params missing...', 'wp-loyalty-auto-currency' ) );
        }
        $path             = WLAC_PLUGIN_PATH . 'App/Views/main.php';
        $main_page_params = array(
            'options'            => get_option( 'wlac_settings', array() ),
            'app_url'            => admin_url( 'admin.php?' . http_build_query( array( 'page' => WLR_PLUGIN_SLUG ) ) ) . '#/apps',
            'wlac_setting_nonce' => Woocommerce::create_nonce( 'wlac-setting-nonce' ),
        );
        Util::renderTemplate($path,$main_page_params);
    }

    static function getDefaultProductPrice( $productPrice, $product, $item, $is_redeem, $orderCurrency ) {
        $currency_plugin_helper = self::getActivePluginObject();
        if ( empty( $currency_plugin_helper ) ) {
            return $productPrice;
        }

        return $currency_plugin_helper->getDefaultProductPrice( $productPrice, $product, $item, $is_redeem, $orderCurrency );
        /*if ( $this->isEnabledWPMLCurrency() ) {
            global $woocommerce_wpml;
            $multi_currency = $woocommerce_wpml->get_multi_currency();
            $current_code   = $multi_currency->get_client_currency();
            return $this->convertToDefaultCurrency( $productPrice, $current_code );
        }
        if ( $this->isEnabledAeliaoCurrency() ) {
            $current_code = isset( $GLOBALS['woocommerce-aelia-currencyswitcher'] ) ? $GLOBALS['woocommerce-aelia-currencyswitcher']->get_selected_currency() : '';
            if ( ! empty( $current_code ) ) return $this->convertToDefaultCurrency( $productPrice, $current_code );
        }
        return $productPrice;*/
    }

    public static function getActivePluginObject() {
        $plugin_name = self::getActiveCurrencyPlugin();
        if ( $plugin_name ) {
            if ( file_exists( WLAC_PLUGIN_PATH . 'App/Compatibility/' ) ) {
                $plugin_helper_list = array_slice( scandir( WLAC_PLUGIN_PATH . 'App/Compatibility/' ), 2 );
                if ( ! empty( $plugin_helper_list ) ) {
                    foreach ( $plugin_helper_list as $condition ) {
                        $class_name = basename( $condition, '.php' );
                        if ( $class_name == $plugin_name ) {
                            $plugin_class_name = '\Wlac\App\Compatibility\\' . $class_name;
                            if ( class_exists( $plugin_class_name ) ) {
                                return $plugin_class_name::getInstance();
                            }
                        }
                    }
                }
            }
        }

        return '';
    }

    public static function getActiveCurrencyPlugin() {
        $currencyPlugins = array(
            'Villa'   => 'isVillaCurrencySwitcherEnabled',
            'RealMag' => 'isEnableRealMagCurrency',
            'WPML'    => 'isEnabledWPMLCurrency',
            'Aelia'   => 'isEnabledAeliaoCurrency',
            'WooPayments' => 'isEnabledWooPaymentsCurrency',
        );

        foreach ( $currencyPlugins as $pluginName => $enableMethod ) {
            if ( self::$enableMethod() ) {
                return $pluginName;
            }
        }

        return apply_filters( 'wlr_enabled_currency_plugin', '' );
    }

    static function isVillaCurrencySwitcherEnabled() {
        return self::isPluginIsActive( 'woo-multi-currency/woo-multi-currency.php' ) || self::isPluginIsActive( 'woocommerce-multi-currency/woocommerce-multi-currency.php' );
    }

    /**
     * @return bool
     */
    static function isEnableRealMagCurrency() {
        // Ref: https://wordpress.org/plugins/woocommerce-currency-switcher/
        return self::isPluginIsActive( 'woocommerce-currency-switcher/index.php' );
    }

    protected static function isPluginIsActive( $plugin = '' ) {
        if ( empty( $plugin ) || ! is_string( $plugin ) ) {
            return false;
        }
        $active_plugins = self::getActivePlugins();
        if ( in_array( $plugin, $active_plugins, false ) || array_key_exists( $plugin, $active_plugins ) ) {
            return true;
        }

        return false;
    }

    static function getActivePlugins() {
        if ( empty( self::$active_plugin_list ) ) {
            $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );
            if ( is_multisite() ) {
                $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
            }
            self::$active_plugin_list = $active_plugins;
        }

        return self::$active_plugin_list;
    }

    static function isEnabledWPMLCurrency() {
        //ref: https://wordpress.org/plugins/woocommerce-multilingual/
        return self::isPluginIsActive( 'woocommerce-multilingual/wpml-woocommerce.php' );
    }

    static function isEnabledAeliaoCurrency() {
        return self::isPluginIsActive( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' );
    }
    
    static function isEnabledWooPaymentsCurrency() {
      return self::isPluginIsActive( 'woocommerce-payments/woocommerce-payments.php' );
    }

    static function getProductPrice( $productPrice, $item, $is_redeem, $orderCurrency ) {
        $currency_plugin_helper = self::getActivePluginObject();
        if ( empty( $currency_plugin_helper ) ) {
            return $productPrice;
        }

        return $currency_plugin_helper->getProductPrice( $productPrice, $item, $is_redeem, $orderCurrency );
        /*if ( empty( $orderCurrency ) ) {
            $orderCurrency = $this->getCurrentCurrencyCode( $orderCurrency );
        }
        $default_currency = $this->getDefaultCurrency();
        if ( $orderCurrency == $default_currency ) {
            return $productPrice;
        }
        if ( ! empty( $orderCurrency ) ) {
            return $this->convertToDefaultCurrency( $productPrice, $orderCurrency );
        }
        return $productPrice;*/
    }

    static function getCartSubtotal( $sub_total, $cart_data ) {
        $currency_plugin_helper = self::getActivePluginObject();
        if ( empty( $currency_plugin_helper ) ) {
            return $sub_total;
        }

        return $currency_plugin_helper->getCartSubtotal( $sub_total, $cart_data );
        /*$current_currency = $this->getCurrentCurrencyCode();
        return $this->convertToDefaultCurrency( $sub_total, $current_currency );*/
    }

    function convertToDefaultCurrency( $amount, $current_currency_code ) {
        $currency_plugin_helper = $this->getActivePluginObject();
        if ( empty( $currency_plugin_helper ) ) {
            return $amount;
        }

        return $currency_plugin_helper->convertToDefaultCurrency( $amount, $current_currency_code );
        /*$default_currency = $this->getDefaultCurrency();
        if ( ! empty( $default_currency ) && $default_currency == $current_currency_code ) {
            return $amount;
        }
        if ( $this->isEnableRealMagCurrency() ) {
            global $WOOCS;
            $currencies = $WOOCS->get_currencies();
            $rate       = isset( $currencies[ $current_currency_code ]['rate'] ) && ! empty( $currencies[ $current_currency_code ]['rate'] ) ? $currencies[ $current_currency_code ]['rate'] : 0;
            $decimal    = isset( $currencies[ $current_currency_code ]['decimals'] ) && ! empty( $currencies[ $current_currency_code ]['decimals'] ) ? $currencies[ $current_currency_code ]['decimals'] : 2;
            if ( $rate > 0 ) {
                $amount = $WOOCS->back_convert( $amount, $rate, $decimal );
            }
            return (float) $amount;
        }
        if ( $this->isEnabledVilaThemeCurrency() && class_exists( '\WOOMULTI_CURRENCY_F_Data' ) ) {
            return (float) wmc_revert_price( $amount, $current_currency_code );
        }
        if ( $this->isEnabledWPMLCurrency() ) {
            global $woocommerce_wpml;
            return (float) $woocommerce_wpml->multi_currency->prices->unconvert_price_amount( $amount, $current_currency_code );
        }
        if ( $this->isEnabledAeliaoCurrency() ) {
            return (float) $GLOBALS['woocommerce-aelia-currencyswitcher']->convert( $amount, $current_currency_code, $default_currency, $price_decimals = null, $include_markup = true );
        }
        return $amount;*/
    }

    static function getOrderSubtotal( $sub_total, $order_data ) {
        $currency_plugin_helper = self::getActivePluginObject();
        if ( empty( $currency_plugin_helper ) ) {
            return $sub_total;
        }

        return $currency_plugin_helper->getOrderSubtotal( $sub_total, $order_data );
        /*$woocommerce_helper = Woocommerce::getInstance();
        $order              = $woocommerce_helper->getOrder( $order_data );
        $order_currency     = $woocommerce_helper->isMethodExists( $order, 'get_currency' ) ? $order->get_currency() : '';
        if ( ! empty( $order_currency ) ) {
            return $this->convertToDefaultCurrency( $sub_total, $order_currency );
        }
        return $sub_total;*/
    }

    function handleConditionOrderTotal( $total, $order ) {
        return $this->convertOrderTotal( $total, $order );
    }

    static function convertOrderTotal( $total, $order ) {
        $currency_plugin_helper = self::getActivePluginObject();
        if ( empty( $currency_plugin_helper ) ) {
            return $total;
        }

        return $currency_plugin_helper->convertOrderTotal( $total, $order );
        /*$woocommerce_helper = Woocommerce::getInstance();
        $order_currency     = $woocommerce_helper->isMethodExists( $order, 'get_currency' ) ? $order->get_currency() : '';
        $default_currency   = $this->getDefaultCurrency();
        if ( $order_currency != $default_currency ) {
            $total = $this->convertToDefaultCurrency( $total, $order_currency );
        }
        return $total;*/
    }

    static function handleWoocommerceHelperOrderTotal( $total, $order ) {
        return self::convertOrderTotal( $total, $order );
    }

    static function getDisplayCurrency() {
        $convert_amount = self::isEnabledConversionInPage();
        if ( $convert_amount ) {
            return self::getCurrentCurrencyCode();
        }

        return self::getDefaultCurrency();
    }

    static function isEnabledConversionInPage() {
        $options = get_option( 'wlac_settings', array() );

        return ( empty( $options ) || ! isset( $options['enable_conversion_in_page'] ) ) || ( $options['enable_conversion_in_page'] == 'yes' );
    }

    static function getCurrentCurrencyCode( $code = '' ) {
        $currency_plugin_helper = self::getActivePluginObject();
        if ( empty( $currency_plugin_helper ) ) {
            return $code;
        }

        return $currency_plugin_helper->getCurrentCurrencyCode( $code );
        /*if ( $this->isEnableRealMagCurrency() ) {
            global $WOOCS;
            return isset( $WOOCS->current_currency ) ? $WOOCS->current_currency : $code;
        }
        if ( $this->isEnabledVilaThemeCurrency() && class_exists( '\WOOMULTI_CURRENCY_F_Data' ) ) {
            $setting = \WOOMULTI_CURRENCY_F_Data::get_ins();
            return $setting->get_current_currency();
        }
        if ( $this->isEnabledWPMLCurrency() ) {
            global $woocommerce_wpml;
            $multi_currency = $woocommerce_wpml->get_multi_currency();
            return $multi_currency->get_client_currency();
        }
        if ( $this->isEnabledAeliaoCurrency() ) {
            return $GLOBALS['woocommerce-aelia-currencyswitcher']->get_selected_currency();
        }
        return $code;*/
    }

    static function getDefaultCurrency( $code = '' ) {
        $currency_plugin_helper = self::getActivePluginObject();
        if ( empty( $currency_plugin_helper ) ) {
            return $code;
        }

        return $currency_plugin_helper->getDefaultCurrency( $code );
        /*if ($this->isEnableRealMagCurrency()) {
            global $WOOCS;
            return $WOOCS->default_currency;
        }
        if ($this->isEnabledVilaThemeCurrency() && class_exists('\WOOMULTI_CURRENCY_F_Data')) {
            $setting = \WOOMULTI_CURRENCY_F_Data::get_ins();
            return $setting->get_default_currency();
        }
        if ($this->isEnabledWPMLCurrency()) {
            return wcml_get_woocommerce_currency_option();
        }
        if ($this->isEnabledAeliaoCurrency()) {
            return $GLOBALS['woocommerce-aelia-currencyswitcher']->base_currency();
        }
        return $code;*/
    }

    public static function saveSettings() {
        $response   = array();
        $wlac_nonce = (string)Input::get('wlac_nonce');

        if ( ! Woocommerce::hasAdminPrivilege() || ! Woocommerce::verify_nonce( $wlac_nonce, 'wlac-setting-nonce' ) ) {
            $response['error']   = true;
            $response['message'] = esc_html__( 'Settings not saved!', 'wp-loyalty-auto-currency' );
            wp_send_json( $response );
        }
        $key = (string) Input::get( 'option_key' );
        $key = Woocommerce::validateInputAlpha( $key );
        if ( ! empty( $key ) ) {
            $enable_conversion_in_page = Input::get( 'enable_conversion_in_page' );
            if ( ! in_array( $enable_conversion_in_page, array( 'yes', 'no' ) ) ) {
                $response['error']       = true;
                $response['field_error'] = array(
                    'enable_conversion_in_page' => __( 'This field have invalid strings', 'wp-loyalty-auto-currency' )
                );
                $response['message']     = esc_html__( 'Settings not saved!', 'wp-loyalty-auto-currency' );
                wp_send_json( $response );
            }
            $data = array(
                'enable_conversion_in_page' => $enable_conversion_in_page
            );
            update_option( $key, $data, true );
            do_action( 'wlac_after_save_settings', $data, $key );
            $response['error']   = false;
            $response['message'] = esc_html__( 'Settings saved successfully!', 'wp-loyalty-auto-currency' );
        } else {
            $response['error']   = true;
            $response['message'] = esc_html__( 'Settings not saved!', 'wp-loyalty-auto-currency' );
        }
        wp_send_json( $response );
    }

    static function handleActionShortCodes( $user_reward_list ) {
        foreach ( $user_reward_list as $user_reward ) {
            if ( $user_reward->discount_type == 'points_conversion' ) {
                $default_currency = self::getDefaultCurrency();
                $conversion_value = self::convertDefaultToCurrentAmount( wc_price( $user_reward->discount_value, array( 'currency' => $default_currency ) ), $user_reward->discount_value, true, $default_currency );
                $short_codes      = array(
                    '[wlr_conversion_value]' => sanitize_text_field( $conversion_value )
                );
                foreach ( $short_codes as $key => $value ) {
                    $user_reward->name        = str_replace( $key, $value, $user_reward->name );
                    $user_reward->description = str_replace( $key, $value, $user_reward->description );
                }
            }
        }

        return $user_reward_list;
    }

    static function convertDefaultToCurrentAmount( $amount, $original_amount, $with_symbol, $currency ) {

        if ( $original_amount <= 0 ) {
            return $amount;
        }
        $currency_plugin_helper = self::getActivePluginObject();
        if ( empty( $currency_plugin_helper ) ) {
            return $amount;
        }
        $convert_amount = self::isEnabledConversionInPage();
        if ( ! $convert_amount ) {
            if ( $with_symbol && $currency ) {
                return $currency_plugin_helper->getPriceFormat( $original_amount, $currency );
            }

            return $amount;
        }
        $current_currency = $currency_plugin_helper->getCurrentCurrencyCode( $currency );
        if ( $current_currency == $currency ) {
            return apply_filters( 'wlac_after_convert_amount', $amount, $original_amount, $with_symbol, $currency );
        }
        $amount = $modified_amount = $currency_plugin_helper->convertToCurrentCurrency( $original_amount, $currency );
        if ( $with_symbol ) {
            $amount = $currency_plugin_helper->getPriceFormat( $modified_amount, $current_currency );
            /*$woocommerce_helper = new Woocommerce();
            $currency_symbol = $woocommerce_helper->getCurrencySymbols($current_currency);
            $amount = number_format($modified_amount, 2, '.', ',');
            $formatted_price = '<span class="woocommerce-Price-currencySymbol">' . $currency_symbol . '</span>' . $amount;
            $amount = '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted_price . '</bdi></span>';*/
        }

        return apply_filters( 'wlac_after_convert_amount', $amount, $modified_amount, $with_symbol, $current_currency );
    }

    static function handleRewardShortCodes( $reward_list ) {
        foreach ( $reward_list as $reward ) {
            if ( $reward->discount_type == 'points_conversion' ) {
                $default_currency = self::getDefaultCurrency();
                $conversion_value = self::convertDefaultToCurrentAmount( wc_price( $reward->discount_value, array( 'currency' => $default_currency ) ), $reward->discount_value, true, $default_currency );
                $short_codes      = array(
                    '[wlr_conversion_value]' => sanitize_text_field( $conversion_value )
                );
                foreach ( $short_codes as $key => $value ) {
                    $reward->name        = str_replace( $key, $value, $reward->name );
                    $reward->description = str_replace( $key, $value, $reward->description );
                }
            }
        }

        return $reward_list;
    }
}