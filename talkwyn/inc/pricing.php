<?php
/**
 * Pricing page components: plan cards (prices from WooCommerce), comparison table,
 * cost explainer.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renewal discount from Talkwyn Hub (when active), else null.
 */
function talkwyn_hub_renewal_discount(): ?float {
	if ( class_exists( '\TWH\Support\Settings' ) ) {
		return (float) \TWH\Support\Settings::get( 'renewal_discount' );
	}
	return null;
}

/**
 * Plan definitions. Feature lists only use features described in the site copy.
 *
 * @return array<string, array<string, mixed>>
 */
function talkwyn_plans(): array {
	$plans = array(
		'free'     => array(
			'name'     => __( 'Free', 'talkwyn' ),
			'sites'    => __( 'Unlimited sites', 'talkwyn' ),
			'tagline'  => __( 'Everything you need to answer visitors and collect leads.', 'talkwyn' ),
			'features' => array(
				__( 'One-click site scan', 'talkwyn' ),
				__( 'Multilingual answers', 'talkwyn' ),
				__( 'Lead capture', 'talkwyn' ),
				__( 'Chat history', 'talkwyn' ),
				__( 'Free AI providers with automatic fallback', 'talkwyn' ),
			),
		),
		'personal' => array(
			'name'     => __( 'Personal', 'talkwyn' ),
			'sites'    => __( '1 site', 'talkwyn' ),
			'tagline'  => __( 'Pro features for your own website.', 'talkwyn' ),
			'features' => array(
				__( 'Everything in Free', 'talkwyn' ),
				__( 'Paid AI models: OpenAI, Anthropic Claude, Mistral, DeepSeek', 'talkwyn' ),
				__( 'Smart search (meaning-based search)', 'talkwyn' ),
				__( 'PDF, DOCX and TXT files, web pages and sitemaps as knowledge', 'talkwyn' ),
				__( 'Analytics and an unanswered questions inbox', 'talkwyn' ),
				__( 'WooCommerce product cards and order status lookup', 'talkwyn' ),
				__( 'Lead alerts on Slack and Telegram', 'talkwyn' ),
				__( 'Proactive messages and business hours', 'talkwyn' ),
				__( '1 year of updates', 'talkwyn' ),
			),
		),
		'business' => array(
			'name'     => __( 'Business', 'talkwyn' ),
			'sites'    => __( '5 sites', 'talkwyn' ),
			'tagline'  => __( 'For businesses with more than one website.', 'talkwyn' ),
			'featured' => true,
			'features' => array(
				__( 'Everything in Personal', 'talkwyn' ),
				__( 'Use it on 5 production sites', 'talkwyn' ),
				__( 'Staging and local sites are free', 'talkwyn' ),
				__( '1 year of updates', 'talkwyn' ),
			),
		),
		'agency'   => array(
			'name'     => __( 'Agency', 'talkwyn' ),
			'sites'    => __( 'Unlimited sites', 'talkwyn' ),
			'tagline'  => __( 'One license for every client site.', 'talkwyn' ),
			'features' => array(
				__( 'Everything in Business', 'talkwyn' ),
				__( 'Unlimited client sites', 'talkwyn' ),
				__( 'White label: your logo and menu name', 'talkwyn' ),
				__( 'Copy settings from one site to the next', 'talkwyn' ),
			),
		),
	);
	if ( talkwyn_setting( 'show_lifetime' ) ) {
		$plans['lifetime'] = array(
			'name'     => __( 'Lifetime', 'talkwyn' ),
			'sites'    => __( 'Pay once', 'talkwyn' ),
			'tagline'  => __( 'Pro features with no renewals.', 'talkwyn' ),
			'features' => array(
				__( 'Everything in Business', 'talkwyn' ),
				__( 'Never expires', 'talkwyn' ),
			),
		);
	}
	return $plans;
}

/**
 * [tw_plans] Plan cards. Paid plans add the mapped WooCommerce product to the cart
 * and go straight to checkout.
 */
add_shortcode(
	'tw_plans',
	static function () {
		$html = '<div class="tw-plans" id="plans">';
		foreach ( talkwyn_plans() as $key => $plan ) {
			$featured = ! empty( $plan['featured'] );
			$html    .= '<article class="tw-plan' . ( $featured ? ' tw-plan--featured' : '' ) . '" aria-labelledby="tw-plan-' . esc_attr( $key ) . '">';
			if ( $featured ) {
				$html .= '<span class="tw-plan__flag tw-badge tw-badge--pro">' . esc_html__( 'Most popular', 'talkwyn' ) . '</span>';
			}
			$html .= '<h2 class="tw-plan__name" id="tw-plan-' . esc_attr( $key ) . '">' . esc_html( $plan['name'] ) . '</h2>';
			if ( 'free' === $key ) {
				$html .= '<p class="tw-plan__price">' . esc_html( talkwyn_price_zero() ) . '</p>';
			} else {
				$price = talkwyn_plan_price( $key );
				$per   = 'lifetime' === $key ? __( 'one time', 'talkwyn' ) : __( 'per year', 'talkwyn' );
				$amt   = talkwyn_plan_amounts( $key );
				if ( '' === $price ) {
					$html .= '<p class="tw-plan__price tw-plan__price--unset">' . esc_html__( 'Yearly', 'talkwyn' ) . '</p>';
				} else {
					$was   = null !== $amt['regular'] && null !== $amt['price'] && $amt['regular'] > $amt['price'] ? '<del class="tw-plan__was"><span class="screen-reader-text">' . esc_html__( 'Regular price', 'talkwyn' ) . ' </span>' . esc_html( talkwyn_money( $amt['regular'] ) ) . '</del> ' : '';
					$html .= '<p class="tw-plan__price">' . $was . esc_html( $price ) . ' <small>' . esc_html( $per ) . '</small></p>';
					if ( 'lifetime' !== $key && null !== $amt['price'] && $amt['price'] > 0 ) {
						/* translators: %s: monthly equivalent of the yearly price */
						$html .= '<p class="tw-plan__month">' . esc_html( sprintf( __( 'about %s a month, billed yearly', 'talkwyn' ), talkwyn_money( round( $amt['price'] / 12, 2 ) ) ) ) . '</p>';
					}
					if ( '' !== $was ) {
						$html .= '<p class="tw-plan__founding">' . esc_html__( 'Founding price', 'talkwyn' ) . '</p>';
					}
				}
			}
			$html .= '<p class="tw-plan__sites">' . esc_html( $plan['sites'] ) . '</p>';
			$html .= '<p>' . esc_html( $plan['tagline'] ) . '</p><ul>';
			foreach ( $plan['features'] as $feature ) {
				$html .= '<li>' . talkwyn_icon( 'check', 18 ) . '<span>' . esc_html( $feature ) . '</span></li>';
			}
			$html .= '</ul>';
			if ( 'free' === $key ) {
				$html .= '<a class="tw-btn tw-btn--secondary tw-btn--block" href="' . esc_url( talkwyn_install_url() ) . '" data-tw-event="install_click" data-tw-location="pricing_free">' . esc_html__( 'Install free', 'talkwyn' ) . '</a>';
			} else {
				/* translators: %s: plan name */
				$label = sprintf( __( 'Buy %s', 'talkwyn' ), $plan['name'] );
				$html .= '<a class="tw-btn tw-btn--block' . ( $featured ? ' tw-btn--red' : '' ) . '" href="' . esc_url( talkwyn_plan_checkout_url( $key ) ) . '" rel="nofollow" data-tw-event="checkout_start" data-tw-location="plan_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</a>';
				if ( $featured ) {
					/* translators: %d: trial days */
					$html .= '<a class="tw-plan__trial" href="#trial" data-tw-event="trial_click" data-tw-location="plan_card">' . esc_html( sprintf( __( 'or try it free for %d days', 'talkwyn' ), talkwyn_trial()['days'] ) ) . '</a>';
				}
			}
			$html .= '</article>';
		}
		return $html . '</div>';
	}
);

/**
 * Zero price in the store currency.
 */
function talkwyn_price_zero(): string {
	if ( function_exists( 'wc_price' ) ) {
		return trim( wp_strip_all_tags( html_entity_decode( wc_price( 0, array( 'decimals' => 0 ) ) ) ) );
	}
	return '$0';
}

/**
 * [tw_compare_plans] Full feature comparison table.
 */
add_shortcode(
	'tw_compare_plans',
	static function () {
		$y    = talkwyn_icon( 'check', 20, __( 'Included', 'talkwyn' ) );
		$n    = '<span class="tw-no">' . esc_html__( 'No', 'talkwyn' ) . '</span>';
		$soon = ' <span class="tw-badge tw-badge--soon">' . esc_html__( 'Coming soon', 'talkwyn' ) . '</span>';
		$off  = esc_html__( 'Off by default', 'talkwyn' );
		$rows = array(
			__( 'Sites', 'talkwyn' )                       => array( esc_html__( 'Unlimited', 'talkwyn' ), '1', '5', esc_html__( 'Unlimited', 'talkwyn' ) ),
			__( 'Staging and local sites', 'talkwyn' )     => array( esc_html__( 'Free', 'talkwyn' ), esc_html__( 'Free', 'talkwyn' ), esc_html__( 'Free', 'talkwyn' ), esc_html__( 'Free', 'talkwyn' ) ),
			__( 'One-click site scan, kept up to date', 'talkwyn' ) => array( $y, $y, $y, $y ),
			__( 'Pages, posts, WooCommerce products, Elementor', 'talkwyn' ) => array( $y, $y, $y, $y ),
			__( 'Answers in the visitor’s language', 'talkwyn' ) => array( $y, $y, $y, $y ),
			__( 'Source links under every answer', 'talkwyn' ) => array( $y, $y, $y, $y ),
			__( 'Lead capture, saved in WordPress and emailed', 'talkwyn' ) => array( $y, $y, $y, $y ),
			__( 'Chat history', 'talkwyn' )                => array( $y, $y, $y, $y ),
			__( 'Free AI providers: Groq, Gemini, OpenRouter, Cloudflare', 'talkwyn' ) => array( $y, $y, $y, $y ),
			__( 'Automatic provider fallback', 'talkwyn' ) => array( $y, $y, $y, $y ),
			__( 'Paid AI models: OpenAI, Claude, Mistral, DeepSeek', 'talkwyn' ) => array( $n, $y, $y, $y ),
			__( 'Smart search (meaning-based search)', 'talkwyn' ) => array( $n, $y, $y, $y ),
			__( 'Custom answers', 'talkwyn' )              => array( $n, $y, $y, $y ),
			__( 'Streaming replies', 'talkwyn' )           => array( $n, $y, $y, $y ),
			__( 'Analytics', 'talkwyn' )                   => array( $n, $y, $y, $y ),
			__( 'Unanswered questions inbox', 'talkwyn' )  => array( $n, $y, $y, $y ),
			__( 'PDF, DOCX and TXT files, web pages and sitemaps as knowledge', 'talkwyn' ) => array( $n, $y, $y, $y ),
			__( 'WooCommerce product cards and order lookup', 'talkwyn' ) => array( $n, $y, $y, $y ),
			__( 'Proactive messages and business hours', 'talkwyn' ) => array( $n, $y, $y, $y ),
			__( 'Lead alerts on Slack and Telegram', 'talkwyn' ) => array( $n, $y, $y, $y ),
			__( '"Powered by Talkwyn" badge in the chat', 'talkwyn' ) => array( $off, $off, $off, $off ),
			__( 'White label: your logo and menu name in the admin', 'talkwyn' ) => array( $n, $n, $n, $y ),
			__( 'Copy settings between sites', 'talkwyn' ) => array( $n, $n, $n, $y ),
			__( 'Lead alerts on WhatsApp', 'talkwyn' ) . $soon => array( $n, $y, $y, $y ),
			__( 'Lead scoring and AI summaries', 'talkwyn' ) . $soon => array( $n, $y, $y, $y ),
			__( 'Pro updates', 'talkwyn' )                 => array( $n, esc_html__( '1 year, renewable', 'talkwyn' ), esc_html__( '1 year, renewable', 'talkwyn' ), esc_html__( '1 year, renewable', 'talkwyn' ) ),
		);
		$head = '<thead><tr><th scope="col">' . esc_html__( 'Feature', 'talkwyn' ) . '</th>';
		foreach ( array( 'free', 'personal', 'business', 'agency' ) as $plan ) {
			$head .= '<th scope="col"' . ( 'business' === $plan ? ' class="tw-col-us"' : '' ) . '>' . esc_html( talkwyn_plans()[ $plan ]['name'] ) . '</th>';
		}
		$head .= '</tr></thead>';
		$body  = '<tbody>';
		foreach ( $rows as $label => $cells ) {
			$body .= '<tr><th scope="row">' . wp_kses_post( $label ) . '</th>';
			foreach ( $cells as $i => $cell ) {
				$body .= '<td' . ( 2 === $i ? ' class="tw-col-us"' : '' ) . '>' . $cell . '</td>';
			}
			$body .= '</tr>';
		}
		$body .= '</tbody>';
		return '<div class="tw-table-wrap" role="region" aria-labelledby="tw-compare-caption" tabindex="0"><table class="tw-table tw-table--compare"><caption id="tw-compare-caption" class="screen-reader-text">' . esc_html__( 'Compare every plan', 'talkwyn' ) . '</caption>' . $head . $body . '</table></div>';
	}
);

/**
 * Price and regular price of a plan as numbers (null when unknown). With WooCommerce
 * prices, a sale price is the founding price and the regular price is struck through.
 *
 * @param string $plan Plan key.
 * @return array{price: ?float, regular: ?float}
 */
function talkwyn_plan_amounts( string $plan ): array {
	$id = (int) talkwyn_setting( 'product_' . $plan );
	if ( 'woocommerce' === talkwyn_setting( 'price_source' ) && $id && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $id );
		if ( $product ) {
			$price   = '' !== (string) $product->get_price() ? (float) $product->get_price() : null;
			$regular = '' !== (string) $product->get_regular_price() ? (float) $product->get_regular_price() : $price;
			return array(
				'price'   => $price,
				'regular' => $regular,
			);
		}
	}
	$num     = static function ( string $raw ): ?float {
		return preg_match( '/(\d+(?:[.,]\d{1,2})?)/', $raw, $m ) ? (float) str_replace( ',', '.', $m[1] ) : null;
	};
	$price   = $num( (string) talkwyn_setting( 'price_' . $plan ) );
	$regular = $num( (string) talkwyn_setting( 'regular_' . $plan ) );
	return array(
		'price'   => $price,
		'regular' => null !== $regular ? $regular : $price,
	);
}

/**
 * Three years of a plan at its regular price: the first year, then two renewals with
 * the renewal discount (Talkwyn Hub setting, 20% when the Hub is not on this site).
 *
 * @param string $plan Plan key.
 */
function talkwyn_three_year_cost( string $plan ): ?float {
	$regular = talkwyn_plan_amounts( $plan )['regular'];
	if ( null === $regular ) {
		return null;
	}
	$discount = talkwyn_hub_renewal_discount();
	$discount = null === $discount ? 20.0 : $discount;
	return round( $regular + 2 * $regular * ( 1 - $discount / 100 ) );
}

/**
 * 3-year cost chart: Talkwyn Personal and Business against the hosted range in Site Settings.
 */
function talkwyn_cost_chart_html(): string {
	$range = trim( (string) talkwyn_setting( 'hosted_3yr_range' ) );
	$nums  = preg_match_all( '/\d[\d,]*/', $range, $m ) ? array_map( static fn( $n ) => (float) str_replace( ',', '', $n ), $m[0] ) : array();
	$high  = $nums ? max( $nums ) : 0.0;
	$low   = $nums ? min( $nums ) : 0.0;
	$rows  = array();
	foreach ( array( 'personal', 'business' ) as $plan ) {
		$cost = talkwyn_three_year_cost( $plan );
		if ( null !== $cost ) {
			/* translators: %s: plan name */
			$rows[] = array( sprintf( __( 'Talkwyn %s', 'talkwyn' ), talkwyn_plans()[ $plan ]['name'] ), $cost, $cost, talkwyn_money( $cost ), true );
		}
	}
	if ( ! $rows || $high <= 0 ) {
		return '';
	}
	$rows[] = array( __( 'A typical hosted AI chat plan', 'talkwyn' ), $low, $high, $range, false );
	$max    = max( array_map( static fn( $r ) => $r[2], $rows ) );
	$html   = '<figure class="tw-costchart"><ul class="tw-costchart__rows">';
	foreach ( $rows as $r ) {
		$from  = 100 * $r[1] / $max;
		$to    = 100 * $r[2] / $max;
		$html .= '<li class="tw-costchart__row' . ( $r[4] ? ' is-us' : '' ) . '"><span class="tw-costchart__label">' . esc_html( $r[0] ) . '</span><span class="tw-costchart__track"><span class="tw-costchart__bar" style="--from:' . esc_attr( (string) round( $r[1] === $r[2] ? 0 : $from, 1 ) ) . '%;--to:' . esc_attr( (string) round( max( 2, $to ), 1 ) ) . '%"></span></span><span class="tw-costchart__val">' . esc_html( $r[3] ) . '</span></li>';
	}
	$html .= '</ul><figcaption>' . esc_html__( 'Cost over 3 years. Talkwyn: first year at the regular price, then two renewals with the renewal discount, with unlimited conversations on your own AI key. Hosted tools: entry AI chat plans from public pricing guides, October 2026; check each vendor for current prices.', 'talkwyn' ) . '</figcaption></figure>';
	return $html;
}
add_shortcode( 'tw_cost_chart', 'talkwyn_cost_chart_html' );

/**
 * [tw_cost_explainer] 3-year view: Talkwyn Personal against a typical hosted AI chat plan.
 */
add_shortcode(
	'tw_cost_explainer',
	static function () {
		$three = talkwyn_three_year_cost( 'personal' );
		$range = trim( (string) talkwyn_setting( 'hosted_3yr_range' ) );
		$html  = '<div class="tw-explainer">';
		$html .= '<div class="tw-explainer__col"><p class="tw-eyebrow">' . esc_html__( 'Typical hosted AI chat plan', 'talkwyn' ) . '</p><p class="tw-explainer__big">' . esc_html( $range ) . '</p><p>' . esc_html__( 'over 3 years for an entry AI chat plan, often billed per seat, per conversation or per AI answer.', 'talkwyn' ) . '</p></div>';
		$html .= '<div class="tw-explainer__col tw-explainer__col--us"><p class="tw-eyebrow">' . esc_html__( 'Talkwyn Personal', 'talkwyn' ) . '</p>';
		if ( null !== $three ) {
			$html .= '<p class="tw-explainer__big">' . esc_html( talkwyn_money( $three ) ) . '</p><p>' . esc_html__( 'over 3 years: the first year, then two renewals with the renewal discount. Unlimited conversations on your own AI key.', 'talkwyn' ) . '</p>';
		} else {
			$html .= '<p class="tw-explainer__big">' . esc_html__( 'One yearly license', 'talkwyn' ) . '</p><p>' . esc_html__( 'with unlimited conversations on your own AI key.', 'talkwyn' ) . '</p>';
		}
		$html .= '</div></div><p class="tw-small">' . esc_html__( 'Hosted range from public pricing guides, October 2026. AI usage may have its own cost if you go beyond a provider\'s free tier.', 'talkwyn' ) . '</p>';
		return $html;
	}
);

/**
 * [tw_renewal_discount] Current renewal discount from Talkwyn Hub, e.g. "20%".
 */
add_shortcode(
	'tw_renewal_discount',
	static function () {
		$discount = talkwyn_hub_renewal_discount();
		return null === $discount ? '' : esc_html( rtrim( rtrim( number_format( $discount, 2, '.', '' ), '0' ), '.' ) . '%' );
	}
);

/**
 * [tw_download_box] Free plugin download: WordPress.org once listed, else the direct ZIP.
 */
add_shortcode(
	'tw_download_box',
	static function () {
		$wporg = (string) talkwyn_setting( 'wporg_url' );
		$zip   = talkwyn_free_zip_url();
		$html  = '<div class="tw-download-box is-style-panel"><div><p class="tw-eyebrow">' . esc_html__( 'Free plan forever', 'talkwyn' ) . '</p><p class="tw-download-box__title">' . esc_html__( 'Talkwyn for WordPress', 'talkwyn' ) . '</p><p>' . esc_html__( 'Works with free AI keys. No credit card.', 'talkwyn' ) . '</p></div><div class="tw-download-box__actions">';
		if ( '' !== $wporg ) {
			$html .= '<a class="tw-btn" href="' . esc_url( $wporg ) . '" rel="noopener" data-tw-event="install_click" data-tw-location="download_wporg">' . talkwyn_icon( 'download', 18 ) . esc_html__( 'Get it on WordPress.org', 'talkwyn' ) . '</a>';
		}
		if ( '' !== $zip ) {
			$html .= '<a class="tw-btn' . ( '' !== $wporg ? ' tw-btn--secondary' : '' ) . '" href="' . esc_url( $zip ) . '" data-tw-event="install_click" data-tw-location="download_zip">' . talkwyn_icon( 'download', 18 ) . esc_html__( 'Download the ZIP', 'talkwyn' ) . '</a>';
		}
		if ( '' === $wporg && '' === $zip ) {
			$html .= '<p class="tw-small">' . esc_html__( 'The download link will appear here at launch.', 'talkwyn' ) . ' <a href="/contact/">' . esc_html__( 'Ask us for the ZIP', 'talkwyn' ) . '</a></p>';
		}
		return $html . '</div></div>';
	}
);

/**
 * [tw_compare_tidio] Fair comparison. Tidio's price comes from Site Settings, with the
 * date it was checked on tidio.com/pricing, so it never goes stale silently.
 */
add_shortcode(
	'tw_compare_tidio',
	static function () {
		$note    = (string) talkwyn_setting( 'tidio_price_note' );
		$checked = (string) talkwyn_setting( 'tidio_checked_on' );
		$tidio   = '' !== $note
			? esc_html( $note ) . ( '' !== $checked ? '<br><small>' . esc_html( sprintf( /* translators: %s: date */ __( 'Checked on %s at tidio.com/pricing', 'talkwyn' ), wp_date( (string) get_option( 'date_format' ), (int) strtotime( $checked ) ) ) ) . '</small>' : '' )
			: esc_html__( 'Monthly or yearly subscription. See current plans at', 'talkwyn' ) . ' <a href="https://www.tidio.com/pricing/" rel="nofollow noopener">tidio.com/pricing</a>';
		$rows    = array(
			array( __( 'What it is', 'talkwyn' ), esc_html__( 'A WordPress plugin that runs on your own site', 'talkwyn' ), esc_html__( 'A hosted live chat and AI platform with a WordPress plugin', 'talkwyn' ) ),
			array( __( 'Pricing', 'talkwyn' ), esc_html( sprintf( /* translators: %s: price */ __( 'Free plan, or Pro from %s per year', 'talkwyn' ), talkwyn_value( 'price_personal' ) ) ), $tidio ),
			array( __( 'AI provider', 'talkwyn' ), esc_html__( 'Your choice, including free tiers from Groq, Gemini, OpenRouter, and Cloudflare', 'talkwyn' ), esc_html__( 'Provided by Tidio', 'talkwyn' ) ),
			array( __( 'Where chats are stored', 'talkwyn' ), esc_html__( 'Your WordPress database', 'talkwyn' ), esc_html__( 'Tidio\'s servers', 'talkwyn' ) ),
			array( __( 'Answers from your site', 'talkwyn' ), esc_html__( 'Pages, posts, WooCommerce products, Elementor content, with sources', 'talkwyn' ), esc_html__( 'See Tidio\'s documentation', 'talkwyn' ) ),
			array( __( 'Mixed-language messages', 'talkwyn' ), esc_html__( 'Built for it', 'talkwyn' ), esc_html__( 'See Tidio\'s documentation', 'talkwyn' ) ),
			array( __( 'Live chat with human agents', 'talkwyn' ), esc_html__( 'Lead capture inside the chat, with email alerts', 'talkwyn' ), esc_html__( 'Yes', 'talkwyn' ) ),
		);
		$html    = '<div class="tw-table-wrap" role="region" aria-labelledby="tw-tidio-caption" tabindex="0"><table class="tw-table"><caption id="tw-tidio-caption">' . esc_html__( 'Talkwyn and Tidio side by side', 'talkwyn' ) . '</caption><thead><tr><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Compared', 'talkwyn' ) . '</span></th><th scope="col" class="tw-col-us">Talkwyn</th><th scope="col">Tidio</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr><th scope="row">' . esc_html( $row[0] ) . '</th><td class="tw-col-us">' . $row[1] . '</td><td>' . $row[2] . '</td></tr>';
		}
		return $html . '</tbody></table></div>';
	}
);

/**
 * [tw_changelog] Release notes from Talkwyn Hub's releases table.
 *
 * @param array<string, string>|string $atts product="talkwyn-pro" limit="20".
 */
add_shortcode(
	'tw_changelog',
	static function ( $atts ) {
		$atts = shortcode_atts(
			array(
				'product' => 'talkwyn-pro',
				'limit'   => 20,
			),
			$atts,
			'tw_changelog'
		);
		if ( ! class_exists( '\TWH\Repository\Releases' ) ) {
			return '<p>' . esc_html__( 'Release notes appear here once Talkwyn Hub is active.', 'talkwyn' ) . '</p>';
		}
		$product = \TWH\Repository\Products::find_by_slug( sanitize_title( $atts['product'] ) );
		if ( ! $product ) {
			return '';
		}
		$html  = '<div class="tw-changelog">';
		$count = 0;
		foreach ( \TWH\Repository\Releases::list( (int) $product['id'] ) as $release ) {
			if ( ! (int) $release['is_active'] || 'stable' !== $release['channel'] ) {
				continue;
			}
			$date  = (string) $release['released_at'];
			$html .= '<article class="tw-release"><h2 id="v' . esc_attr( sanitize_title( (string) $release['version'] ) ) . '">' . esc_html( (string) $release['version'] ) . ( 0 === $count ? ' <span class="tw-badge tw-badge--active">' . esc_html__( 'Latest', 'talkwyn' ) . '</span>' : '' ) . '</h2>'
				. '<time datetime="' . esc_attr( gmdate( 'c', (int) strtotime( $date . ' UTC' ) ) ) . '">' . esc_html( wp_date( (string) get_option( 'date_format' ), (int) strtotime( $date . ' UTC' ) ) ) . '</time>'
				. wp_kses_post( \TWH\Domain\Markdown::to_html( (string) $release['changelog'] ) ) . '</article>';
			if ( ++$count >= (int) $atts['limit'] ) {
				break;
			}
		}
		if ( 0 === $count ) {
			$html .= '<p>' . esc_html__( 'The first release notes are on their way.', 'talkwyn' ) . '</p>';
		}
		return $html . '</div>';
	}
);

/**
 * [tw_trial_block] The free trial block on /pricing/#trial: copy from Site Settings or Talkwyn Hub,
 * and the Hub's start form when the Hub runs on this site.
 */
add_shortcode(
	'tw_trial_block',
	static function () {
		$trial = talkwyn_trial();
		$form  = '';
		if ( shortcode_exists( 'twh_trial_form' ) ) {
			$form = do_shortcode( '[twh_trial_form]' );
		} elseif ( '' !== (string) talkwyn_setting( 'hub_url' ) ) {
			$form = '<a class="tw-pill tw-pill--red tw-pill--lg tw-pill--block" href="' . esc_url( trailingslashit( (string) talkwyn_setting( 'hub_url' ) ) . 'pricing/#trial' ) . '" data-tw-event="trial_click" data-tw-location="trial_block">' . esc_html__( 'Start my free trial', 'talkwyn' ) . '</a>';
		} else {
			$form = '<p>' . esc_html__( 'Trials open soon. Tell us your website and we will set yours up by hand.', 'talkwyn' ) . '</p><a class="tw-pill tw-pill--red tw-pill--block" href="' . esc_url( home_url( '/contact/' ) ) . '">' . esc_html__( 'Ask for a trial', 'talkwyn' ) . '</a>';
		}
		return '<section class="tw-trial-block" id="trial" aria-labelledby="tw-trial-title"><div class="tw-trial-block__text">'
			. '<p class="tw-eyebrow"><span class="tw-dot" aria-hidden="true"></span>' . esc_html__( 'Free trial', 'talkwyn' ) . '</p>'
			/* translators: %d: trial days */
			. '<h2 id="tw-trial-title">' . esc_html( sprintf( __( 'Try every Pro feature free for %d days.', 'talkwyn' ), $trial['days'] ) ) . '</h2>'
			. '<p class="tw-lede">' . esc_html( $trial['policy'] ) . ' ' . esc_html__( 'When the trial ends you can upgrade or simply stay on the free plan.', 'talkwyn' ) . '</p>'
			. '<ul class="tw-checklist"><li>' . esc_html__( 'Every Pro feature on one site', 'talkwyn' ) . '</li><li>' . esc_html__( 'Your key arrives by email in a minute', 'talkwyn' ) . '</li><li>' . esc_html__( 'Reminder emails before the trial ends', 'talkwyn' ) . '</li><li>' . esc_html__( 'Upgrade with the same key, nothing to reinstall', 'talkwyn' ) . '</li></ul>'
			. '</div><div class="tw-trial-block__form">' . $form . '</div></section>';
	}
);

/**
 * [tw_partner_terms] Live partner program terms (from Talkwyn Hub when available).
 */
add_shortcode(
	'tw_partner_terms',
	static function () {
		$t     = talkwyn_partner_terms();
		$rate  = talkwyn_value( 'partner_rate' );
		$cards = array(
			/* translators: %s: commission percent */
			array( 'gift', $rate, $t['renewal_rate'] > 0 ? sprintf( __( 'on new paid plans, and %s on renewals', 'talkwyn' ), rtrim( rtrim( number_format( $t['renewal_rate'], 2, '.', '' ), '0' ), '.' ) . '%' ) : __( 'on every paid plan you refer', 'talkwyn' ) ),
			/* translators: %d: days */
			array( 'clock', sprintf( _n( '%d day', '%d days', $t['cookie_days'], 'talkwyn' ), $t['cookie_days'] ), __( 'cookie window, and the last click wins', 'talkwyn' ) ),
			array( 'badge-check', talkwyn_money( $t['threshold'], $t['currency'] ), __( 'minimum payout', 'talkwyn' ) ),
			array( 'send', $t['methods'], __( 'payout methods', 'talkwyn' ) ),
		);
		$html = '<div class="tw-terms-cards">';
		foreach ( $cards as $c ) {
			$html .= '<div class="tw-terms-card"><span class="tw-card__icon">' . talkwyn_icon( $c[0], 22 ) . '</span><p class="tw-terms-card__big">' . esc_html( $c[1] ) . '</p><p>' . esc_html( $c[2] ) . '</p></div>';
		}
		/* translators: %d: days */
		$html .= '</div><p class="tw-small tw-terms-note">' . esc_html( sprintf( __( 'Commissions are approved %d days after the sale, once the refund window has passed. Terms come straight from our partner system, so they are always current.', 'talkwyn' ), $t['approval_days'] ) ) . '</p>';
		return $html;
	}
);
