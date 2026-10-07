<?php
/**
 * Homepage components: hero chat, live demo, founding band, pricing teaser, FAQ schema.
 *
 * The demo clinic ("Brightside Dental") is a clearly labeled fictional sample.
 * Its prices and hours are sample data from its sample website, not claims about
 * Talkwyn customers.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chat window frame.
 *
 * @param string $body  Messages HTML.
 * @param string $extra Extra HTML after the body (chips, form).
 * @param string $title Window title.
 * @param string $class Extra classes.
 */
function talkwyn_chat_frame( string $body, string $extra = '', string $title = '', string $class = '' ): string {
	$title = '' !== $title ? $title : __( 'Brightside Dental (sample clinic)', 'talkwyn' );
	return '<div class="tw-chat ' . esc_attr( $class ) . '">'
		. '<div class="tw-chat__head"><span class="tw-chat__avatar">' . talkwyn_logo_svg( 'mark', '' ) . '</span><div><p class="tw-chat__title">' . esc_html( $title ) . '</p><p class="tw-chat__status">' . esc_html__( 'Answers in your language', 'talkwyn' ) . '</p></div></div>'
		. $body . $extra . '</div>';
}

/**
 * Message bubble.
 *
 * @param string   $who     bot|user.
 * @param string   $html    Inner HTML (already safe).
 * @param string   $lang    Language code.
 * @param string[] $sources Source labels.
 */
function talkwyn_msg( string $who, string $html, string $lang = 'en', array $sources = array() ): string {
	$dir = in_array( $lang, array( 'ar', 'ur' ), true ) ? 'rtl' : 'ltr';
	$out = '<div class="tw-msg tw-msg--' . esc_attr( $who ) . '" lang="' . esc_attr( $lang ) . '" dir="' . $dir . '">' . $html;
	if ( $sources ) {
		$out .= '<div class="tw-msg__sources"><span class="tw-msg__sources-label">' . esc_html__( 'Sources', 'talkwyn' ) . '</span>';
		foreach ( $sources as $source ) {
			$out .= '<span class="tw-chip tw-chip--source">' . esc_html( $source ) . '</span>';
		}
		$out .= '</div>';
	}
	return $out . '</div>';
}

/**
 * [tw_hero_chat] Static sample conversation shown in the hero.
 */
add_shortcode(
	'tw_hero_chat',
	static function () {
		$body  = '<div class="tw-chat__body tw-chat__body--short" aria-label="' . esc_attr__( 'Sample conversation', 'talkwyn' ) . '">'
			. talkwyn_msg( 'user', '<p>How much is teeth whitening?</p>' )
			. talkwyn_msg( 'bot', '<p>In-office whitening is $249 and takes about an hour. A take-home kit is $149. Would you like to book a free consultation first?</p>', 'en', array( 'Whitening', 'Prices' ) )
			. talkwyn_msg( 'user', '<p>هل تفتحون يوم السبت؟</p>', 'ar' )
			. talkwyn_msg( 'bot', '<p>نعم، نفتح يوم السبت من التاسعة صباحًا حتى الثانية ظهرًا. هل تريد أن نحجز لك موعدًا؟</p>', 'ar', array( 'Opening hours' ) )
			. '<span class="tw-chip tw-chip--saved">✓ ' . esc_html__( 'Lead saved', 'talkwyn' ) . '</span>'
			. '</div>';
		$extra = '<p class="tw-chat__note"><a href="#live-demo">' . esc_html__( 'Ask your own question below', 'talkwyn' ) . '</a></p>';
		return talkwyn_chat_frame( $body, $extra );
	}
);

/**
 * Scripted demo data (sample clinic).
 *
 * @return array<string, mixed>
 */
function talkwyn_demo_data(): array {
	$book_lead = array(
		'en' => array(
			'text'  => 'Would you like the clinic to call you to confirm a time?',
			'yes'   => 'Yes, call me',
			'no'    => 'Not now',
			'saved' => 'Lead saved. The team will call you.',
		),
		'ar' => array(
			'text'  => 'هل تريد أن تتصل بك العيادة لتأكيد الموعد؟',
			'yes'   => 'نعم، اتصلوا بي',
			'no'    => 'ليس الآن',
			'saved' => 'تم حفظ طلبك',
			'dir'   => 'rtl',
		),
		'es' => array(
			'text'  => '¿Quieres que la clínica te llame para confirmar la hora?',
			'yes'   => 'Sí, llámenme',
			'no'    => 'Ahora no',
			'saved' => 'Contacto guardado',
		),
	);
	$a         = static function ( string $html, string $lang, array $sources, ?array $lead = null ) {
		$out = array(
			'html'    => $html,
			'lang'    => $lang,
			'dir'     => in_array( $lang, array( 'ar', 'ur' ), true ) ? 'rtl' : 'ltr',
			'sources' => $sources,
		);
		if ( $lead ) {
			$out['lead'] = $lead;
		}
		return $out;
	};
	return array(
		'sourcesLabel' => __( 'Sources', 'talkwyn' ),
		'typingLabel'  => __( 'Talkwyn is typing', 'talkwyn' ),
		'intents'      => array(
			array(
				'keywords' => array( 'whiten', 'bleach', 'blanque', 'تبييض', 'safaid', 'white', 'सफ़ेद', 'सफेद' ),
				'answers'  => array(
					'en'      => $a( '<p>In-office whitening is <strong>$249</strong> and takes about an hour. A take-home kit with custom trays is <strong>$149</strong>.</p><p>Would you like to book a free consultation first?</p>', 'en', array( 'Whitening', 'Prices' ) ),
					'ar'      => $a( '<p>سعر تبييض الأسنان في العيادة <strong>249 دولارًا</strong> ويستغرق حوالي ساعة. أما الطقم المنزلي فسعره <strong>149 دولارًا</strong>.</p>', 'ar', array( 'Whitening', 'Prices' ) ),
					'es'      => $a( '<p>El blanqueamiento en la clínica cuesta <strong>249 dólares</strong> y dura alrededor de una hora. El kit para casa cuesta <strong>149 dólares</strong>.</p>', 'es', array( 'Whitening', 'Prices' ) ),
					'ur-Latn' => $a( '<p>Clinic mein whitening <strong>$249</strong> ki hai aur takreeban ek ghanta lagta hai. Ghar ke liye kit <strong>$149</strong> ki hai.</p>', 'en', array( 'Whitening', 'Prices' ) ),
					'hi'      => $a( '<p>क्लिनिक में दांत सफ़ेद करने की कीमत <strong>$249</strong> है और इसमें लगभग एक घंटा लगता है। घर पर इस्तेमाल की किट <strong>$149</strong> की है।</p>', 'hi', array( 'Whitening', 'Prices' ) ),
				),
			),
			array(
				'keywords' => array( 'saturday', 'sunday', 'hour', 'open', 'close', 'time', 'السبت', 'تفتح', 'مواعيد', 'sábado', 'horario', 'abren', 'khul', 'band', 'شنبه', 'ہفتہ', 'शनिवार', 'खुल' ),
				'answers'  => array(
					'en'      => $a( '<p>Yes, we are open on Saturday from 9 am to 2 pm. On weekdays we are open 9 am to 6 pm, and we are closed on Sunday.</p>', 'en', array( 'Opening hours' ) ),
					'ar'      => $a( '<p>نعم، نفتح يوم السبت من التاسعة صباحًا حتى الثانية ظهرًا. في أيام الأسبوع نفتح من التاسعة صباحًا حتى السادسة مساءً، ونغلق يوم الأحد.</p>', 'ar', array( 'Opening hours' ) ),
					'es'      => $a( '<p>Sí, abrimos el sábado de 9 a 14 h. Entre semana abrimos de 9 a 18 h y cerramos los domingos.</p>', 'es', array( 'Opening hours' ) ),
					'ur-Latn' => $a( '<p>Ji haan, hum Saturday ko subah 9 se dopahar 2 baje tak khule hain. Weekdays mein 9 se 6 baje tak, aur Sunday ko band hain.</p>', 'en', array( 'Opening hours' ) ),
					'ur'      => $a( '<p>جی ہاں، ہم ہفتے کو صبح نو بجے سے دوپہر دو بجے تک کھلے ہیں۔ اتوار کو کلینک بند رہتا ہے۔</p>', 'ur', array( 'Opening hours' ) ),
					'hi'      => $a( '<p>जी हाँ, हम शनिवार को सुबह 9 बजे से दोपहर 2 बजे तक खुले हैं। रविवार को क्लिनिक बंद रहता है।</p>', 'hi', array( 'Opening hours' ) ),
				),
			),
			array(
				'keywords' => array( 'insur', 'ppo', 'cover', 'تأمين', 'seguro', 'aseguradora', 'bima', 'बीमा' ),
				'answers'  => array(
					'en' => $a( '<p>We accept most PPO dental plans and file the claim for you. Bring your insurance card to your first visit. If you tell me your plan, the front desk can confirm your coverage before you book.</p>', 'en', array( 'Insurance and payment' ) ),
					'ar' => $a( '<p>نقبل معظم خطط التأمين على الأسنان من نوع PPO ونقدّم المطالبة نيابةً عنك. أحضر بطاقة التأمين في زيارتك الأولى.</p>', 'ar', array( 'Insurance and payment' ) ),
					'es' => $a( '<p>Aceptamos la mayoría de los planes dentales PPO y presentamos la reclamación por ti. Trae tu tarjeta del seguro a la primera visita.</p>', 'es', array( 'Insurance and payment' ) ),
					'hi' => $a( '<p>हम ज़्यादातर PPO डेंटल प्लान स्वीकार करते हैं और क्लेम आपके लिए फ़ाइल करते हैं। पहली विज़िट पर अपना बीमा कार्ड साथ लाएँ।</p>', 'hi', array( 'Insurance and payment' ) ),
				),
			),
			array(
				'keywords' => array( 'book', 'appointment', 'tomorrow', 'schedule', 'slot', 'call me', 'موعد', 'حجز', 'غدا', 'cita', 'mañana', 'reservar', 'appointment', 'kal', 'book karna', 'अपॉइंटमेंट', 'कल' ),
				'answers'  => array(
					'en'      => $a( '<p>Tomorrow we have openings in the morning and late afternoon. I can’t book directly from here, but I can pass your request to the front desk.</p>', 'en', array( 'Book a visit' ), $book_lead['en'] ),
					'ar'      => $a( '<p>لدينا مواعيد متاحة غدًا في الصباح وبعد العصر. لا أستطيع الحجز من هنا مباشرةً، لكن يمكنني إرسال طلبك إلى الاستقبال.</p>', 'ar', array( 'Book a visit' ), $book_lead['ar'] ),
					'es'      => $a( '<p>Mañana hay horas libres por la mañana y a última hora de la tarde. No puedo reservar desde aquí, pero puedo pasar tu solicitud a recepción.</p>', 'es', array( 'Book a visit' ), $book_lead['es'] ),
					'ur-Latn' => $a( '<p>Kal subah aur shaam ko slots available hain. Main yahan se seedha book nahi kar sakta, lekin aap ki request front desk tak pohncha sakta hoon.</p>', 'en', array( 'Book a visit' ), $book_lead['en'] ),
				),
			),
			array(
				'keywords' => array( 'where', 'address', 'parking', 'located', 'location', 'أين', 'العنوان', 'dónde', 'dirección', 'kahan', 'कहाँ', 'पता' ),
				'answers'  => array(
					'en' => $a( '<p>Brightside Dental is at 120 Harbor Street, second floor, with free parking behind the building. It is a two-minute walk from the Central bus stop.</p>', 'en', array( 'Contact and directions' ) ),
					'ar' => $a( '<p>تقع عيادة برايتسايد في 120 شارع هاربر، الطابق الثاني، مع موقف مجاني خلف المبنى.</p>', 'ar', array( 'Contact and directions' ) ),
					'es' => $a( '<p>Brightside Dental está en 120 Harbor Street, segundo piso, con aparcamiento gratuito detrás del edificio.</p>', 'es', array( 'Contact and directions' ) ),
				),
			),
			array(
				'keywords' => array( 'hello', 'hi ', 'hey', 'salam', 'مرحبا', 'السلام', 'hola', 'नमस्ते', 'assalam' ),
				'answers'  => array(
					'en'      => $a( '<p>Hi! I can help with prices, opening hours, insurance, and booking at Brightside Dental. What would you like to know?</p>', 'en', array() ),
					'ar'      => $a( '<p>أهلًا وسهلًا! يمكنني مساعدتك في الأسعار ومواعيد العمل والتأمين والحجز. بماذا أستطيع مساعدتك؟</p>', 'ar', array() ),
					'es'      => $a( '<p>¡Hola! Puedo ayudarte con precios, horarios, seguros y citas en Brightside Dental. ¿Qué te gustaría saber?</p>', 'es', array() ),
					'ur-Latn' => $a( '<p>Wa alaikum assalam! Main prices, timings, insurance aur booking mein madad kar sakta hoon. Aap kya poochna chahte hain?</p>', 'en', array() ),
					'hi'      => $a( '<p>नमस्ते! मैं कीमतों, समय, बीमा और अपॉइंटमेंट में मदद कर सकता हूँ। आप क्या जानना चाहेंगे?</p>', 'hi', array() ),
				),
			),
		),
		'unknown'      => array(
			'en'      => $a( '<p>I couldn’t find that on Brightside Dental’s website, so I won’t guess. I can ask the team to get back to you with the right answer.</p>', 'en', array(), $book_lead['en'] ),
			'ar'      => $a( '<p>لم أجد هذه المعلومة على موقع العيادة، لذلك لن أخمّن. يمكنني أن أطلب من الفريق التواصل معك بالإجابة الصحيحة.</p>', 'ar', array(), $book_lead['ar'] ),
			'es'      => $a( '<p>No encontré eso en la web de la clínica, así que no voy a adivinar. Puedo pedir al equipo que te responda.</p>', 'es', array(), $book_lead['es'] ),
			'ur-Latn' => $a( '<p>Yeh maloomat clinic ki website par nahi mili, is liye main andaza nahi lagaunga. Main team se keh sakta hoon ke woh aap ko sahi jawab dein.</p>', 'en', array(), $book_lead['en'] ),
			'ur'      => $a( '<p>یہ معلومات کلینک کی ویب سائٹ پر نہیں ملی، اس لیے میں اندازہ نہیں لگاؤں گا۔ میں ٹیم سے آپ کو جواب دینے کا کہہ سکتا ہوں۔</p>', 'ur', array() ),
			'hi'      => $a( '<p>यह जानकारी क्लिनिक की वेबसाइट पर नहीं मिली, इसलिए मैं अंदाज़ा नहीं लगाऊँगा। मैं टीम से आपको सही जवाब देने के लिए कह सकता हूँ।</p>', 'hi', array() ),
		),
	);
}

/**
 * [tw_live_demo] The #live-demo section content. Embeds the real Talkwyn widget when
 * a demo shortcode is configured; otherwise an honest, scripted preview.
 */
add_shortcode(
	'tw_live_demo',
	static function () {
		$real = (string) talkwyn_setting( 'demo_shortcode' );
		if ( '' !== $real && preg_match( '/^\[([a-z0-9_-]+)/i', $real, $m ) && shortcode_exists( $m[1] ) ) {
			return '<div class="tw-demo-head tw-center"><h2>' . esc_html__( 'Ask it anything. Really.', 'talkwyn' ) . '</h2>'
				. '<p class="tw-lede">' . esc_html__( 'This is the same Talkwyn that will run on your site, set up here for a sample dental clinic. Ask about whitening prices, Saturday hours, or insurance. Try it in Arabic or Spanish. Then try asking something the clinic’s website doesn’t cover and watch it admit it doesn’t know.', 'talkwyn' ) . '</p></div>'
				. '<div class="tw-demo-embed">' . do_shortcode( $real ) . '</div>';
		}

		$chips     = array(
			array( 'How much is whitening?', 'en' ),
			array( 'هل تفتحون يوم السبت؟', 'ar' ),
			array( 'Do you accept my insurance?', 'en' ),
			array( 'Can I book for tomorrow?', 'en' ),
		);
		$chip_html = '<div class="tw-chat__chips" role="group" aria-label="' . esc_attr__( 'Suggested questions', 'talkwyn' ) . '">';
		foreach ( $chips as $chip ) {
			$dir        = 'ar' === $chip[1] ? ' dir="rtl"' : '';
			$chip_html .= '<button type="button" class="tw-chip" lang="' . esc_attr( $chip[1] ) . '"' . $dir . ' data-tw-ask="' . esc_attr( $chip[0] ) . '">' . esc_html( $chip[0] ) . '</button>';
		}
		$chip_html .= '</div>';

		$welcome = talkwyn_msg( 'bot', '<p>' . esc_html__( 'Hi! I’m the assistant for Brightside Dental, a sample clinic. Ask me about prices, hours, insurance, or booking, in any language.', 'talkwyn' ) . '</p>' );
		$body    = '<div class="tw-chat__body" role="log" aria-live="polite" aria-label="' . esc_attr__( 'Demo conversation', 'talkwyn' ) . '">' . $welcome . '</div>';
		$form    = '<form class="tw-chat__form"><label class="screen-reader-text" for="tw-demo-input">' . esc_html__( 'Type your question', 'talkwyn' ) . '</label><input id="tw-demo-input" type="text" autocomplete="off" maxlength="300" placeholder="' . esc_attr__( 'Type a question in any language', 'talkwyn' ) . '"><button type="submit" class="tw-chat__send" aria-label="' . esc_attr__( 'Send', 'talkwyn' ) . '">' . talkwyn_icon( 'send', 18 ) . '</button></form>';
		$note    = '<p class="tw-chat__note">' . esc_html__( 'Scripted preview with sample answers from a fictional clinic. The live demo is on its way.', 'talkwyn' ) . '</p>';
		$data    = '<script type="application/json">' . wp_json_encode( talkwyn_demo_data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';

		return '<div class="tw-demo-grid"><div class="tw-demo-head"><h2>' . esc_html__( 'See how it answers', 'talkwyn' ) . '</h2>'
			. '<p class="tw-lede">' . esc_html__( 'This preview is set up for a sample dental clinic. Ask about whitening prices, Saturday hours, or insurance. Try it in Arabic or Spanish. Then ask something the clinic’s website doesn’t cover and watch it admit it doesn’t know.', 'talkwyn' ) . '</p>'
			. '<ul class="tw-demo-points"><li>' . talkwyn_icon( 'languages', 20 ) . esc_html__( 'Replies in the language you write in', 'talkwyn' ) . '</li><li>' . talkwyn_icon( 'file-text', 20 ) . esc_html__( 'Shows the pages each answer came from', 'talkwyn' ) . '</li><li>' . talkwyn_icon( 'inbox', 20 ) . esc_html__( 'Offers a follow-up when you’re ready to book', 'talkwyn' ) . '</li></ul></div>'
			. '<div class="tw-demo" data-tw-demo>' . talkwyn_chat_frame( $body, $chip_html . $form . $note ) . $data . '</div></div>';
	}
);

/**
 * Founding customers section; hidden when disabled in Site Settings.
 *
 * @param array<string, string>|string $atts    Attributes: style="band|panel".
 * @param string|null                   $content Inner content (unused).
 */
add_shortcode(
	'tw_founding',
	static function ( $atts ) {
		if ( ! talkwyn_setting( 'founding_enabled' ) ) {
			return '';
		}
		$atts      = shortcode_atts( array( 'style' => 'panel' ), $atts, 'tw_founding' );
		$total     = max( 1, (int) talkwyn_setting( 'founding_seats' ) );
		$remaining = min( $total, (int) talkwyn_setting( 'founding_remaining' ) );
		if ( $remaining <= 0 ) {
			return ''; // Offer is full: hide it rather than advertise seats that don't exist.
		}
		$pct  = (int) round( 100 * ( $total - $remaining ) / $total );
		$band = 'band' === $atts['style'];
		return '<div id="founding" class="tw-founding-wrap ' . ( $band ? 'is-style-band tw-founding-wrap--band' : 'is-style-panel' ) . '"><div class="tw-founding">'
			. '<div><h2>' . esc_html( sprintf( /* translators: %s: number of founding seats */ __( 'Be one of our first %s.', 'talkwyn' ), number_format_i18n( $total ) ) ) . '</h2>'
			. '<p>' . esc_html__( 'Talkwyn is a new company, and we’re building it with our first customers. Founding customers get a locked-in price for as long as they renew, direct access to the people building the product, and a say in what we build next.', 'talkwyn' ) . '</p>'
			. '<p><a class="tw-btn" href="' . esc_url( is_page( 'pricing' ) ? '#plans' : home_url( '/pricing/#founding' ) ) . '" data-tw-event="pricing_cta" data-tw-location="founding">' . esc_html__( 'Claim founding pricing', 'talkwyn' ) . '</a></p></div>'
			. '<div class="tw-seats"><span class="tw-seats__num">' . esc_html( number_format_i18n( $remaining ) ) . '</span><span class="tw-seats__label">' . esc_html( sprintf( /* translators: %s: total seats */ __( 'of %s founding seats left', 'talkwyn' ), number_format_i18n( $total ) ) ) . '</span>'
			. '<div class="tw-seats__bar" role="progressbar" aria-valuemin="0" aria-valuemax="' . (int) $total . '" aria-valuenow="' . (int) ( $total - $remaining ) . '" aria-label="' . esc_attr__( 'Founding seats claimed', 'talkwyn' ) . '"><span style="inline-size:' . (int) $pct . '%"></span></div></div>'
			. '</div></div>';
	}
);

/**
 * [tw_pricing_teaser] Four plan summaries with prices from Site Settings.
 */
add_shortcode(
	'tw_pricing_teaser',
	static function () {
		$line  = static function ( string $plan, string $sites ): string {
			$price = talkwyn_plan_price( $plan );
			return '' !== $price
				/* translators: 1: price, 2: number of sites */
				? sprintf( __( '%1$s per year for %2$s', 'talkwyn' ), $price, $sites )
				/* translators: %s: number of sites */
				: sprintf( __( 'Yearly license for %s', 'talkwyn' ), $sites );
		};
		$items = array(
			array( __( 'Free', 'talkwyn' ), __( 'Site scan, multilingual answers, lead capture, chat history, free AI providers.', 'talkwyn' ), talkwyn_install_url() ),
			array( __( 'Personal', 'talkwyn' ), $line( 'personal', __( '1 site', 'talkwyn' ) ), '' ),
			array( __( 'Business', 'talkwyn' ), $line( 'business', __( '5 sites', 'talkwyn' ) ), '' ),
			array( __( 'Agency', 'talkwyn' ), $line( 'agency', __( 'unlimited sites', 'talkwyn' ) ), '' ),
		);
		$html  = '<ul class="tw-pricing-teaser">';
		foreach ( $items as $item ) {
			$name  = $item[2] ? '<a href="' . esc_url( $item[2] ) . '" data-tw-event="install_click" data-tw-location="pricing_teaser">' . esc_html( $item[0] ) . '</a>' : esc_html( $item[0] );
			$html .= '<li><strong>' . $name . '</strong>' . esc_html( $item[1] ) . '</li>';
		}
		return $html . '</ul>';
	}
);

/**
 * FAQPage schema: collect Q&A from core/details blocks inside any group with the
 * "tw-faq" class, exactly as rendered.
 *
 * @param string               $html  Block HTML.
 * @param array<string, mixed> $block Block.
 */
add_filter(
	'render_block_core/group',
	static function ( $html, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'tw-faq' ) ) {
			return $html;
		}
		$items = array();
		foreach ( (array) $block['innerBlocks'] as $inner ) {
			if ( 'core/details' !== $inner['blockName'] ) {
				continue;
			}
			$rendered = render_block( $inner );
			if ( preg_match( '#<summary[^>]*>(.*?)</summary>(.*)</details>#s', $rendered, $m ) ) {
				$items[] = array( trim( wp_strip_all_tags( $m[1] ) ), trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( do_shortcode( $m[2] ) ) ) ) );
			}
		}
		if ( $items ) {
			talkwyn_faq_registry( $items );
		}
		return $html;
	},
	10,
	2
);

/**
 * [tw_trust_strip] Honest launch-stage proof points. The founding line hides when
 * founding pricing is switched off.
 */
add_shortcode(
	'tw_trust_strip',
	static function () {
		$items = array(
			array( 'layers', __( 'Built for WordPress, WooCommerce, and Shopify', 'talkwyn' ) ),
			array( 'languages', __( 'Replies in Arabic, Urdu, Hindi, English, and many more languages', 'talkwyn' ) ),
			array( 'database', __( 'Your data stays in your WordPress database', 'talkwyn' ) ),
		);
		if ( talkwyn_setting( 'founding_enabled' ) ) {
			/* translators: %s: number of founding seats */
			$items[] = array( 'badge-check', sprintf( __( 'Founding pricing for our first %s customers', 'talkwyn' ), talkwyn_value( 'founding_seats' ) ) );
		}
		$html = '<ul class="tw-trust">';
		foreach ( $items as $item ) {
			$html .= '<li>' . talkwyn_icon( $item[0], 20 ) . esc_html( $item[1] ) . '</li>';
		}
		return $html . '</ul>';
	}
);
