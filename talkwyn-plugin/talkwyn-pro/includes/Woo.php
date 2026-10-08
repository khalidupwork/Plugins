<?php
/**
 * WooCommerce: product cards in chat and order status lookup.
 *
 * Order lookup needs the order number and the billing email of that order,
 * and is rate limited per visitor.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce features.
 */
final class Woo {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		add_filter( 'talkwyn_reply_extra', array( self::class, 'cards' ), 10, 3 );
		add_filter( 'talkwyn_before_answer', array( self::class, 'order_lookup' ), 8, 2 );
	}

	/**
	 * Product cards for products found in the answer's sources.
	 *
	 * @param array  $extra Extra.
	 * @param array  $ctx   Context.
	 * @param string $reply Reply.
	 * @return array
	 */
	public static function cards( $extra, $ctx, $reply ) {
		if ( ! \Talkwyn_Settings::get( 'pro_woo_cards', 1 ) || ! empty( $ctx['is_social'] ) || empty( $ctx['chunks'] ) ) {
			return $extra;
		}
		$cards = array();
		$seen  = array();
		foreach ( (array) $ctx['chunks'] as $chunk ) {
			if ( 'product' !== ( $chunk['source_type'] ?? '' ) ) {
				continue;
			}
			$id = (int) ( $chunk['source_id'] ?? 0 );
			if ( ! $id && preg_match( '/^post:(\d+)$/', (string) ( $chunk['source_key'] ?? '' ), $m ) ) {
				$id = (int) $m[1];
			}
			if ( ! $id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$card        = self::card( $id );
			if ( $card ) {
				$cards[] = $card;
			}
			if ( count( $cards ) >= 3 ) {
				break;
			}
		}
		if ( $cards ) {
			$extra['cards'] = $cards;
		}
		return $extra;
	}

	/**
	 * One product card.
	 *
	 * @param int $id Product ID.
	 * @return array|null
	 */
	public static function card( int $id ): ?array {
		$product = wc_get_product( $id );
		if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_visible() ) {
			return null;
		}
		$image = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
		$cart  = $product->is_purchasable() && $product->is_in_stock() && $product->is_type( 'simple' );
		return array(
			'id'       => $id,
			'title'    => html_entity_decode( wp_strip_all_tags( $product->get_name() ), ENT_QUOTES, 'UTF-8' ),
			'url'      => (string) $product->get_permalink(),
			'image'    => $image ? (string) $image : '',
			'price'    => trim( html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ) ),
			'stock'    => $product->is_in_stock(),
			'cart_url' => $cart ? (string) $product->add_to_cart_url() : '',
			'labels'   => array(
				'view' => __( 'View product', 'talkwyn-pro' ),
				'cart' => __( 'Add to cart', 'talkwyn-pro' ),
				'out'  => __( 'Out of stock', 'talkwyn-pro' ),
			),
		);
	}

	/**
	 * Find an order number and email in a message. Pure, unit tested.
	 *
	 * @param string $message Message.
	 * @return array{intent:bool,number:int,email:string}
	 */
	public static function parse_order_message( string $message ): array {
		$intent = (bool) preg_match( '/\b(order|orders|tracking|track|shipment|shipped|delivery status|where is my|my package|bestelling|commande|pedido|bestellung|ordine|encomenda|sipariş|siparis|طلب|آرڈر|ऑर्डर|订单|注文|주문|заказ|zamówienie|zamowienie)\b/iu', $message );
		$email  = preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $message, $m ) ? strtolower( $m[0] ) : '';
		$clean  = '' !== $email ? str_replace( $m[0], ' ', $message ) : $message;
		$number = preg_match( '/(?:#|no\.?|number|nr\.?|n°|nº)?\s*(\d{2,10})\b/iu', $clean, $n ) ? (int) $n[1] : 0;
		return array(
			'intent' => $intent,
			'number' => $number,
			'email'  => $email,
		);
	}

	/**
	 * Answer order status questions.
	 *
	 * @param array|null $preset Preset.
	 * @param array      $ctx    Context.
	 * @return array|null
	 */
	public static function order_lookup( $preset, $ctx ) {
		if ( is_array( $preset ) || ! \Talkwyn_Settings::get( 'pro_woo_orders', 1 ) || ! function_exists( 'wc_get_order' ) ) {
			return $preset;
		}
		$session = (string) $ctx['session'];
		$flags   = \Talkwyn_History::flags( $session );
		$parsed  = self::parse_order_message( (string) $ctx['message'] );
		$asked   = ! empty( $flags['order_ask'] );
		if ( ! $parsed['intent'] && ! ( $asked && ( $parsed['number'] || $parsed['email'] ) ) ) {
			return $preset;
		}
		if ( ! $parsed['intent'] && ! $asked ) {
			return $preset;
		}
		$number = $parsed['number'] ? $parsed['number'] : (int) ( $flags['order_number'] ?? 0 );
		$email  = '' !== $parsed['email'] ? $parsed['email'] : (string) ( $flags['order_email'] ?? '' );
		if ( ! $number || '' === $email ) {
			// Only start the lookup for clear status questions.
			if ( ! $asked && ! preg_match( '/\b(status|where|track|tracking|shipped|arrive|deliver|delivery)\b/iu', (string) $ctx['message'] ) && ! $parsed['number'] ) {
				return $preset;
			}
			\Talkwyn_History::set_flag( $session, 'order_ask', 1 );
			\Talkwyn_History::set_flag( $session, 'order_number', $number );
			\Talkwyn_History::set_flag( $session, 'order_email', $email );
			return array(
				'text'     => ! $number && '' === $email
					? __( 'I can check that. Please send your order number and the billing email you used for the order.', 'talkwyn-pro' )
					: ( ! $number ? __( 'Thanks. What is the order number?', 'talkwyn-pro' ) : __( 'Thanks. What is the billing email on the order?', 'talkwyn-pro' ) ),
				'provider' => 'order-lookup',
			);
		}
		\Talkwyn_History::set_flag( $session, 'order_ask', 0 );
		\Talkwyn_History::set_flag( $session, 'order_number', 0 );
		\Talkwyn_History::set_flag( $session, 'order_email', '' );
		$s = \Talkwyn_Settings::all();
		if ( ! \Talkwyn_Rate_Limiter::hit( 'order', $session . '@' . \Talkwyn_Rate_Limiter::client_ip( ! empty( $s['trusted_proxy'] ) ), 5 ) ) {
			return array(
				'text'     => __( 'Too many order lookups. Please try again in an hour or contact the team.', 'talkwyn-pro' ),
				'provider' => 'order-lookup',
			);
		}
		return array(
			'text'     => self::order_text( $number, $email ),
			'provider' => 'order-lookup',
		);
	}

	/**
	 * Status text for a verified order.
	 *
	 * @param int    $number Order number.
	 * @param string $email  Billing email.
	 */
	private static function order_text( int $number, string $email ): string {
		$not_found = __( 'I could not find an order with that number and email. Please check both and try again.', 'talkwyn-pro' );
		$order     = wc_get_order( (int) apply_filters( 'talkwyn_pro_order_id', $number ) );
		if ( ! $order || ! method_exists( $order, 'get_billing_email' ) ) {
			return $not_found;
		}
		if ( ! hash_equals( strtolower( trim( (string) $order->get_billing_email() ) ), strtolower( trim( $email ) ) ) ) {
			return $not_found;
		}
		$lines = array(
			/* translators: 1: order number, 2: status */
			sprintf( __( 'Order #%1$s is **%2$s**.', 'talkwyn-pro' ), $order->get_order_number(), wc_get_order_status_name( $order->get_status() ) ),
		);
		if ( $order->get_date_created() ) {
			/* translators: %s: date */
			$lines[] = sprintf( __( 'Placed on %s.', 'talkwyn-pro' ), wc_format_datetime( $order->get_date_created() ) );
		}
		/* translators: %s: order total */
		$lines[] = sprintf( __( 'Total: %s.', 'talkwyn-pro' ), html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ) );
		$items   = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = '- ' . $item->get_name() . ' x ' . $item->get_quantity();
		}
		if ( $items ) {
			$lines[] = __( 'Items:', 'talkwyn-pro' ) . "\n" . implode( "\n", array_slice( $items, 0, 10 ) );
		}
		$tracking = $order->get_meta( '_wc_shipment_tracking_items' );
		if ( is_array( $tracking ) ) {
			foreach ( $tracking as $t ) {
				if ( ! empty( $t['tracking_number'] ) ) {
					/* translators: 1: carrier, 2: tracking number */
					$lines[] = sprintf( __( 'Tracking: %1$s %2$s', 'talkwyn-pro' ), (string) ( $t['tracking_provider'] ?? $t['custom_tracking_provider'] ?? '' ), (string) $t['tracking_number'] );
				}
			}
		}
		return (string) apply_filters( 'talkwyn_pro_order_text', implode( "\n", $lines ), $order );
	}
}
