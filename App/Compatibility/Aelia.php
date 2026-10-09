<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlac\App\Compatibility;

use Wlac\App\Helpers\Woocommerce;

class Aelia implements Currency
{
    public static $instance = null;

    function getDefaultProductPrice($product_price, $product, $item, $is_redeem, $order_currency)
    {
        if (is_object($product) && (float)$product->get_price('edit') === (float)get_post_meta($product->get_id(), '_price', true)) {
            return $product_price;
        }
        $current_code = isset($GLOBALS['woocommerce-aelia-currencyswitcher']) ? $GLOBALS['woocommerce-aelia-currencyswitcher']->get_selected_currency() : '';
        if (!empty($current_code)) {
            $product_price = $this->convertToDefaultCurrency($product_price, $current_code);
        }
        return $product_price;
    }

    function convertToDefaultCurrency($amount, $current_currency_code)
    {
        $default_currency = $this->getDefaultCurrency();
        if (!empty($default_currency) && $default_currency == $current_currency_code) {
            return $amount;
        }
        return (float)$GLOBALS['woocommerce-aelia-currencyswitcher']->convert($amount, $current_currency_code, $default_currency, $price_decimals = null, $include_markup = true);
    }

    function getDefaultCurrency($code = '')
    {
        return $GLOBALS['woocommerce-aelia-currencyswitcher']->base_currency();
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
        return $GLOBALS['woocommerce-aelia-currencyswitcher']->get_selected_currency();
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
        $price_decimals = null;
        return (float)$GLOBALS['woocommerce-aelia-currencyswitcher']->convert($original_amount, $default_currency, $current_currency_code, $price_decimals, $include_markup = true);
    }

    function getPriceFormat($amount, $code = '')
    {
        if (empty($code)) {
            return $amount;
        }
        if (!isset($GLOBALS['woocommerce-aelia-currencyswitcher'])) return $amount;
        $settings = $GLOBALS['woocommerce-aelia-currencyswitcher']::settings()->current_settings();
        $currency = is_array($settings['exchange_rates']) && isset($settings['exchange_rates'][$code]) && is_array($settings['exchange_rates'][$code]) ? $settings['exchange_rates'][$code] : array();
        if (empty($currency)) {
            return $amount;
        }
        $currency_symbol = $this->getCurrencySymbol($currency, $code);
        $num_decimal = is_array($currency) && !empty($currency['decimals']) ? $currency['decimals'] : wc_get_price_decimals();
        $decimal_sep = is_array($currency) && !empty($currency['decimal_separator']) ? $currency['decimal_separator'] : wc_get_price_decimal_separator();
        $thousand_sep = is_array($currency) && !empty($currency['thousand_separator']) ? $currency['thousand_separator'] : wc_get_price_thousand_separator();
        $amount = number_format($amount, $num_decimal, $decimal_sep, $thousand_sep);
        $price_format = $this->getFormat($currency, $code);
        $formatted_price = sprintf($price_format, '<span class="woocommerce-Price-currencySymbol">' . $currency_symbol . '</span>', $amount);
        return '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted_price . '</bdi></span>';
    }

    protected function getCurrencySymbol($current_currency, $code)
    {
        $woocommerce_helper = new Woocommerce();
        if (!is_array($current_currency)) {
            return $woocommerce_helper->getCurrencySymbols($code);
        }
        if (isset($current_currency['symbol']) && !empty($current_currency['symbol'])) {
            return $current_currency['symbol'];
        }
        return $woocommerce_helper->getCurrencySymbols($code);
    }

    protected function getFormat($currency, $code = '')
    {
        $format = get_woocommerce_price_format();
        if (empty($code)) {
            return $format;
        }
        if (is_array($currency) && !empty($currency['symbol_position'])) {
            switch ($currency['symbol_position']) {
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

}