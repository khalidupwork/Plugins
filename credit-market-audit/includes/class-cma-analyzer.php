<?php
/**
 * On-page SEO and design analyzer.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches the page HTML and runs basic SEO + design checks against it.
 *
 * Every check is an array: id, label, status (pass|warning|fail), value, recommendation, weight.
 */
class CMA_Analyzer {

	/**
	 * Fetched page data.
	 *
	 * @var array
	 */
	private $page = array();

	/**
	 * XPath over the page DOM.
	 *
	 * @var DOMXPath
	 */
	private $xpath;

	/**
	 * Run the analysis.
	 *
	 * @param string $url URL to audit.
	 * @return array|WP_Error { page: array, seo: array[], design: array[] }
	 */
	public static function analyze( $url ) {
		$analyzer = new self();
		$fetched  = $analyzer->fetch( $url );
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}

		return array(
			'page'   => $analyzer->page,
			'seo'    => $analyzer->seo_checks(),
			'design' => $analyzer->design_checks(),
		);
	}

	/**
	 * Score a list of checks 0-100. pass = 1, warning = 0.5, fail = 0, weighted.
	 *
	 * @param array $checks Checks.
	 * @return int|null
	 */
	public static function score( array $checks ) {
		$total = 0;
		$got   = 0;
		foreach ( $checks as $check ) {
			$weight = isset( $check['weight'] ) ? (int) $check['weight'] : 1;
			$total += $weight;
			if ( 'pass' === $check['status'] ) {
				$got += $weight;
			} elseif ( 'warning' === $check['status'] ) {
				$got += $weight / 2;
			}
		}
		return $total > 0 ? (int) round( $got / $total * 100 ) : null;
	}

	/**
	 * Build a check array.
	 *
	 * @param string $id             Machine id.
	 * @param string $label          Human label.
	 * @param string $status         pass|warning|fail.
	 * @param string $value          What we found.
	 * @param string $recommendation How to fix (empty on pass).
	 * @param int    $weight         Importance 1-5.
	 * @return array
	 */
	public static function check( $id, $label, $status, $value, $recommendation = '', $weight = 2 ) {
		return array(
			'id'             => $id,
			'label'          => $label,
			'status'         => $status,
			'value'          => $value,
			'recommendation' => 'pass' === $status ? '' : $recommendation,
			'weight'         => $weight,
		);
	}

	/**
	 * Download the page and prepare the DOM.
	 *
	 * @param string $url URL.
	 * @return true|WP_Error
	 */
	private function fetch( $url ) {
		$start    = microtime( true );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 25,
				'redirection'         => 5,
				'limit_response_size' => 5 * MB_IN_BYTES,
				'user-agent'          => 'Mozilla/5.0 (compatible; CreditMarketAudit/' . CMA_VERSION . '; +' . home_url( '/' ) . ')',
				'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml' ),
			)
		);
		$elapsed  = microtime( true ) - $start;

		if ( is_wp_error( $response ) ) {
			/* translators: %s: error message */
			return new WP_Error( 'cma_fetch_failed', sprintf( __( 'We could not reach this website: %s', 'credit-market-audit' ), $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$html = (string) wp_remote_retrieve_body( $response );
		if ( $code >= 400 || '' === trim( $html ) ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'cma_fetch_failed', sprintf( __( 'The website responded with HTTP status %d. Please check the URL and try again.', 'credit-market-audit' ), $code ) );
		}

		$final_url = $url;
		if ( isset( $response['http_response'] ) && is_object( $response['http_response'] ) && method_exists( $response['http_response'], 'get_response_object' ) ) {
			$raw = $response['http_response']->get_response_object();
			if ( isset( $raw->url ) && $raw->url ) {
				$final_url = $raw->url;
			}
		}

		$headers = wp_remote_retrieve_headers( $response );
		$parts   = wp_parse_url( $final_url );

		$this->page = array(
			'url'         => $url,
			'final_url'   => $final_url,
			'host'        => isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '',
			'scheme'      => isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'http',
			'status'      => $code,
			'load_time'   => round( $elapsed, 2 ),
			'html_size'   => strlen( $html ),
			'compression' => isset( $headers['content-encoding'] ) ? strtolower( (string) $headers['content-encoding'] ) : '',
			'server'      => isset( $headers['server'] ) ? (string) $headers['server'] : '',
		);

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$this->xpath = new DOMXPath( $dom );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * SEO
	 * ------------------------------------------------------------------- */

	/**
	 * Basic on-page SEO checks.
	 *
	 * @return array[]
	 */
	private function seo_checks() {
		$checks = array();
		$page   = $this->page;

		// HTTPS.
		$https    = 'https' === $page['scheme'];
		$checks[] = self::check(
			'https',
			__( 'HTTPS / SSL', 'credit-market-audit' ),
			$https ? 'pass' : 'fail',
			$https ? __( 'Website is served over HTTPS.', 'credit-market-audit' ) : __( 'Website is not served over HTTPS.', 'credit-market-audit' ),
			__( 'Install an SSL certificate and redirect all HTTP traffic to HTTPS. Google uses HTTPS as a ranking signal and browsers mark HTTP sites as "Not secure".', 'credit-market-audit' ),
			5
		);

		// Title.
		$title = $this->text( '//title' );
		$len   = $this->strlen( $title );
		if ( '' === $title ) {
			$status = 'fail';
		} elseif ( $len < 20 || $len > 65 ) {
			$status = 'warning';
		} else {
			$status = 'pass';
		}
		$checks[] = self::check(
			'title',
			__( 'Title tag', 'credit-market-audit' ),
			$status,
			'' === $title ? __( 'No title tag found.', 'credit-market-audit' ) : sprintf( '"%s" (%d %s)', $title, $len, __( 'characters', 'credit-market-audit' ) ),
			__( 'Write a unique, descriptive title of 30–60 characters that includes your main keyword and brand name.', 'credit-market-audit' ),
			5
		);

		// Meta description.
		$desc = $this->meta( 'name', 'description' );
		$len  = $this->strlen( $desc );
		if ( '' === $desc ) {
			$status = 'fail';
		} elseif ( $len < 70 || $len > 160 ) {
			$status = 'warning';
		} else {
			$status = 'pass';
		}
		$checks[] = self::check(
			'meta_description',
			__( 'Meta description', 'credit-market-audit' ),
			$status,
			'' === $desc ? __( 'No meta description found.', 'credit-market-audit' ) : sprintf( '"%s" (%d %s)', $desc, $len, __( 'characters', 'credit-market-audit' ) ),
			__( 'Add a compelling meta description of 120–160 characters. It is shown in search results and affects click-through rate.', 'credit-market-audit' ),
			4
		);

		// H1.
		$h1s   = $this->xpath->query( '//h1' );
		$count = $h1s ? $h1s->length : 0;
		if ( 0 === $count ) {
			$status = 'fail';
			$value  = __( 'No H1 heading found.', 'credit-market-audit' );
		} elseif ( $count > 1 ) {
			$status = 'warning';
			/* translators: %d: number of H1 tags */
			$value = sprintf( __( '%d H1 headings found.', 'credit-market-audit' ), $count );
		} else {
			$status = 'pass';
			$value  = sprintf( '"%s"', $this->truncate( trim( $h1s->item( 0 )->textContent ), 90 ) );
		}
		$checks[] = self::check(
			'h1',
			__( 'H1 heading', 'credit-market-audit' ),
			$status,
			$value,
			__( 'Use exactly one H1 per page that clearly describes the page topic and contains your primary keyword.', 'credit-market-audit' ),
			4
		);

		// Subheadings.
		$h2 = $this->count( '//h2' );
		$h3 = $this->count( '//h3' );
		$checks[] = self::check(
			'headings',
			__( 'Heading structure', 'credit-market-audit' ),
			$h2 > 0 ? 'pass' : 'warning',
			sprintf( 'H2: %d, H3: %d', $h2, $h3 ),
			__( 'Break content into sections with H2/H3 subheadings. This helps both readers and search engines understand the page.', 'credit-market-audit' ),
			2
		);

		// Image alt text.
		$images  = $this->count( '//img' );
		$no_alt  = $this->count( '//img[not(@alt)]' );
		if ( 0 === $images ) {
			$status = 'pass';
			$value  = __( 'No images on the page.', 'credit-market-audit' );
		} else {
			$ratio  = $no_alt / $images;
			$status = 0 === $no_alt ? 'pass' : ( $ratio <= 0.2 ? 'warning' : 'fail' );
			/* translators: 1: images missing alt, 2: total images */
			$value = sprintf( __( '%1$d of %2$d images are missing alt text.', 'credit-market-audit' ), $no_alt, $images );
		}
		$checks[] = self::check(
			'image_alt',
			__( 'Image alt attributes', 'credit-market-audit' ),
			$status,
			$value,
			__( 'Add descriptive alt text to every meaningful image. It improves accessibility and helps images rank in Google Images.', 'credit-market-audit' ),
			3
		);

		// Canonical.
		$canonical = $this->attr( "//link[contains(concat(' ', translate(@rel, 'CANONIL', 'canonil'), ' '), ' canonical ')]", 'href' );
		$checks[]  = self::check(
			'canonical',
			__( 'Canonical tag', 'credit-market-audit' ),
			'' !== $canonical ? 'pass' : 'warning',
			'' !== $canonical ? $canonical : __( 'No canonical tag found.', 'credit-market-audit' ),
			__( 'Add a rel="canonical" link to tell search engines the preferred URL and avoid duplicate-content issues.', 'credit-market-audit' ),
			2
		);

		// Indexability.
		$robots  = strtolower( $this->meta( 'name', 'robots' ) );
		$noindex = false !== strpos( $robots, 'noindex' );
		$checks[] = self::check(
			'indexable',
			__( 'Search engine indexing', 'credit-market-audit' ),
			$noindex ? 'fail' : 'pass',
			$noindex ? __( 'Page is blocked from indexing (meta robots "noindex").', 'credit-market-audit' ) : __( 'Page can be indexed by search engines.', 'credit-market-audit' ),
			__( 'Remove the "noindex" robots directive so Google can show this page in search results.', 'credit-market-audit' ),
			5
		);

		// Language.
		$lang     = $this->attr( '//html', 'lang' );
		$checks[] = self::check(
			'lang',
			__( 'Language attribute', 'credit-market-audit' ),
			'' !== $lang ? 'pass' : 'warning',
			'' !== $lang ? $lang : __( 'The <html> tag has no lang attribute.', 'credit-market-audit' ),
			__( 'Add a lang attribute (e.g. lang="en") to the <html> tag so search engines and screen readers know the page language.', 'credit-market-audit' ),
			1
		);

		// Social tags.
		$og      = array_filter(
			array(
				'og:title'       => $this->meta( 'property', 'og:title' ),
				'og:description' => $this->meta( 'property', 'og:description' ),
				'og:image'       => $this->meta( 'property', 'og:image' ),
			)
		);
		$twitter = $this->meta( 'name', 'twitter:card' );
		$found   = array_keys( $og );
		if ( $twitter ) {
			$found[] = 'twitter:card';
		}
		$checks[] = self::check(
			'social',
			__( 'Social sharing tags (Open Graph)', 'credit-market-audit' ),
			3 === count( $og ) ? 'pass' : ( $og ? 'warning' : 'fail' ),
			$found ? implode( ', ', $found ) : __( 'No Open Graph or Twitter tags found.', 'credit-market-audit' ),
			__( 'Add og:title, og:description and og:image tags so links to your site look attractive when shared on Facebook, LinkedIn, WhatsApp and X.', 'credit-market-audit' ),
			2
		);

		// Structured data.
		$schema   = $this->count( "//script[translate(@type, 'APLICTONJS+', 'aplictonjs+')='application/ld+json']" ) + $this->count( '//*[@itemscope]' );
		$checks[] = self::check(
			'schema',
			__( 'Structured data (Schema.org)', 'credit-market-audit' ),
			$schema > 0 ? 'pass' : 'warning',
			$schema > 0 ? __( 'Structured data detected.', 'credit-market-audit' ) : __( 'No structured data found.', 'credit-market-audit' ),
			__( 'Add Schema.org markup (Organization, LocalBusiness, Product, FAQ…) to become eligible for rich results in Google.', 'credit-market-audit' ),
			2
		);

		// Content length.
		$words = $this->word_count();
		$checks[] = self::check(
			'content',
			__( 'Content length', 'credit-market-audit' ),
			$words >= 300 ? 'pass' : ( $words >= 150 ? 'warning' : 'fail' ),
			/* translators: %d: word count */
			sprintf( __( '%d words on the page.', 'credit-market-audit' ), $words ),
			__( 'Pages with thin content rarely rank. Aim for at least 300 words of useful, original text on important pages.', 'credit-market-audit' ),
			3
		);

		// Links.
		list( $internal, $external ) = $this->link_counts();
		$checks[] = self::check(
			'links',
			__( 'Internal links', 'credit-market-audit' ),
			$internal >= 5 ? 'pass' : 'warning',
			/* translators: 1: internal links, 2: external links */
			sprintf( __( '%1$d internal and %2$d external links.', 'credit-market-audit' ), $internal, $external ),
			__( 'Link to your other important pages from this page to help visitors and search engines discover your content.', 'credit-market-audit' ),
			1
		);

		// robots.txt / sitemap.
		list( $robots_ok, $sitemap ) = $this->robots_and_sitemap();
		$checks[] = self::check(
			'robots_txt',
			__( 'robots.txt', 'credit-market-audit' ),
			$robots_ok ? 'pass' : 'warning',
			$robots_ok ? __( 'robots.txt found.', 'credit-market-audit' ) : __( 'robots.txt not found.', 'credit-market-audit' ),
			__( 'Add a robots.txt file at the root of your domain and reference your XML sitemap in it.', 'credit-market-audit' ),
			2
		);
		$checks[] = self::check(
			'sitemap',
			__( 'XML sitemap', 'credit-market-audit' ),
			$sitemap ? 'pass' : 'fail',
			$sitemap ? $sitemap : __( 'No XML sitemap found.', 'credit-market-audit' ),
			__( 'Create an XML sitemap (most SEO plugins do this automatically) and submit it in Google Search Console.', 'credit-market-audit' ),
			3
		);

		// Server response time.
		$time     = $page['load_time'];
		$checks[] = self::check(
			'response_time',
			__( 'Server response time', 'credit-market-audit' ),
			$time <= 1.0 ? 'pass' : ( $time <= 3.0 ? 'warning' : 'fail' ),
			/* translators: %s: seconds */
			sprintf( __( 'HTML downloaded in %s seconds.', 'credit-market-audit' ), number_format_i18n( $time, 2 ) ),
			__( 'Improve server response time with page caching, a faster host or a CDN. Aim for under 1 second.', 'credit-market-audit' ),
			3
		);

		// Compression.
		$compressed = (bool) preg_match( '/\b(gzip|br|deflate|zstd)\b/', $page['compression'] );
		$checks[]   = self::check(
			'compression',
			__( 'Text compression', 'credit-market-audit' ),
			$compressed ? 'pass' : 'warning',
			$compressed ? strtoupper( $page['compression'] ) : __( 'No GZIP/Brotli compression detected.', 'credit-market-audit' ),
			__( 'Enable GZIP or Brotli compression on your server to reduce page size by up to 70%.', 'credit-market-audit' ),
			2
		);

		// Mixed content.
		if ( $https ) {
			$mixed    = $this->count( "//img[starts-with(@src, 'http://')] | //script[starts-with(@src, 'http://')] | //link[starts-with(@href, 'http://') and contains(translate(@rel, 'STYLEHE', 'stylehe'), 'stylesheet')] | //iframe[starts-with(@src, 'http://')]" );
			$checks[] = self::check(
				'mixed_content',
				__( 'Mixed content', 'credit-market-audit' ),
				0 === $mixed ? 'pass' : 'fail',
				/* translators: %d: number of insecure resources */
				0 === $mixed ? __( 'All resources load over HTTPS.', 'credit-market-audit' ) : sprintf( __( '%d resources load over insecure HTTP.', 'credit-market-audit' ), $mixed ),
				__( 'Load every image, script and stylesheet over HTTPS so browsers do not block them or show security warnings.', 'credit-market-audit' ),
				2
			);
		}

		return $checks;
	}

	/* ---------------------------------------------------------------------
	 * Design
	 * ------------------------------------------------------------------- */

	/**
	 * Design / UX checks that can be detected from markup.
	 * PageSpeed-based design checks are appended later by CMA_Audit.
	 *
	 * @return array[]
	 */
	private function design_checks() {
		$checks = array();

		// Responsive viewport.
		$viewport   = $this->meta( 'name', 'viewport' );
		$responsive = '' !== $viewport && false !== stripos( $viewport, 'width=device-width' );
		$checks[]   = self::check(
			'viewport',
			__( 'Mobile responsive (viewport)', 'credit-market-audit' ),
			$responsive ? 'pass' : 'fail',
			'' !== $viewport ? $viewport : __( 'No viewport meta tag found.', 'credit-market-audit' ),
			__( 'Add <meta name="viewport" content="width=device-width, initial-scale=1"> and use a responsive layout. Over 60% of visitors browse on mobile.', 'credit-market-audit' ),
			5
		);

		// Favicon.
		$favicon = $this->attr( "//link[contains(translate(@rel, 'ICON', 'icon'), 'icon')]", 'href' );
		if ( '' === $favicon && $this->url_exists( $this->root_url() . '/favicon.ico' ) ) {
			$favicon = '/favicon.ico';
		}
		$checks[] = self::check(
			'favicon',
			__( 'Favicon', 'credit-market-audit' ),
			'' !== $favicon ? 'pass' : 'warning',
			'' !== $favicon ? __( 'Favicon found.', 'credit-market-audit' ) : __( 'No favicon found.', 'credit-market-audit' ),
			__( 'Add a favicon so your brand is recognisable in browser tabs, bookmarks and Google mobile results.', 'credit-market-audit' ),
			2
		);

		// Touch icon / theme colour (brand polish on mobile).
		$touch    = $this->attr( "//link[contains(translate(@rel, 'APLETOUCHIN', 'apletouchin'), 'apple-touch-icon')]", 'href' );
		$theme    = $this->meta( 'name', 'theme-color' );
		$checks[] = self::check(
			'mobile_branding',
			__( 'Mobile branding (touch icon & theme colour)', 'credit-market-audit' ),
			( $touch && $theme ) ? 'pass' : ( ( $touch || $theme ) ? 'warning' : 'fail' ),
			sprintf(
				'%s: %s · %s: %s',
				__( 'Touch icon', 'credit-market-audit' ),
				$touch ? __( 'yes', 'credit-market-audit' ) : __( 'no', 'credit-market-audit' ),
				__( 'Theme colour', 'credit-market-audit' ),
				$theme ? $theme : __( 'no', 'credit-market-audit' )
			),
			__( 'Add an apple-touch-icon and a theme-color meta tag so your site looks polished when saved to a phone home screen and in mobile browsers.', 'credit-market-audit' ),
			1
		);

		// Web fonts.
		$fonts    = $this->font_families();
		$count    = count( $fonts );
		$checks[] = self::check(
			'fonts',
			__( 'Typography (web fonts)', 'credit-market-audit' ),
			$count <= 3 ? 'pass' : ( $count <= 5 ? 'warning' : 'fail' ),
			$count ? implode( ', ', array_slice( $fonts, 0, 8 ) ) : __( 'Using system / theme fonts only.', 'credit-market-audit' ),
			__( 'Limit your design to 2–3 font families. Too many fonts look inconsistent and slow the page down.', 'credit-market-audit' ),
			2
		);

		// Modern image formats.
		$images = $this->count( '//img' );
		if ( $images > 0 ) {
			$modern   = $this->count( "//img[contains(@src, '.webp') or contains(@src, '.avif') or contains(@src, '.svg') or contains(@srcset, '.webp') or contains(@srcset, '.avif') or contains(@data-src, '.webp')] | //picture/source[contains(@type, 'webp') or contains(@type, 'avif')]" );
			$ratio    = min( 1, $modern / $images );
			$checks[] = self::check(
				'image_formats',
				__( 'Modern image formats', 'credit-market-audit' ),
				$ratio >= 0.5 ? 'pass' : ( $ratio > 0 ? 'warning' : 'fail' ),
				/* translators: %d: percentage */
				sprintf( __( '%d%% of images use WebP/AVIF/SVG.', 'credit-market-audit' ), (int) round( $ratio * 100 ) ),
				__( 'Serve images as WebP or AVIF. They look identical but are 25–50% smaller, so pages load faster.', 'credit-market-audit' ),
				2
			);

			$sized    = $this->count( '//img[@width and @height]' );
			$ratio    = $sized / $images;
			$checks[] = self::check(
				'image_dimensions',
				__( 'Image dimensions set', 'credit-market-audit' ),
				$ratio >= 0.8 ? 'pass' : ( $ratio >= 0.4 ? 'warning' : 'fail' ),
				/* translators: 1: sized images, 2: total images */
				sprintf( __( '%1$d of %2$d images have width and height attributes.', 'credit-market-audit' ), $sized, $images ),
				__( 'Set width and height on images so the layout does not jump while the page loads.', 'credit-market-audit' ),
				2
			);
		}

		// Outdated HTML.
		$deprecated = $this->count( '//font | //center | //marquee | //blink | //frameset | //frame' );
		$checks[]   = self::check(
			'deprecated_html',
			__( 'Modern HTML', 'credit-market-audit' ),
			0 === $deprecated ? 'pass' : 'fail',
			/* translators: %d: count */
			0 === $deprecated ? __( 'No outdated HTML tags found.', 'credit-market-audit' ) : sprintf( __( '%d outdated tags (font, center, marquee…) found.', 'credit-market-audit' ), $deprecated ),
			__( 'Your site uses outdated HTML tags — a sign of an old design. A modern redesign will look better and work across all devices.', 'credit-market-audit' ),
			2
		);

		// Inline styles.
		$inline   = $this->count( '//body//*[@style]' );
		$checks[] = self::check(
			'inline_styles',
			__( 'Design consistency (inline styles)', 'credit-market-audit' ),
			$inline <= 30 ? 'pass' : ( $inline <= 100 ? 'warning' : 'fail' ),
			/* translators: %d: count */
			sprintf( __( '%d elements with inline styles.', 'credit-market-audit' ), $inline ),
			__( 'Move inline styles into a shared stylesheet / design system so spacing, colours and fonts stay consistent across pages.', 'credit-market-audit' ),
			1
		);

		// Request count (CSS / JS).
		$css      = $this->count( "//link[contains(translate(@rel, 'STYLEHE', 'stylehe'), 'stylesheet')]" );
		$js       = $this->count( '//script[@src]' );
		$total    = $css + $js;
		$checks[] = self::check(
			'assets',
			__( 'CSS & JavaScript files', 'credit-market-audit' ),
			$total <= 25 ? 'pass' : ( $total <= 50 ? 'warning' : 'fail' ),
			/* translators: 1: stylesheets, 2: scripts */
			sprintf( __( '%1$d stylesheets and %2$d scripts.', 'credit-market-audit' ), $css, $js ),
			__( 'Too many CSS/JS files usually come from heavy themes and plugins. Combine, defer or remove unused assets.', 'credit-market-audit' ),
			1
		);

		// Call to action.
		$cta      = $this->count( "//a[contains(translate(@href, 'TELMAIO', 'telmaio'), 'tel:') or contains(translate(@href, 'TELMAIO', 'telmaio'), 'mailto:')] | //form | //button" );
		$checks[] = self::check(
			'conversion',
			__( 'Contact options / call to action', 'credit-market-audit' ),
			$cta > 0 ? 'pass' : 'warning',
			$cta > 0 ? __( 'Forms, buttons or click-to-call links found.', 'credit-market-audit' ) : __( 'No forms, buttons or click-to-call links found.', 'credit-market-audit' ),
			__( 'Make it easy to contact you: add a clear call-to-action button, a contact form and a click-to-call phone link.', 'credit-market-audit' ),
			2
		);

		return $checks;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Scheme + host of the audited page.
	 *
	 * @return string
	 */
	private function root_url() {
		return $this->page['scheme'] . '://' . $this->page['host'];
	}

	/**
	 * Count nodes matching an XPath.
	 *
	 * @param string $query XPath.
	 * @return int
	 */
	private function count( $query ) {
		$nodes = $this->xpath->query( $query );
		return $nodes ? $nodes->length : 0;
	}

	/**
	 * Text of the first node.
	 *
	 * @param string $query XPath.
	 * @return string
	 */
	private function text( $query ) {
		$nodes = $this->xpath->query( $query );
		return ( $nodes && $nodes->length ) ? trim( preg_replace( '/\s+/u', ' ', $nodes->item( 0 )->textContent ) ) : '';
	}

	/**
	 * Attribute of the first node.
	 *
	 * @param string $query XPath.
	 * @param string $attr  Attribute.
	 * @return string
	 */
	private function attr( $query, $attr ) {
		$nodes = $this->xpath->query( $query );
		if ( ! $nodes ) {
			return '';
		}
		foreach ( $nodes as $node ) {
			if ( $node instanceof DOMElement && $node->hasAttribute( $attr ) ) {
				return trim( $node->getAttribute( $attr ) );
			}
		}
		return '';
	}

	/**
	 * Content of a <meta> tag matched case-insensitively.
	 *
	 * @param string $attr  name|property.
	 * @param string $value Expected value.
	 * @return string
	 */
	private function meta( $attr, $value ) {
		$upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$lower = 'abcdefghijklmnopqrstuvwxyz';
		$query = sprintf( "//meta[translate(@%s, '%s', '%s')='%s']", $attr, $upper, $lower, strtolower( $value ) );
		return trim( preg_replace( '/\s+/u', ' ', $this->attr( $query, 'content' ) ) );
	}

	/**
	 * Multibyte-safe length.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private function strlen( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/**
	 * Truncate text for display.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max chars.
	 * @return string
	 */
	private function truncate( $text, $max ) {
		$text = preg_replace( '/\s+/u', ' ', $text );
		if ( $this->strlen( $text ) <= $max ) {
			return $text;
		}
		return ( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max - 1, 'UTF-8' ) : substr( $text, 0, $max - 1 ) ) . '…';
	}

	/**
	 * Visible word count of the body.
	 *
	 * @return int
	 */
	private function word_count() {
		$body = $this->xpath->query( '//body' );
		if ( ! $body || ! $body->length ) {
			return 0;
		}
		$clone = $body->item( 0 )->cloneNode( true );
		$doc   = new DOMDocument();
		$doc->appendChild( $doc->importNode( $clone, true ) );
		$xp = new DOMXPath( $doc );
		foreach ( iterator_to_array( $xp->query( '//script | //style | //noscript | //svg | //template' ) ) as $node ) {
			$node->parentNode->removeChild( $node );
		}
		$text = trim( preg_replace( '/\s+/u', ' ', $doc->textContent ) );
		if ( '' === $text ) {
			return 0;
		}
		return count( preg_split( '/\s+/u', $text ) );
	}

	/**
	 * Internal / external link counts.
	 *
	 * @return int[]
	 */
	private function link_counts() {
		$internal = 0;
		$external = 0;
		$host     = preg_replace( '/^www\./', '', $this->page['host'] );
		$nodes    = $this->xpath->query( '//a[@href]' );
		foreach ( $nodes ? $nodes : array() as $node ) {
			$href = trim( $node->getAttribute( 'href' ) );
			if ( '' === $href || '#' === $href[0] || preg_match( '/^(mailto|tel|javascript|sms|whatsapp):/i', $href ) ) {
				continue;
			}
			$link_host = wp_parse_url( $href, PHP_URL_HOST );
			if ( ! $link_host || preg_replace( '/^www\./', '', strtolower( $link_host ) ) === $host ) {
				++$internal;
			} else {
				++$external;
			}
		}
		return array( $internal, $external );
	}

	/**
	 * Font families loaded via Google Fonts / Bunny / @font-face.
	 *
	 * @return string[]
	 */
	private function font_families() {
		$families = array();

		$links = $this->xpath->query( "//link[contains(@href, 'fonts.googleapis.com') or contains(@href, 'fonts.bunny.net')]" );
		foreach ( $links ? $links : array() as $link ) {
			$query = (string) wp_parse_url( html_entity_decode( $link->getAttribute( 'href' ) ), PHP_URL_QUERY );
			// css2 API repeats `family=`; css API uses `family=A|B`.
			preg_match_all( '/(?:^|&)family=([^&]+)/', $query, $m );
			foreach ( $m[1] as $raw ) {
				foreach ( explode( '|', urldecode( $raw ) ) as $family ) {
					$name = trim( preg_replace( '/[:@].*$/', '', str_replace( '+', ' ', $family ) ) );
					if ( '' !== $name ) {
						$families[ strtolower( $name ) ] = $name;
					}
				}
			}
		}

		$styles = $this->xpath->query( '//style' );
		foreach ( $styles ? $styles : array() as $style ) {
			if ( preg_match_all( '/@font-face\s*{[^}]*font-family\s*:\s*[\'"]?([^;\'"}]+)/i', $style->textContent, $m ) ) {
				foreach ( $m[1] as $name ) {
					$name                            = trim( $name );
					$families[ strtolower( $name ) ] = $name;
				}
			}
		}

		if ( $this->count( "//link[contains(@href, 'use.typekit.net')] | //script[contains(@src, 'use.typekit.net')]" ) ) {
			$families['adobe fonts'] = 'Adobe Fonts';
		}

		// Icon fonts are not typography.
		foreach ( array_keys( $families ) as $key ) {
			if ( preg_match( '/awesome|icon|dashicons|eicons|material symbols|glyph/i', $key ) ) {
				unset( $families[ $key ] );
			}
		}

		return array_values( $families );
	}

	/**
	 * Look for robots.txt and a sitemap.
	 *
	 * @return array{0: bool, 1: string} robots found, sitemap URL.
	 */
	private function robots_and_sitemap() {
		$root      = $this->root_url();
		$robots_ok = false;
		$sitemap   = '';

		$response = wp_safe_remote_get(
			$root . '/robots.txt',
			array(
				'timeout'             => 10,
				'limit_response_size' => 256 * KB_IN_BYTES,
			)
		);
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = (string) wp_remote_retrieve_body( $response );
			if ( preg_match( '/user-agent\s*:|disallow\s*:|sitemap\s*:/i', $body ) ) {
				$robots_ok = true;
				if ( preg_match( '/^\s*sitemap\s*:\s*(\S+)/im', $body, $m ) ) {
					$sitemap = esc_url_raw( $m[1] );
				}
			}
		}

		if ( '' === $sitemap ) {
			foreach ( array( '/sitemap.xml', '/sitemap_index.xml', '/wp-sitemap.xml' ) as $path ) {
				if ( $this->url_exists( $root . $path, true ) ) {
					$sitemap = $root . $path;
					break;
				}
			}
		}

		return array( $robots_ok, $sitemap );
	}

	/**
	 * Does a URL return 200?
	 *
	 * @param string $url      URL.
	 * @param bool   $want_xml Require XML-looking body.
	 * @return bool
	 */
	private function url_exists( $url, $want_xml = false ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 8,
				'limit_response_size' => 64 * KB_IN_BYTES,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		if ( $want_xml ) {
			$body = ltrim( (string) wp_remote_retrieve_body( $response ) );
			return 0 === strpos( $body, '<?xml' ) || false !== stripos( substr( $body, 0, 500 ), '<urlset' ) || false !== stripos( substr( $body, 0, 500 ), '<sitemapindex' );
		}
		return true;
	}
}
