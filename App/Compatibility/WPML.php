<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlac\App\Compatibility;

use Wlac\App\Helpers\Woocommerce;

class WPML implements Currency
{
    public static $instance = null;
    public static $currency_list = array();

    function getDefaultProductPrice($product_price, $product, $item, $is_redeem, $order_currency)
    {
        global $woocommerce_wpml;
        $multi_currency = $woocommerce_wpml->get_multi_currency();
        $current_code = $multi_currency->get_client_currency();
        return $this->convertToDefaultCurrency($product_price, $current_code);
    }

    function convertToDefaultCurrency($amount, $current_currency_code)
    {
	    if(is_admin()) {
		    return $amount;
	    }
        $default_currency = $this->getDefaultCurrency();
        if (!empty($default_currency) && $default_currency == $current_currency_code) {
            return $amount;
        }
        global $woocommerce_wpml;
        return (float)$woocommerce_wpml->multi_currency->prices->unconvert_price_amount($amount, $current_currency_code);
    }

    function getDefaultCurrency($code = '')
    {
        return wcml_get_woocommerce_currency_option();
    }

    function getProductPrice($product_price, $item, $is_redeem, $order_currency)
    {
        if (empty($order_currency)) {
            $order_currency = $this->getCurrentCurrencyCode($order_currency);
        }
        $default_currency = $this->getDefaultCurrency();
        if ($order_currency == $default_currency) {
            return $product_price;
        }
        if (!empty($order_currency)) {
            return $this->convertToDefaultCurrency($product_price, $order_currency);
        }
        return $product_price;
    }

    function getCurrentCurrencyCode($code = '')
    {
        global $woocommerce_wpml;
        $multi_currency = $woocommerce_wpml->get_multi_currency();
        return $multi_currency->get_client_currency();
    }

    function convertOrderTotal($total, $order)
    {
        $woocommerce_helper = Woocommerce::getInstance();
        $order = $woocommerce_helper->getOrder($order);
        $order_currency = $woocommerce_helper->isMethodExists($order, 'get_currency') ? $order->get_currency() : '';
        if (!empty($order_currency)) {
            return $this->convertToDefaultCurrency($total, $order_currency);
        }
        return $total;
    }

    public static function getInstance(array $config = array())
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    function getCartSubtotal($sub_total, $cart_data)
    {
        $current_currency = $this->getCurrentCurrencyCode();
        return $this->convertToDefaultCurrency($sub_total, $current_currency);
    }

    function getOrderSubtotal($sub_total, $order_data)
    {
        $woocommerce_helper = Woocommerce::getInstance();
        $order = $woocommerce_helper->getOrder($order_data);
        $order_currency = $woocommerce_helper->isMethodExists($order, 'get_currency') ? $order->get_currency() : '';
        if (!empty($order_currency)) {
            return $this->convertToDefaultCurrency($sub_total, $order_currency);
        }
        return $sub_total;
    }

    function convertToCurrentCurrency($original_amount, $default_currency)
    {
        $current_currency_code = $this->getCurrentCurrencyCode();
        global $woocommerce_wpml;
        return (float)$woocommerce_wpml->multi_currency->prices->convert_price_amount_by_currencies($original_amount, $default_currency, $current_currency_code);
    }

    function getPriceFormat($amount, $code = '')
    {
        if (empty($code)) {
            return false;
        }
        $currency = $this->getCurrencyDetails($code);
        $num_decimal = is_array($currency) && !empty($currency['num_decimals']) ? $currency['num_decimals'] : wc_get_price_decimals();
        $decimal_sep = is_array($currency) && !empty($currency['decimal_sep']) ? $currency['decimal_sep'] : wc_get_price_decimal_separator();
        $thousand_sep = is_array($currency) && !empty($currency['thousand_sep']) ? $currency['thousand_sep'] : wc_get_price_thousand_separator();
        $woocommerce_helper = Woocommerce::getInstance();
        $currency_symbol = $woocommerce_helper->getCurrencySymbols($code);
        $amount = number_format($amount, $num_decimal, $decimal_sep, $thousand_sep);
        $price_format = $this->getFormat($code);
        $formatted_price = sprintf($price_format, '<span class="woocommerce-Price-currencySymbol">' . $currency_symbol . '</span>', $amount);
        return '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted_price . '</bdi></span>';
    }

    protected function getFormat($code = '')
    {
        $format = get_woocommerce_price_format();
        if (empty($code)) {
            return $format;
        }
        $currency = $this->getCurrencyDetails($code);
        if (is_array($currency) && !empty($currency['position'])) {
            switch ($currency['position']) {
                case 'left':
                    $format = '%1$s%2$s';
                    break;
                case 'right':
                    $format = '%2$s%1$s';
                    break;
                case 'left_space':
                    $format = '%1$s&nbsp;%2$s';
                    break;
                case 'right_space':
                    $format = '%2$s&nbsp;%1$s';
                    break;
            }
        }
        return $format;
    }

    function getCurrencyDetails($code = '')
    {
        if (empty($code)) return array();
        if (isset(self::$currency_list[$code]) && !empty(self::$currency_list[$code])) return self::$currency_list[$code];
        global $woocommerce_wpml;
        $multi_currency = $woocommerce_wpml->get_multi_currency();
        return self::$currency_list[$code] = $multi_currency->get_currency_details_by_code($code);
    }
}