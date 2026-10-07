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
		$chat  = str_replace( '<div class="tw-chat ', '<div data-tw-play="loop" class="tw-chat ', talkwyn_chat_frame( $body, $extra, '', 'tw-chat--hero' ) );
		return '<div class="tw-hero__card" data-tw-tilt>' . $chat . '</div>';
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
				'keywords' => array( 'whiten', 'bleach', 'blanque', 'تبييض', 'blanchiment', 'white', 'सफ़ेद', 'सफेद' ),
				'answers'  => array(
					'en'      => $a( '<p>In-office whitening is <strong>$249</strong> and takes about an hour. A take-home kit with custom trays is <strong>$149</strong>.</p><p>Would you like to book a free consultation first?</p>', 'en', array( 'Whitening', 'Prices' ) ),
					'ar'      => $a( '<p>سعر تبييض الأسنان في العيادة <strong>249 دولارًا</strong> ويستغرق حوالي ساعة. أما الطقم المنزلي فسعره <strong>149 دولارًا</strong>.</p>', 'ar', array( 'Whitening', 'Prices' ) ),
					'es'      => $a( '<p>El blanqueamiento en la clínica cuesta <strong>249 dólares</strong> y dura alrededor de una hora. El kit para casa cuesta <strong>149 dólares</strong>.</p>', 'es', array( 'Whitening', 'Prices' ) ),
					'fr'      => $a( '<p>Le blanchiment au cabinet coûte <strong>249 $</strong> et dure environ une heure. Le kit à domicile coûte <strong>149 $</strong>.</p>', 'fr', array( 'Whitening', 'Prices' ) ),
					'hi'      => $a( '<p>क्लिनिक में दांत सफ़ेद करने की कीमत <strong>$249</strong> है और इसमें लगभग एक घंटा लगता है। घर पर इस्तेमाल की किट <strong>$149</strong> की है।</p>', 'hi', array( 'Whitening', 'Prices' ) ),
				),
			),
			array(
				'keywords' => array( 'saturday', 'sunday', 'hour', 'open', 'close', 'time', 'السبت', 'تفتح', 'مواعيد', 'sábado', 'horario', 'abren', 'samedi', 'horaire', 'ouvert', 'शनिवार', 'खुल' ),
				'answers'  => array(
					'en'      => $a( '<p>Yes, we are open on Saturday from 9 am to 2 pm. On weekdays we are open 9 am to 6 pm, and we are closed on Sunday.</p>', 'en', array( 'Opening hours' ) ),
					'ar'      => $a( '<p>نعم، نفتح يوم السبت من التاسعة صباحًا حتى الثانية ظهرًا. في أيام الأسبوع نفتح من التاسعة صباحًا حتى السادسة مساءً، ونغلق يوم الأحد.</p>', 'ar', array( 'Opening hours' ) ),
					'es'      => $a( '<p>Sí, abrimos el sábado de 9 a 14 h. Entre semana abrimos de 9 a 18 h y cerramos los domingos.</p>', 'es', array( 'Opening hours' ) ),
					'fr'      => $a( '<p>Oui, nous sommes ouverts le samedi de 9 h à 14 h, en semaine de 9 h à 18 h, et fermés le dimanche.</p>', 'fr', array( 'Opening hours' ) ),
					'ur'      => $a( '<p>جی ہاں، ہم ہفتے کو صبح نو بجے سے دوپہر دو بجے تک کھلے ہیں۔ اتوار کو کلینک بند رہتا ہے۔</p>', 'ur', array( 'Opening hours' ) ),
					'hi'      => $a( '<p>जी हाँ, हम शनिवार को सुबह 9 बजे से दोपहर 2 बजे तक खुले हैं। रविवार को क्लिनिक बंद रहता है।</p>', 'hi', array( 'Opening hours' ) ),
				),
			),
			array(
				'keywords' => array( 'insur', 'ppo', 'cover', 'تأمين', 'seguro', 'aseguradora', 'assurance', 'mutuelle', 'बीमा' ),
				'answers'  => array(
					'en' => $a( '<p>We accept most PPO dental plans and file the claim for you. Bring your insurance card to your first visit. If you tell me your plan, the front desk can confirm your coverage before you book.</p>', 'en', array( 'Insurance and payment' ) ),
					'ar' => $a( '<p>نقبل معظم خطط التأمين على الأسنان من نوع PPO ونقدّم المطالبة نيابةً عنك. أحضر بطاقة التأمين في زيارتك الأولى.</p>', 'ar', array( 'Insurance and payment' ) ),
					'es' => $a( '<p>Aceptamos la mayoría de los planes dentales PPO y presentamos la reclamación por ti. Trae tu tarjeta del seguro a la primera visita.</p>', 'es', array( 'Insurance and payment' ) ),
					'hi' => $a( '<p>हम ज़्यादातर PPO डेंटल प्लान स्वीकार करते हैं और क्लेम आपके लिए फ़ाइल करते हैं। पहली विज़िट पर अपना बीमा कार्ड साथ लाएँ।</p>', 'hi', array( 'Insurance and payment' ) ),
				),
			),
			array(
				'keywords' => array( 'book', 'appointment', 'tomorrow', 'schedule', 'slot', 'call me', 'موعد', 'حجز', 'غدا', 'cita', 'mañana', 'reservar', 'rendez-vous', 'demain', 'अपॉइंटमेंट', 'कल' ),
				'answers'  => array(
					'en'      => $a( '<p>Tomorrow we have openings in the morning and late afternoon. I can’t book directly from here, but I can pass your request to the front desk.</p>', 'en', array( 'Book a visit' ), $book_lead['en'] ),
					'ar'      => $a( '<p>لدينا مواعيد متاحة غدًا في الصباح وبعد العصر. لا أستطيع الحجز من هنا مباشرةً، لكن يمكنني إرسال طلبك إلى الاستقبال.</p>', 'ar', array( 'Book a visit' ), $book_lead['ar'] ),
					'es'      => $a( '<p>Mañana hay horas libres por la mañana y a última hora de la tarde. No puedo reservar desde aquí, pero puedo pasar tu solicitud a recepción.</p>', 'es', array( 'Book a visit' ), $book_lead['es'] ),
					'fr'      => $a( '<p>Il reste des créneaux demain matin et en fin de journée. Je ne peux pas réserver ici, mais je peux transmettre votre demande à l\'accueil.</p>', 'fr', array( 'Book an appointment' ) ),
				),
			),
			array(
				'keywords' => array( 'where', 'address', 'parking', 'located', 'location', 'أين', 'العنوان', 'dónde', 'dirección', 'adresse', 'où', 'कहाँ', 'पता' ),
				'answers'  => array(
					'en' => $a( '<p>Brightside Dental is at 120 Harbor Street, second floor, with free parking behind the building. It is a two-minute walk from the Central bus stop.</p>', 'en', array( 'Contact and directions' ) ),
					'ar' => $a( '<p>تقع عيادة برايتسايد في 120 شارع هاربر، الطابق الثاني، مع موقف مجاني خلف المبنى.</p>', 'ar', array( 'Contact and directions' ) ),
					'es' => $a( '<p>Brightside Dental está en 120 Harbor Street, segundo piso, con aparcamiento gratuito detrás del edificio.</p>', 'es', array( 'Contact and directions' ) ),
				),
			),
			array(
				'keywords' => array( 'hello', 'hi ', 'hey', 'salam', 'مرحبا', 'السلام', 'hola', 'bonjour', 'नमस्ते' ),
				'answers'  => array(
					'en'      => $a( '<p>Hi! I can help with prices, opening hours, insurance, and booking at Brightside Dental. What would you like to know?</p>', 'en', array() ),
					'ar'      => $a( '<p>أهلًا وسهلًا! يمكنني مساعدتك في الأسعار ومواعيد العمل والتأمين والحجز. بماذا أستطيع مساعدتك؟</p>', 'ar', array() ),
					'es'      => $a( '<p>¡Hola! Puedo ayudarte con precios, horarios, seguros y citas en Brightside Dental. ¿Qué te gustaría saber?</p>', 'es', array() ),
					'fr'      => $a( '<p>Bonjour ! Je peux vous aider pour les tarifs, les horaires, les assurances et les rendez-vous. Que souhaitez-vous savoir ?</p>', 'fr', array() ),
					'hi'      => $a( '<p>नमस्ते! मैं कीमतों, समय, बीमा और अपॉइंटमेंट में मदद कर सकता हूँ। आप क्या जानना चाहेंगे?</p>', 'hi', array() ),
				),
			),
		),
		'unknown'      => array(
			'en'      => $a( '<p>I couldn’t find that on Brightside Dental’s website, so I won’t guess. I can ask the team to get back to you with the right answer.</p>', 'en', array(), $book_lead['en'] ),
			'ar'      => $a( '<p>لم أجد هذه المعلومة على موقع العيادة، لذلك لن أخمّن. يمكنني أن أطلب من الفريق التواصل معك بالإجابة الصحيحة.</p>', 'ar', array(), $book_lead['ar'] ),
			'es'      => $a( '<p>No encontré eso en la web de la clínica, así que no voy a adivinar. Puedo pedir al equipo que te responda.</p>', 'es', array(), $book_lead['es'] ),
			'fr'      => $a( '<p>Je n\'ai pas trouvé cette information sur le site de la clinique, donc je préfère ne pas deviner. Je peux demander à l\'équipe de vous répondre.</p>', 'fr', array(), $book_lead['en'] ),
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
		return '<div id="founding" class="tw-founding-wrap ' . ( $band ? 'is-style-band tw-on-ink tw-founding-wrap--band' : 'is-style-panel' ) . '" data-tw-reveal><div class="tw-founding">'
			. '<div><h2>' . esc_html( sprintf( /* translators: %s: number of founding seats */ __( 'Be one of our first %s.', 'talkwyn' ), number_format_i18n( $total ) ) ) . '</h2>'
			. '<p>' . esc_html__( 'Talkwyn is a new company, and we’re building it with our first customers. Founding customers get a locked-in price for as long as they renew, direct access to the people building the product, and a say in what we build next.', 'talkwyn' ) . '</p>'
			. '<p><a class="tw-pill tw-pill--white" href="' . esc_url( is_page( 'pricing' ) ? '#plans' : home_url( '/pricing/#founding' ) ) . '" data-tw-event="pricing_cta" data-tw-location="founding">' . esc_html__( 'Claim founding pricing', 'talkwyn' ) . '</a></p></div>'
			. '<div class="tw-seats"><span class="tw-seats__num" data-tw-count="' . (int) $remaining . '">' . esc_html( number_format_i18n( $remaining ) ) . '</span><span class="tw-seats__label">' . esc_html( sprintf( /* translators: %s: total seats */ __( 'of %s founding seats left', 'talkwyn' ), number_format_i18n( $total ) ) ) . '</span>'
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
		$plans = talkwyn_plans();
		$icons = array(
			'free'     => 'gift',
			'personal' => 'user-check',
			'business' => 'building-2',
			'agency'   => 'briefcase',
		);
		$html  = '<ul class="tw-tiers" data-tw-reveal data-tw-stagger>';
		foreach ( $icons as $slug => $icon ) {
			if ( empty( $plans[ $slug ] ) ) {
				continue;
			}
			$plan  = $plans[ $slug ];
			$price = 'free' === $slug ? '$0' : talkwyn_plan_price( $slug );
			$html .= '<li class="tw-tier tw-tier--' . esc_attr( $slug ) . '">';
			if ( ! empty( $plan['featured'] ) ) {
				$html .= '<span class="tw-tier__flag">' . esc_html__( 'Most popular', 'talkwyn' ) . '</span>';
			}
			$html .= '<div class="tw-tier__top"><span class="tw-tier__icon">' . talkwyn_icon( $icon, 22 ) . '</span><h3 class="tw-tier__name">' . esc_html( $plan['name'] ) . '</h3></div>';
			if ( '' !== $price ) {
				$per   = 'free' === $slug ? __( 'forever', 'talkwyn' ) : __( 'per year', 'talkwyn' );
				$html .= '<p class="tw-tier__line"><span class="tw-tier__price">' . esc_html( $price ) . '</span> <span class="tw-tier__per">' . esc_html( $per ) . '</span></p>';
			} else {
				$html .= '<p class="tw-tier__line"><span class="tw-tier__price tw-tier__price--text">' . esc_html__( 'Yearly license', 'talkwyn' ) . '</span></p>';
			}
			$html .= '<p class="tw-tier__sites">' . esc_html( $plan['sites'] ) . '</p><ul class="tw-tier__feats">';
			foreach ( array_slice( (array) $plan['features'], 0, 3 ) as $feat ) {
				$html .= '<li>' . talkwyn_icon( 'check', 16 ) . esc_html( $feat ) . '</li>';
			}
			$html .= '</ul>';
			if ( 'free' === $slug ) {
				$html .= '<a class="tw-tier__cta" href="' . esc_url( talkwyn_install_url() ) . '" data-tw-event="install_click" data-tw-location="pricing_teaser">' . esc_html__( 'Install free', 'talkwyn' ) . talkwyn_icon( 'arrow-right', 16 ) . '</a>';
			} else {
				/* translators: %s: plan name */
				$html .= '<a class="tw-tier__cta" href="' . esc_url( home_url( '/pricing/#plans' ) ) . '">' . esc_html( sprintf( __( 'See %s', 'talkwyn' ), $plan['name'] ) ) . talkwyn_icon( 'arrow-right', 16 ) . '</a>';
			}
			$html .= '</li>';
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
			array( 'plug', __( 'Live on WordPress and WooCommerce, with more platforms coming', 'talkwyn' ) ),
			array( 'languages', __( 'Replies in English, Spanish, French, German, Arabic, Hindi, Chinese, and many more languages', 'talkwyn' ) ),
			array( 'database', __( 'Your chats and leads stay on your own site', 'talkwyn' ) ),
		);
		if ( talkwyn_setting( 'founding_enabled' ) ) {
			/* translators: %s: number of founding seats */
			$items[] = array( 'badge-check', sprintf( __( 'Founding pricing for our first %s customers', 'talkwyn' ), talkwyn_value( 'founding_seats' ) ) );
		}
		$html = '<ul class="tw-trust" data-tw-reveal data-tw-stagger>';
		foreach ( $items as $item ) {
			$html .= '<li><span class="tw-trust__icon">' . talkwyn_icon( $item[0], 20 ) . '</span><span>' . esc_html( $item[1] ) . '</span></li>';
		}
		return $html . '</ul>';
	}
);

/**
 * [tw_logo_mark] The Rising Dots mark (decorative).
 */
add_shortcode(
	'tw_logo_mark',
	static function () {
		return talkwyn_logo_svg( 'mark', '' );
	}
);

/**
 * [tw_integrations set="home|all"] Integration cards with live status pills from Site Settings.
 *
 * @param array<string, string>|string $atts Attributes.
 */
add_shortcode(
	'tw_integrations',
	static function ( $atts ) {
		$atts  = shortcode_atts( array( 'set' => 'home' ), $atts, 'tw_integrations' );
		$all   = talkwyn_integrations();
		$cards = array(
			'wordpress'     => array( 'plug', __( 'Our <a class="tw-card__title-link" href="/integrations/wordpress/">WordPress chatbot plugin</a> installs in one click and understands Elementor pages out of the box.', 'talkwyn' ) ),
			'woocommerce'   => array( 'store', __( 'Answers about products, prices, stock, and shipping.', 'talkwyn' ) ),
			'elementor'     => array( 'layout-template', __( 'Reads the text inside your Elementor pages, sections, and widgets.', 'talkwyn' ) ),
			'shopify'       => array( 'shopping-bag', __( 'Product answers, order questions, and lead capture.', 'talkwyn' ) ),
			'website_embed' => array( 'code-xml', __( 'One line of code for any site builder or custom site.', 'talkwyn' ) ),
			'whatsapp'      => array( 'smartphone', __( 'New leads straight to your phone.', 'talkwyn' ) ),
		);
		if ( 'home' === $atts['set'] ) {
			unset( $cards['elementor'] );
		}
		$html = '<div class="tw-integrations" data-tw-reveal data-tw-stagger>';
		foreach ( $cards as $slug => $card ) {
			$row   = $all[ $slug ];
			$title = 'wordpress' === $slug
				? esc_html( $row['name'] )
				: '<a class="tw-card__title-link" href="' . esc_url( home_url( $row['url'] ) ) . '">' . esc_html( $row['name'] ) . '</a>';
			$desc  = 'wordpress' === $slug ? wp_kses_post( str_replace( 'href="/', 'href="' . esc_url( home_url( '/' ) ), $card[1] ) ) : esc_html( $card[1] );
			$html .= '<article class="tw-integration tw-spot"><div class="tw-integration__top"><span class="tw-integration__logo">' . talkwyn_icon( $card[0], 24 ) . '</span>' . talkwyn_status_pill( $row['status'] ) . '</div>'
				. '<h3>' . $title . '</h3><p>' . $desc . '</p>'
				. '<span class="tw-card__more" aria-hidden="true">' . esc_html( 'available' === $row['status'] ? __( 'See how it works', 'talkwyn' ) : __( 'Join the waitlist', 'talkwyn' ) ) . talkwyn_icon( 'arrow-right', 16 ) . '</span></article>';
		}
		$planned = array();
		foreach ( array( 'wix', 'webflow', 'squarespace', 'hubspot', 'zapier' ) as $slug ) {
			if ( 'planned' === $all[ $slug ]['status'] ) {
				$planned[] = $all[ $slug ]['name'];
			}
		}
		if ( $planned ) {
			$html .= '<article class="tw-integration tw-integration--planned tw-spot"><div class="tw-integration__top"><span class="tw-integration__logo">' . talkwyn_icon( 'vote', 24 ) . '</span>' . talkwyn_status_pill( 'planned' ) . '</div>'
				. '<h3><a class="tw-card__title-link" href="' . esc_url( home_url( '/integrations/#planned' ) ) . '">' . esc_html( implode( ', ', $planned ) ) . '</a></h3><p>' . esc_html__( 'Vote for yours.', 'talkwyn' ) . '</p>'
				. '<span class="tw-card__more" aria-hidden="true">' . esc_html__( 'Vote for the next one', 'talkwyn' ) . talkwyn_icon( 'arrow-right', 16 ) . '</span></article>';
		}
		return $html . '</div>';
	}
);

/**
 * [tw_status_list only="wordpress,shopify" title="..."] Compact live status card for inner page visuals.
 */
add_shortcode(
	'tw_status_list',
	static function ( $atts ) {
		$atts = shortcode_atts(
			array(
				'only'  => '',
				'title' => '',
			),
			$atts,
			'tw_status_list'
		);
		$all  = talkwyn_integrations();
		$keys = array_filter( array_map( 'trim', explode( ',', (string) $atts['only'] ) ) );
		$keys = $keys ? $keys : array_keys( $all );
		$rows = '';
		foreach ( $keys as $key ) {
			if ( empty( $all[ $key ] ) ) {
				continue;
			}
			$row   = $all[ $key ];
			$rows .= '<li><a href="' . esc_url( home_url( $row['url'] ) ) . '"><span class="tw-vcard__label">' . esc_html( $row['name'] ) . '</span>' . talkwyn_status_pill( $row['status'] ) . '</a></li>';
		}
		return '<div class="tw-vcard tw-vcard--status"><div class="tw-vcard__head"><span class="tw-vcard__icon">' . talkwyn_icon( 'plug', 18 ) . '</span><b>' . esc_html( $atts['title'] ) . '</b><span class="tw-vcard__badge">' . esc_html__( 'Live status', 'talkwyn' ) . '</span></div><ul class="tw-vcard__rows">' . $rows . '</ul></div>';
	}
);

/**
 * [tw_status_legend] The three status labels with what each one means.
 */
add_shortcode(
	'tw_status_legend',
	static function () {
		$items = array(
			'available'      => __( 'Live today. Install it and use it now.', 'talkwyn' ),
			'in_development' => __( 'Being built now. No launch dates.', 'talkwyn' ),
			'planned'        => __( 'On the list. Your vote moves it up.', 'talkwyn' ),
		);
		$rows  = '';
		foreach ( $items as $status => $text ) {
			$rows .= '<li class="tw-legend__row">' . talkwyn_status_pill( $status ) . '<span>' . esc_html( $text ) . '</span></li>';
		}
		return '<div class="tw-vcard"><div class="tw-vcard__head"><span class="tw-vcard__icon">' . talkwyn_icon( 'list-checks', 18 ) . '</span><b>' . esc_html__( 'Status labels', 'talkwyn' ) . '</b></div><ul class="tw-vcard__rows tw-legend">' . $rows . '</ul></div>';
	}
);

/**
 * [tw_explore] Homepage band: all 12 industries and all 12 languages, each a link to its page.
 */
add_shortcode(
	'tw_explore',
	static function () {
		$lists = talkwyn_site_lists();
		$ind   = '';
		foreach ( $lists['industries'] as $i ) {
			$ind .= '<li><a href="' . esc_url( home_url( $i[2] ) ) . '"><span class="tw-xp__icon">' . talkwyn_icon( $i[0], 18 ) . '</span>' . esc_html( $i[1] ) . talkwyn_icon( 'arrow-right', 14 ) . '</a></li>';
		}
		$lang = '';
		foreach ( $lists['languages'] as $l ) {
			$dir   = 'ar' === $l[4] ? ' dir="rtl"' : '';
			$lang .= '<li><a href="' . esc_url( home_url( $l[2] ) ) . '"><b lang="' . esc_attr( $l[4] ) . '"' . $dir . '>' . esc_html( $l[3] ) . '</b><span>' . esc_html( $l[1] ) . '</span></a></li>';
		}
		return '<div class="tw-xp">'
			. '<div class="tw-xp__panel"><div class="tw-xp__head"><p class="tw-xp__kicker">' . esc_html__( '12 industries', 'talkwyn' ) . '</p><h3>' . esc_html__( 'Set up for the questions your business gets', 'talkwyn' ) . '</h3></div><ul class="tw-xp__ind">' . $ind . '</ul>'
			. '<a class="tw-arrow-link" href="' . esc_url( home_url( '/industries/' ) ) . '">' . esc_html__( 'All industries', 'talkwyn' ) . talkwyn_icon( 'arrow-right', 16 ) . '</a></div>'
			. '<div class="tw-xp__panel"><div class="tw-xp__head"><p class="tw-xp__kicker">' . esc_html__( '12 focus languages', 'talkwyn' ) . '</p><h3>' . esc_html__( 'Replies in the language each visitor writes in', 'talkwyn' ) . '</h3></div><ul class="tw-xp__lang">' . $lang . '</ul>'
			. '<a class="tw-arrow-link" href="' . esc_url( home_url( '/multilingual-chatbot/' ) ) . '">' . esc_html__( 'How the multilingual chatbot works', 'talkwyn' ) . talkwyn_icon( 'arrow-right', 16 ) . '</a></div>'
			. '</div>';
	}
);
