<?php
/**
 * Conversation helpers: small talk, intent, lead offers and local answers.
 *
 * Ported from Nabia AI Chatbot 1.9.0. Small talk in common languages is answered
 * locally to save provider quota.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Conversation rules.
 */
class Talkwyn_Conversation {
	public static function intent($message) {
		$q = trim(Talkwyn_Text::lower($message));
		$q = preg_replace('/\s+/u', ' ', $q);
		if ($q === '') { return 'business'; }

		// Local handling for common English and Roman Urdu small talk. This saves API quota.
		$local_patterns = array(
			"/^(hi|hello|hey|hiya|yo|salam|salaam|aoa|assalamualaikum|assalam o alaikum|good morning|good afternoon|good evening|good night)( there)?[!.? ]*$/iu",
			"/^(hru|how r u|how are u|how are you|how are you doing|how you doing|how is it going|how's it going|how are things|you good|u good|what is up|what's up|whats up|wassup|sup)[!.? ]*$/iu",
			"/^(kese ho|kaise ho|kaisay ho|kese hain|kaise hain|kia haal hai|kya haal hai|kya haal|kia haal|theek ho|thik ho)[!.? ]*$/iu",
			"/^(thanks|thank you|thankyou|thx|ty|shukriya|jazakallah|great thanks|okay thanks|ok thanks)[!.? ]*$/iu",
			"/^(ok|okay|alright|all right|cool|nice|great|perfect|got it|understood|sounds good|fine)[!.? ]*$/iu",
			"/^(bye|goodbye|see you|see ya|talk later|take care|allah hafiz|khuda hafiz)[!.? ]*$/iu",
			"/^(who are you|what are you|are you a bot|are you ai|what can you do|help|can you help me|can you help)[!.? ]*$/iu",
		);
		foreach ($local_patterns as $pattern) {
			if (preg_match($pattern, $q)) { return 'social_local'; }
		}

		// Known non-English small talk skips website retrieval but is sent to the AI provider
		// so the response stays natural in the visitor language.
		$ai_patterns = array(
			'/^(hallo|hoi|goedemorgen|goedemiddag|goedenavond|hoe gaat het|hoe gaat het met je|bedankt|dank je|dankjewel|prima|tot ziens|doei)[!.? ]*$/iu',
			'/^(bonjour|salut|bonsoir|comment ça va|comment ca va|ça va|ca va|merci|merci beaucoup|d accord|d’accord|au revoir|à bientôt|a bientot)[!.? ]*$/iu',
			'/^(hallo|guten morgen|guten tag|guten abend|wie geht es dir|wie gehts|wie geht’s|danke|vielen dank|alles klar|tschüss|tschuss|auf wiedersehen)[!.? ]*$/iu',
			'/^(hola|buenos días|buenos dias|buenas tardes|buenas noches|cómo estás|como estas|qué tal|que tal|gracias|muchas gracias|vale|hasta luego|adiós|adios)[!.? ]*$/iu',
			'/^(ciao|buongiorno|buonasera|come stai|come va|grazie|grazie mille|va bene|arrivederci|a presto)[!.? ]*$/iu',
			'/^(olá|ola|bom dia|boa tarde|boa noite|como vai|tudo bem|obrigado|obrigada|muito obrigado|muito obrigada|até logo|ate logo|tchau)[!.? ]*$/iu',
			'/^(merhaba|selam|günaydın|gunaydin|nasılsın|nasilsin|teşekkürler|tesekkurler|sağ ol|sag ol|tamam|görüşürüz|gorusuruz)[!.? ]*$/iu',
			'/^(مرحبا|مرحباً|أهلا|اهلا|السلام عليكم|كيف حالك|كيف الحال|شكرا|شكراً|حسنا|حسنًا|مع السلامة)[!.؟? ]*$/u',
			'/^(سلام|السلام علیکم|آپ کیسے ہیں|کیسے ہو|شکریہ|ٹھیک ہے|خدا حافظ|اللہ حافظ)[!.؟? ]*$/u',
			'/^(नमस्ते|नमस्कार|आप कैसे हैं|कैसे हो|धन्यवाद|ठीक है|अलविदा)[!.? ]*$/u',
			'/^(你好|您好|你好吗|谢谢|好的|再见)[!！。?？ ]*$/u',
			'/^(こんにちは|こんばんは|おはよう|お元気ですか|ありがとう|ありがとうございます|わかりました|さようなら|またね)[!！。?？ ]*$/u',
			'/^(안녕하세요|안녕|잘 지내세요|어떻게 지내세요|감사합니다|고마워요|알겠습니다|안녕히 가세요)[!.? ]*$/u',
			'/^(привет|здравствуйте|доброе утро|добрый день|добрый вечер|как дела|спасибо|большое спасибо|хорошо|до свидания|пока)[!.? ]*$/iu',
			'/^(cześć|czesc|dzień dobry|dzien dobry|jak się masz|jak sie masz|dziękuję|dziekuje|dobrze|do widzenia|pa)[!.? ]*$/iu',
		);
		foreach ($ai_patterns as $pattern) {
			if (preg_match($pattern, $q)) { return 'social_ai'; }
		}

		return 'business';
	}

	public static function is_broad_catalog_question($message) {
		$q = trim(Talkwyn_Text::lower($message));
		if ($q === '') { return false; }
		return (bool) preg_match('/(what (services|products|solutions|tools|features|options).*(offer|have|provide)|what do you (offer|provide|do)|services do you offer|products do you offer|tell me about (your )?(services|products|solutions)|which (services|products|solutions) do you have|wat (bieden|doen) jullie|quels services|welche dienstleistungen|qué servicios|que servicios|quali servizi|quais serviços|hangi hizmetler|ما هي الخدمات|کون سی سروس|कौन सी सेवाएँ|哪些服务|どんなサービス|어떤 서비스|какие услуги|jakie usługi)/iu', $q);
	}

	public static function build_retrieval_query($message, $history) {
		$q = trim(wp_strip_all_tags((string) $message));
		if ($q === '' || strpos(self::intent($q), 'social_') === 0) { return $q; }

		$plain = trim(Talkwyn_Text::lower($q));
		preg_match_all('/[\p{L}\p{N}]+/u', $plain, $m);
		$word_count = count($m[0]);
		$looks_follow_up = $word_count <= 4 || (bool) preg_match(
			'/^(and\b|also\b|what about\b|how about\b|how much\b|how long\b|why\b|which one\b|does it\b|is it\b|can it\b|that\b|this\b|it\b|price\b|pricing\b|cost\b|timeline\b|time\b|more\b)/i',
			$plain
		);
		if (!$looks_follow_up) { return $q; }

		$previous_user = '';
		for ($i = count((array) $history) - 1; $i >= 0; $i--) {
			$h = $history[$i];
			if (!is_array($h) || ($h['role'] ?? '') !== 'user') { continue; }
			$candidate = trim(wp_strip_all_tags((string) ($h['content'] ?? '')));
			if ($candidate === '' || strpos(self::intent($candidate), 'social_') === 0) { continue; }
			$previous_user = $candidate;
			break;
		}
		if ($previous_user === '') { return $q; }

		return $q . "\n" . Talkwyn_Text::sub($previous_user, 0, 500);
	}

	public static function social_answer($message, $site_name, $page_lang = '') {
		$q = trim(Talkwyn_Text::lower($message));
		$q = preg_replace('/\s+/u', ' ', $q);

		$kind = 'greet';
		if (preg_match("/(hru|how are|how r u|how's it going|kese ho|kaise ho|kya haal|kia haal|hoe gaat|comment .*va|wie geht|cómo estás|como estas|come stai|como vai|nasılsın|nasilsin|كيف حالك|آپ کیسے ہیں|आप कैसे हैं|你好吗|お元気ですか|잘 지내|как дела|jak się masz|jak sie masz)/iu", $q)) { $kind = 'how'; }
		elseif (preg_match('/(thanks|thank you|shukriya|jazakallah|bedankt|dank je|merci|danke|gracias|grazie|obrigad|teşekkür|tesekkur|شكرا|شکریہ|धन्यवाद|谢谢|ありがとう|감사|спасибо|dziękuję|dziekuje)/iu', $q)) { $kind = 'thanks'; }
		elseif (preg_match('/^(ok|okay|alright|cool|nice|great|perfect|got it|understood|fine|prima|goed|d accord|d’accord|alles klar|vale|va bene|tamam|حسنا|حسنًا|ٹھیک ہے|ठीक है|好的|わかりました|알겠습니다|хорошо|dobrze)/iu', $q)) { $kind = 'ack'; }
		elseif (preg_match('/(bye|goodbye|see you|allah hafiz|khuda hafiz|tot ziens|doei|au revoir|à bientôt|a bientot|tschüss|tschuss|auf wiedersehen|hasta luego|adiós|adios|arrivederci|a presto|até logo|ate logo|tchau|görüşürüz|gorusuruz|مع السلامة|خدا حافظ|اللہ حافظ|अलविदा|再见|さようなら|またね|안녕히 가세요|до свидания|пока|do widzenia)/iu', $q)) { $kind = 'bye'; }
		elseif (preg_match('/(who are you|what are you|are you a bot|are you ai)/iu', $q)) { $kind = 'identity'; }
		elseif (preg_match('/(what can you do|can you help|^help$)/iu', $q)) { $kind = 'help'; }

		$lang = strtolower(trim((string) $page_lang));
		$lang = preg_replace('/[-_].*$/', '', $lang);
		if (preg_match('/[\x{0600}-\x{06ff}]/u', $q)) { $lang = 'ar'; }
		elseif (preg_match('/[\x{0900}-\x{097f}]/u', $q)) { $lang = 'hi'; }
		elseif (preg_match('/[\x{3040}-\x{30ff}]/u', $q)) { $lang = 'ja'; }
		elseif (preg_match('/[\x{ac00}-\x{d7af}]/u', $q)) { $lang = 'ko'; }
		elseif (preg_match('/[\x{4e00}-\x{9fff}]/u', $q)) { $lang = 'zh'; }
		elseif (preg_match('/[\x{0400}-\x{04ff}]/u', $q)) { $lang = 'ru'; }
		elseif (preg_match('/\b(hoi|goedemorgen|bedankt|dankjewel|doei)\b/iu', $q)) { $lang = 'nl'; }
		elseif (preg_match('/\b(bonjour|salut|merci|bonsoir)\b/iu', $q)) { $lang = 'fr'; }
		elseif (preg_match('/\b(guten|danke|tschüss|tschuss)\b/iu', $q)) { $lang = 'de'; }
		elseif (preg_match('/\b(hola|gracias|adiós|adios)\b/iu', $q)) { $lang = 'es'; }
		elseif (preg_match('/\b(buongiorno|grazie|arrivederci)\b/iu', $q)) { $lang = 'it'; }
		elseif (preg_match('/\b(olá|ola|obrigado|obrigada|tchau)\b/iu', $q)) { $lang = 'pt'; }
		elseif (preg_match('/\b(merhaba|selam|teşekkürler|tesekkurler)\b/iu', $q)) { $lang = 'tr'; }
		elseif (preg_match('/\b(kese ho|kaise ho|kaisay ho|kya haal|kia haal|shukriya|allah hafiz|khuda hafiz)\b/iu', $q)) { $lang = 'ur'; }
		elseif (preg_match('/\b(hi|hello|hey|hru|how are|thanks|thank you|bye|goodbye)\b/iu', $q)) { $lang = 'en'; }

		$texts = array(
			'en' => array(
				'greet' => 'Hi! How can I help you with ' . $site_name . ' today?',
				'how' => 'I am doing well, thanks for asking! How can I help you with ' . $site_name . ' today?',
				'thanks' => 'You are welcome! If you have another question about ' . $site_name . ', just ask.',
				'ack' => 'Great. What would you like to know next?',
				'bye' => 'Thanks for chatting. Have a great day!',
				'identity' => 'I am the AI assistant for ' . $site_name . '. I can answer questions about the website, services, products, and help you reach the team when needed.',
				'help' => 'Of course. Ask me anything about ' . $site_name . '. I can explain website information, help with services or products, and guide you to the right next step.',
			),
			'ur' => array(
				'greet' => 'Salam! Main ' . $site_name . ' ka AI assistant hoon. Aap kya poochna chahte hain?',
				'how' => 'Main theek hoon, shukriya! Aap ' . $site_name . ' ke bare mein kya poochna chahte hain?',
				'thanks' => 'Khush aamdeed! Agar ' . $site_name . ' ke bare mein koi aur sawal ho to pooch lein.',
				'ack' => 'Bilkul. Ab aap kya jan-na chahte hain?',
				'bye' => 'Shukriya! Allah hafiz.',
				'identity' => 'Main ' . $site_name . ' ka AI assistant hoon. Website, services aur products ke bare mein madad kar sakta hoon.',
				'help' => 'Bilkul. ' . $site_name . ' ke bare mein apna sawal poochain, main madad karta hoon.',
			),
			'nl' => array('greet'=>'Hallo! Hoe kan ik u vandaag helpen met ' . $site_name . '?','how'=>'Goed, bedankt! Hoe kan ik u helpen met ' . $site_name . '?','thanks'=>'Graag gedaan! Stel gerust nog een vraag.','ack'=>'Prima. Wat wilt u nog weten?','bye'=>'Bedankt voor het chatten. Fijne dag!','identity'=>'Ik ben de AI-assistent van ' . $site_name . '. Ik kan vragen over de website, diensten en producten beantwoorden.','help'=>'Natuurlijk. Vraag me gerust iets over ' . $site_name . '.'),
			'fr' => array('greet'=>'Bonjour ! Comment puis-je vous aider avec ' . $site_name . ' aujourd’hui ?','how'=>'Je vais bien, merci ! Comment puis-je vous aider avec ' . $site_name . ' ?','thanks'=>'Avec plaisir ! Posez-moi une autre question si vous le souhaitez.','ack'=>'Très bien. Que souhaitez-vous savoir ensuite ?','bye'=>'Merci pour votre visite. Bonne journée !','identity'=>'Je suis l’assistant IA de ' . $site_name . '. Je peux répondre aux questions sur le site, les services et les produits.','help'=>'Bien sûr. Posez-moi votre question sur ' . $site_name . '.'),
			'de' => array('greet'=>'Hallo! Wie kann ich Ihnen heute bei ' . $site_name . ' helfen?','how'=>'Mir geht es gut, danke! Wie kann ich Ihnen bei ' . $site_name . ' helfen?','thanks'=>'Gern geschehen! Stellen Sie mir gerne eine weitere Frage.','ack'=>'Alles klar. Was möchten Sie als Nächstes wissen?','bye'=>'Danke für den Chat. Einen schönen Tag!','identity'=>'Ich bin der KI-Assistent von ' . $site_name . '. Ich beantworte Fragen zur Website, zu Dienstleistungen und Produkten.','help'=>'Natürlich. Fragen Sie mich einfach etwas über ' . $site_name . '.'),
			'es' => array('greet'=>'¡Hola! ¿Cómo puedo ayudarte hoy con ' . $site_name . '?','how'=>'Estoy bien, ¡gracias! ¿Cómo puedo ayudarte con ' . $site_name . '?','thanks'=>'¡De nada! Puedes hacerme otra pregunta cuando quieras.','ack'=>'Perfecto. ¿Qué te gustaría saber ahora?','bye'=>'Gracias por chatear. ¡Que tengas un buen día!','identity'=>'Soy el asistente de IA de ' . $site_name . '. Puedo responder preguntas sobre el sitio, servicios y productos.','help'=>'Claro. Pregúntame lo que quieras sobre ' . $site_name . '.'),
			'it' => array('greet'=>'Ciao! Come posso aiutarti oggi con ' . $site_name . '?','how'=>'Sto bene, grazie! Come posso aiutarti con ' . $site_name . '?','thanks'=>'Prego! Se hai un’altra domanda, chiedi pure.','ack'=>'Perfetto. Cosa vuoi sapere adesso?','bye'=>'Grazie per la chat. Buona giornata!','identity'=>'Sono l’assistente AI di ' . $site_name . '. Posso rispondere a domande sul sito, sui servizi e sui prodotti.','help'=>'Certo. Chiedimi pure qualsiasi cosa su ' . $site_name . '.'),
			'pt' => array('greet'=>'Olá! Como posso ajudar você hoje com ' . $site_name . '?','how'=>'Estou bem, obrigado! Como posso ajudar com ' . $site_name . '?','thanks'=>'De nada! Se tiver outra pergunta, é só perguntar.','ack'=>'Perfeito. O que você gostaria de saber agora?','bye'=>'Obrigado pela conversa. Tenha um ótimo dia!','identity'=>'Sou o assistente de IA da ' . $site_name . '. Posso responder perguntas sobre o site, serviços e produtos.','help'=>'Claro. Pergunte o que quiser sobre ' . $site_name . '.'),
			'tr' => array('greet'=>'Merhaba! Bugün ' . $site_name . ' hakkında size nasıl yardımcı olabilirim?','how'=>'İyiyim, teşekkürler! ' . $site_name . ' hakkında nasıl yardımcı olabilirim?','thanks'=>'Rica ederim! Başka bir sorunuz varsa sorabilirsiniz.','ack'=>'Tamam. Şimdi ne öğrenmek istersiniz?','bye'=>'Sohbet için teşekkürler. İyi günler!','identity'=>'Ben ' . $site_name . ' için AI asistanıyım. Site, hizmetler ve ürünler hakkında soruları yanıtlayabilirim.','help'=>'Elbette. ' . $site_name . ' hakkında istediğiniz soruyu sorabilirsiniz.'),
			'ar' => array('greet'=>'مرحباً! كيف يمكنني مساعدتك اليوم بخصوص ' . $site_name . '؟','how'=>'أنا بخير، شكراً لسؤالك! كيف يمكنني مساعدتك بخصوص ' . $site_name . '؟','thanks'=>'على الرحب والسعة! يمكنك طرح أي سؤال آخر.','ack'=>'حسناً. ماذا تود أن تعرف بعد ذلك؟','bye'=>'شكراً للمحادثة. أتمنى لك يوماً سعيداً!','identity'=>'أنا مساعد الذكاء الاصطناعي الخاص بـ ' . $site_name . '. يمكنني الإجابة عن أسئلة الموقع والخدمات والمنتجات.','help'=>'بالتأكيد. اسألني أي شيء عن ' . $site_name . '.'),
			'hi' => array('greet'=>'नमस्ते! आज मैं ' . $site_name . ' के बारे में आपकी कैसे मदद कर सकता हूँ?','how'=>'मैं ठीक हूँ, धन्यवाद! ' . $site_name . ' के बारे में मैं आपकी कैसे मदद कर सकता हूँ?','thanks'=>'आपका स्वागत है! आप कोई और सवाल पूछ सकते हैं।','ack'=>'ठीक है। अब आप क्या जानना चाहेंगे?','bye'=>'बात करने के लिए धन्यवाद। आपका दिन शुभ हो!','identity'=>'मैं ' . $site_name . ' का AI सहायक हूँ। मैं वेबसाइट, सेवाओं और उत्पादों से जुड़े सवालों का जवाब दे सकता हूँ।','help'=>'ज़रूर। ' . $site_name . ' के बारे में अपना सवाल पूछें।'),
			'zh' => array('greet'=>'你好！今天有什么关于' . $site_name . '的问题需要我帮助？','how'=>'我很好，谢谢！有什么关于' . $site_name . '的问题需要我帮助？','thanks'=>'不客气！如果还有问题，请继续问我。','ack'=>'好的。接下来您想了解什么？','bye'=>'感谢您的咨询，祝您今天愉快！','identity'=>'我是' . $site_name . '的 AI 助手，可以回答网站、服务和产品相关问题。','help'=>'当然可以。请问任何关于' . $site_name . '的问题。'),
			'ja' => array('greet'=>'こんにちは！' . $site_name . 'について、今日はどのようにお手伝いできますか？','how'=>'元気です。ありがとうございます！' . $site_name . 'について何かお手伝いできますか？','thanks'=>'どういたしまして！ほかにも質問があればどうぞ。','ack'=>'承知しました。次に何を知りたいですか？','bye'=>'ご利用ありがとうございました。良い一日を！','identity'=>'私は' . $site_name . 'の AI アシスタントです。ウェブサイト、サービス、商品についてお答えできます。','help'=>'もちろんです。' . $site_name . 'について何でも質問してください。'),
			'ko' => array('greet'=>'안녕하세요! 오늘 ' . $site_name . '에 대해 무엇을 도와드릴까요?','how'=>'잘 지내고 있습니다. 감사합니다! ' . $site_name . '에 대해 무엇을 도와드릴까요?','thanks'=>'천만에요! 다른 질문이 있으면 편하게 물어보세요.','ack'=>'좋습니다. 다음으로 무엇이 궁금하신가요?','bye'=>'대화해 주셔서 감사합니다. 좋은 하루 보내세요!','identity'=>'저는 ' . $site_name . '의 AI 도우미입니다. 웹사이트, 서비스, 제품에 관한 질문에 답할 수 있습니다.','help'=>'물론입니다. ' . $site_name . '에 대해 무엇이든 물어보세요.'),
			'ru' => array('greet'=>'Здравствуйте! Чем я могу помочь вам сегодня по ' . $site_name . '?','how'=>'У меня всё хорошо, спасибо! Чем я могу помочь по ' . $site_name . '?','thanks'=>'Пожалуйста! Если есть ещё вопрос, задавайте.','ack'=>'Хорошо. Что вы хотите узнать дальше?','bye'=>'Спасибо за беседу. Хорошего дня!','identity'=>'Я AI-ассистент ' . $site_name . '. Могу отвечать на вопросы о сайте, услугах и продуктах.','help'=>'Конечно. Задайте любой вопрос о ' . $site_name . '.'),
			'pl' => array('greet'=>'Cześć! Jak mogę dziś pomóc w sprawie ' . $site_name . '?','how'=>'U mnie dobrze, dziękuję! Jak mogę pomóc w sprawie ' . $site_name . '?','thanks'=>'Nie ma za co! Jeśli masz kolejne pytanie, śmiało pytaj.','ack'=>'Dobrze. Co chcesz wiedzieć dalej?','bye'=>'Dziękuję za rozmowę. Miłego dnia!','identity'=>'Jestem asystentem AI ' . $site_name . '. Mogę odpowiadać na pytania o stronę, usługi i produkty.','help'=>'Oczywiście. Zapytaj mnie o cokolwiek związanego z ' . $site_name . '.'),
		);

		if (!isset($texts[$lang])) { $lang = 'en'; }
		return isset($texts[$lang][$kind]) ? $texts[$lang][$kind] : $texts[$lang]['greet'];
	}

	public static function should_offer_lead($message, $history, $s) {
		if (empty($s['lead_enabled'])) { return false; }

		if (!empty($s['smart_lead_intent']) && self::has_lead_intent($message)) {
			return true;
		}

		$threshold = max(1, absint($s['lead_after_messages']));
		$count = self::is_meaningful_message($message) ? 1 : 0;
		foreach ((array) $history as $h) {
			if (!is_array($h) || ($h['role'] ?? '') !== 'user') { continue; }
			$content = isset($h['content']) ? (string) $h['content'] : '';
			if (self::is_meaningful_message($content)) { $count++; }
		}
		return $count >= $threshold;
	}

	public static function has_lead_intent($message) {
		$q = Talkwyn_Text::lower($message);
		if (preg_match('/(no thanks|not now|maybe later|do not contact|don.t contact|nee bedankt|niet nu|pas maintenant|ne me contactez pas|nicht jetzt|nicht kontaktieren|ahora no|no me contacten|non ora|não agora|nao agora|şimdi değil|simdi degil|لا الآن|بعد میں|अभी नहीं|暂时不要|今は結構です|지금은 괜찮아요|не сейчас|nie teraz)/iu', $q)) { return false; }
		return (bool) preg_match('/(contact|call|email|phone|telephone|whatsapp|quote|quotation|price|pricing|cost|book|booking|appointment|consult|consultation|hire|buy|purchase|order|interested|project|proposal|demo|schedule|speak|talk|human|agent|team|support|contact opnemen|bellen|offerte|prijs|afspraak|contacter|appel|devis|prix|rendez-vous|kontakt|anrufen|angebot|preis|termin|contacto|llamar|presupuesto|precio|cita|contatto|chiamare|preventivo|prezzo|appuntamento|contato|ligar|orçamento|orcamento|preço|preco|randevu|fiyat|teklif|iletişim|iletisim|اتصل|تواصل|سعر|حجز|موعد|قیمت|رابطہ|کال|बुक|कीमत|संपर्क|预约|价格|联系|聯絡|予約|価格|連絡|문의|가격|예약|связаться|цена|записаться|kontakt|cena|rezerwacja)/iu', $q);
	}

	public static function has_contact_intent($message) {
		$q = Talkwyn_Text::lower($message);
		return (bool) preg_match('/(contact|email|phone|telephone|call|whatsapp|address|reach|contact opnemen|bellen|adres|contacter|appel|adresse|kontakt|anrufen|adresse|contacto|llamar|dirección|direccion|contatto|chiamare|indirizzo|contato|ligar|endereço|endereco|iletişim|iletisim|ara|adres|اتصل|تواصل|عنوان|رابطہ|کال|پتہ|संपर्क|फोन|पता|联系|地址|電話|連絡|住所|연락|전화|주소|связаться|телефон|адрес|kontakt|telefon|adres)/iu', $q);
	}

	public static function is_meaningful_message($message) {
		$q = trim(Talkwyn_Text::lower($message));
		if ($q === '' || Talkwyn_Text::len($q) < 2) { return false; }
		if (strpos(self::intent($q), 'social_') === 0) { return false; }
		return true;
	}

	public static function should_use_local_answer($message, $reply, $chunks) {
		if (!self::has_contact_intent($message)) { return false; }
		if (!preg_match('/not enough information|do not have enough|don.t have enough|not sure|cannot answer|can.t answer|unable to answer|je ne sais pas|pas assez|nicht genug|weiß nicht|weiss nicht|no tengo suficiente|no estoy seguro|non ho abbastanza|não tenho informação|nao tenho informacao|لا توجد معلومات|مجھے معلوم نہیں|जानकारी नहीं|不确定|情報がありません|정보가 없습니다|не знаю|недостаточно информации/iu', (string) $reply)) { return false; }
		foreach ($chunks as $chunk) {
			$hay = Talkwyn_Text::lower(($chunk['title'] ?? '') . ' ' . ($chunk['source_url'] ?? '') . ' ' . ($chunk['chunk_text'] ?? ''));
			if (strpos($hay, 'contact') !== false || strpos($hay, 'mailto:') !== false || strpos($hay, 'tel:') !== false || strpos($hay, 'whatsapp') !== false || strpos($hay, '@') !== false) {
				return true;
			}
		}
		return false;
	}

	public static function local_answer($message, $chunks, $fallback, $site_name = 'this website', $page_lang = '') {
		$q = trim(Talkwyn_Text::lower($message));

		if (preg_match('/^(hi|hello|hey|hiya|good morning|good afternoon|good evening)[!. ]*$/i', $q)) {
			return 'Hi! I am the AI assistant for ' . $site_name . '. Ask me anything about the website, services, products, or how the team can help.';
		}
		if (preg_match('/^(thanks|thank you|thankyou|great thanks|okay thanks|ok thanks)[!. ]*$/i', $q)) {
			return 'You are welcome. Is there anything else you would like to know?';
		}
		if (preg_match('/\b(who are you|what are you|are you a bot|are you ai)\b/i', $q)) {
			return 'I am the AI assistant for ' . $site_name . '. I can answer questions using information from this website and help you connect with the team when needed.';
		}
		if (preg_match('/^(help|can you help|what can you do)[?!. ]*$/i', $q)) {
			return 'Of course. Ask me a question about ' . $site_name . ' and I will use the website information to help you. I can also help you reach the team if you need a person.';
		}

		$all = '';
		$contact_source = '';
		foreach ($chunks as $chunk) {
			$text = (string) ($chunk['chunk_text'] ?? '');
			$all .= "\n" . $text;
			$hay = Talkwyn_Text::lower(($chunk['title'] ?? '') . ' ' . ($chunk['source_url'] ?? '') . ' ' . $text);
			if ($contact_source === '' && (strpos($hay, 'contact') !== false || strpos($hay, 'mailto:') !== false || strpos($hay, 'tel:') !== false || strpos($hay, 'whatsapp') !== false)) {
				$contact_source = (string) ($chunk['source_url'] ?? '');
			}
		}

		if (self::has_contact_intent($q)) {
			$bits = array();
			if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $all, $m)) {
				$bits[] = 'Email: ' . $m[0];
			}
			if (preg_match('/(?:\+?\d[\d\s().\-]{7,}\d)/', $all, $m)) {
				$phone = trim(preg_replace('/\s+/', ' ', $m[0]));
				if (strlen(preg_replace('/\D/', '', $phone)) >= 8) { $bits[] = 'Phone: ' . $phone; }
			}
			if ($bits) {
				return "You can contact the team using these details from the website:\n" . implode("\n", array_unique($bits));
			}
			if ($contact_source !== '') {
				return 'You can contact the team through the Contact page. I can also ask the team to follow up with you here.';
			}
		}

		// If the external AI is unavailable, do not pretend a random website sentence is
		// a complete answer. Build an honest, useful mini-summary from the strongest sources.
		$items = array();
		$seen_titles = array();
		foreach ($chunks as $chunk) {
			$title = trim(wp_strip_all_tags((string) ($chunk['title'] ?? '')));
			$text = trim(wp_strip_all_tags((string) ($chunk['chunk_text'] ?? '')));
			if ($title === '' || isset($seen_titles[Talkwyn_Text::lower($title)])) { continue; }
			$sentences = preg_split('/(?<=[.!?])\s+|\n+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
			$snippet = '';
			foreach ($sentences as $sentence) {
				$sentence = trim($sentence);
				if (Talkwyn_Text::len($sentence) >= 20) { $snippet = wp_trim_words($sentence, 24, '...'); break; }
			}
			if ($snippet === '') { continue; }
			$seen_titles[Talkwyn_Text::lower($title)] = true;
			$items[] = '• ' . $title . ': ' . $snippet;
			if (count($items) >= 4) { break; }
		}
		if ($items) {
			if (self::is_broad_catalog_question($message)) {
				return "Here are the most relevant areas I found on the website:
" . implode("
", $items) . "

If you tell me what you are looking for, I can narrow this down.";
			}
			return "I found these relevant pages on the website:
" . implode("
", $items);
		}
		return $fallback;
	}


	/**
	 * Whether a reply says the answer was not found.
	 *
	 * @param string $reply Reply.
	 * @return bool
	 */
	public static function looks_unanswered( $reply ) {
		return (bool) preg_match( '/not enough information|do not have enough|don.t have enough|not sure|could not find|couldn.t find|cannot answer|can.t answer|unable to answer|no information|je ne sais pas|pas assez|nicht genug|weiß nicht|no tengo suficiente|no estoy seguro|non ho abbastanza|não tenho informação|لا توجد معلومات|مجھے معلوم نہیں|जानकारी नहीं|不确定|情報がありません|정보가 없습니다|не знаю|недостаточно информации/iu', (string) $reply );
	}
}
