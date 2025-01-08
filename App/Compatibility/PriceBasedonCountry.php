<?php

namespace Wlac\App\Compatibility;

use Wlac\App\Helpers\Woocommerce;

class PriceBasedonCountry implements Currency {
	public static $instance = null;

	/**
	 * Get default product price.
	 *
	 * @param $product_price
	 * @param $product
	 * @param $item
	 * @param $is_redeem
	 * @param $order_currency
	 *
	 * @return mixed
	 */
	function getDefaultProductPrice( $product_price, $product, $item, $is_redeem, $order_currency ) {
		if ( ! empty( $product ) && $product instanceof \WC_Product ) {
			$productPrice = method_exists( $product, 'get_price' ) ? $product->get_price() : 0;
			if ( $product_price != $productPrice ) {
				$product_price = $productPrice;
			}
		}

		return $this->getProductPrice( $product_price, $item, $is_redeem, $order_currency );
	}

	/**
	 * Get product price.
	 *
	 * @param $product_price
	 * @param $item
	 * @param $is_redeem
	 * @param $order_currency
	 *
	 * @return mixed
	 */
	function getProductPrice( $product_price, $item, $is_redeem, $order_currency ) {
		if ( empty( $order_currency ) ) {
			$order_currency = $this->getCurrentCurrencyCode( $order_currency );
		}
		$default_currency = $this->getDefaultCurrency();
		if ( $order_currency !== $default_currency ) {
			$product_price = $this->convertToDefaultCurrency( $product_price, $order_currency );
		}

		return $product_price;
	}

	/**
	 * Get current currency code.
	 *
	 * @param $code
	 *
	 * @return string
	 */
	function getCurrentCurrencyCode( $code = '' ) {
		return get_woocommerce_currency();
	}

	/**
	 * Get default currency code.
	 *
	 * @param $code
	 *
	 * @return string
	 */
	function getDefaultCurrency( $code = '' ) {
		return wcpbc_get_base_currency();
	}

	/**
	 * Convert to default currency.
	 *
	 * @param $amount
	 * @param $current_currency_code
	 *
	 * @return mixed
	 */
	function convertToDefaultCurrency( $amount, $current_currency_code ) {
		foreach ( \WCPBC_Pricing_Zones::get_zones() as $zone ) {
			if ( $current_currency_code === $zone->get_currency() ) {
				return $zone->get_base_currency_amount( $amount );
			}
		}

		return $amount;
	}

	/**
	 * Get order subtotal.
	 *
	 * @param $sub_total
	 * @param $order_data
	 *
	 * @return float|int|mixed
	 */
	function getOrderSubtotal( $sub_total, $order_data ) {
		return $this->convertOrderTotal( $sub_total, $order_data );
	}

	/**
	 * Convert order total.
	 *
	 * @param $total
	 * @param $order
	 *
	 * @return float|int|mixed
	 */
	function convertOrderTotal( $total, $order ) {
		$woocommerce_helper = Woocommerce::getInstance();
		$order              = $woocommerce_helper->getOrder( $order );
		$zone               = \WCPBC_Pricing_Zones::get_zone_from_order( $order );
		if ( $zone ) {
			$total = $zone->get_base_currency_amount( $total );
		}

		return $total;
	}

	/**
	 * Get Instance Object.
	 *
	 * @param array $config
	 *
	 * @return self|null
	 */
	public static function getInstance( array $config = [] ) {
		if ( ! self::$instance ) {
			self::$instance = new self( $config );
		}

		return self::$instance;
	}

	/**
	 * Get cart subtotal.
	 *
	 * @param $sub_total
	 * @param $cart_data
	 *
	 * @return float|int|mixed
	 */
	function getCartSubtotal( $sub_total, $cart_data ) {
		if ( wcpbc_the_zone() ) {
			$sub_total = wcpbc_the_zone()->get_base_currency_amount( $sub_total );
		}

		return $sub_total;
	}

	/**
	 * Convert to current currency.
	 *
	 * @param $original_amount
	 * @param $default_currency
	 *
	 * @return float|mixed
	 */
	function convertToCurrentCurrency( $original_amount, $default_currency ) {
		if ( wcpbc_the_zone() ) {
			$original_amount = wcpbc_the_zone()->get_exchange_rate_price( $original_amount );
		}

		return $original_amount;
	}

	/**
	 * Get price format.
	 *
	 * @param $amount
	 * @param $code
	 *
	 * @return false|string
	 */
	function getPriceFormat( $amount, $code = '' ) {
		/*echo "Amount : " . $amount;
		echo "Code : " . $code;*/
		if ( empty( $code ) ) {
			return false;
		}
		$currency_symbol = get_woocommerce_currency_symbol();
		$num_decimal     = wc_get_price_decimals();
		$decimal_sep     = wc_get_price_decimal_separator();
		$thousand_sep    = wc_get_price_thousand_separator();
		$amount          = number_format( $amount, $num_decimal, $decimal_sep, $thousand_sep );
		$price_format    = str_replace( [ '%1$s', '%2$s' ], [ '%s', '%s' ], get_woocommerce_price_format() );
		$formatted_price = sprintf( $price_format, '<span class="woocommerce-Price-currencySymbol">' . $currency_symbol . '</span>', $amount );

		return '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted_price . '</bdi></span>';
	}
}