/* CLICK MAT KAR. — game content packs. Shared by the game engines and the result page.
 * Money is stored in "Rs base units" (1 USD = 100 base units, see main.js).
 * Shop item: [id, emoji, name, category, price, quip, silly]
 * Quiz option: [text, points, reaction, money?]
 */
window.CMK_GAMES = {

	/* ------------------------------------------------------------------ */
	'shop-like-youre-rich': {
		engine: 'shop',
		budget: 100000000,
		intro: 'Spend it on things nobody needs, then see what your Financial IQ says about you.',
		hint: 'Pick something. Anything. Preferably something stupid.',
		categories: [['all', 'Everything', '🌀'], ['fashion', 'Drip', '👟'], ['tech', 'Gadgets', '📱'], ['rides', 'Rides', '🏎️'], ['home', 'Home', '🏰'], ['weird', 'Why though', '🦒']],
		items: [
			['sneakers', '👟', 'Limited Sneakers', 'fashion', 280000, 'Too clean to actually walk in.', 0],
			['tiny-bag', '👜', 'Tiny Designer Bag', 'fashion', 620000, 'Holds one (1) mint.', 1],
			['indoor-shades', '🕶️', 'Indoor Sunglasses', 'fashion', 95000, 'For dramatic entries only.', 1],
			['jacket', '🧥', 'Jacket Worn Once', 'fashion', 450000, 'For the photo. Then never.', 0],
			['watch', '⌚', 'Watch That Tells Time Worse', 'fashion', 1800000, 'Your phone is more accurate.', 1],
			['phone', '📱', 'Phone With 7 Cameras', 'tech', 450000, 'All seven for selfies.', 0],
			['laptop', '💻', 'Laptop For Watching Reels', 'tech', 650000, 'Strictly productivity. Strictly.', 0],
			['headphones', '🎧', 'Headphones To Ignore Family', 'tech', 120000, 'Noise cancelling. Rishta cancelling.', 0],
			['robot', '🤖', 'Robot Butler (Judgemental)', 'tech', 8500000, 'It sighs when you order biryani at 3am.', 1],
			['supercar', '🏎️', 'Supercar, No Parking', 'rides', 32000000, 'Will live in the driveway. Forever.', 0],
			['scooter', '🛵', 'Gold-Plated Scooter', 'rides', 900000, 'Same traffic. More shine.', 1],
			['yacht', '🛥️', 'Yacht', 'rides', 42000000, 'You get seasick. Who cares.', 0],
			['helicopter', '🚁', 'Helicopter To Skip Traffic', 'rides', 65000000, 'Landing permission not included.', 0],
			['castle', '🏰', 'Small Castle', 'home', 48000000, 'Comes with one (1) ghost.', 0],
			['sofa', '🛋️', 'Sofa Nobody Sits On', 'home', 750000, 'Plastic cover included, obviously.', 1],
			['gold-tap', '🚿', 'Solid Gold Tap', 'home', 380000, 'Water tastes exactly the same.', 1],
			['portrait', '🖼️', 'Giant Painting Of Yourself', 'home', 2200000, 'Hang it facing the door.', 1],
			['giraffe', '🦒', 'Emotional Support Giraffe', 'weird', 4500000, 'Needs a taller house. Buy one.', 1],
			['camel', '🐪', 'Camel For Wedding Entry', 'weird', 1200000, 'Baraat will never be the same.', 1],
			['butter-knife', '🧈', 'Gold Butter Knife', 'weird', 95000, 'Butters exactly like a normal knife.', 1],
			['judging-chair', '🪑', 'Chair That Judges You', 'weird', 310000, 'Ergonomic. Emotionally not.', 1],
			['star', '🌟', 'A Star Named After You', 'weird', 50000, 'You will never see it. Ever.', 1],
			['banana', '🍌', 'Banana Taped To A Wall', 'weird', 6500000, 'Art. Apparently.', 1],
			['island', '🏝️', 'Private Island (Small)', 'weird', 85000000, 'Mostly sand. Some regret.', 0]
		],
		checkout: 'Checkout (fake)',
		result: {
			label: 'I spent', big: 'money', sub: "on things I definitely don't need.",
			scoreLabel: 'Financial IQ', share: 'I wasted {big} on things I don\'t need. Financial IQ: {q}/100 🤡 Beat me:',
			tiers: [
				[15, 'Certified financial disaster.', 'Your money did not leave. It escaped.'],
				[35, 'Retail therapy champion.', 'Therapy would have been cheaper. And you still need it.'],
				[60, 'Chaotic, but functional.', 'Some bad decisions, some okay ones. Mostly bad.'],
				[100, 'Suspiciously responsible.', 'You had all that money and bought... sensible things? Phir se? Fine.']
			]
		}
	},

	/* ------------------------------------------------------------------ */
	'dream-wedding': {
		engine: 'shop',
		budget: 50000000,
		intro: 'Seven functions, one budget, zero restraint. Plan the shaadi everyone will talk about (for the wrong reasons).',
		hint: 'Phuppo is watching. Choose wisely. Or don\'t.',
		categories: [['all', 'Everything', '💍'], ['venue', 'Venue', '🏰'], ['entry', 'Entry', '🐎'], ['outfit', 'Outfits', '👗'], ['food', 'Food', '🍛'], ['chaos', 'Pure chaos', '🎆']],
		items: [
			['farmhouse', '🏡', 'Farmhouse With 3 Stages', 'venue', 4500000, 'One for each rishtedaar faction.', 0],
			['castle-venue', '🏰', 'Rented European Castle', 'venue', 18000000, 'Half the guests need visas.', 1],
			['yacht-mehndi', '🛥️', 'Mehndi On A Yacht', 'venue', 9000000, 'Henna + seasickness. Iconic.', 1],
			['mirror-hall', '🪞', 'Hall Made Of Mirrors', 'venue', 3200000, 'Infinite uncles. Infinite selfies.', 1],
			['horse', '🐎', 'Classic Horse Entry', 'entry', 350000, 'The horse is not happy about the dhol.', 0],
			['helicopter-entry', '🚁', 'Helicopter Entry', 'entry', 6500000, 'Lands on the buffet. Probably.', 1],
			['elephant', '🐘', 'Elephant With Fairy Lights', 'entry', 2400000, 'Main character energy.', 1],
			['dhol', '🥁', '500 Dhol Players', 'entry', 1500000, 'Neighbours have moved out.', 1],
			['lehenga', '👗', 'Lehenga Heavier Than You', 'outfit', 2800000, 'Requires two cousins to walk.', 0],
			['sherwani', '🤵', 'Gold Thread Sherwani', 'outfit', 1600000, 'Shines brighter than the future.', 0],
			['outfit-changes', '👠', '3 Outfit Changes Per Function', 'outfit', 3600000, 'Seven functions. Do the math.', 1],
			['matching-family', '🧥', 'Matching Outfits For 80 Cousins', 'outfit', 4000000, 'Colour code: "dusty pista".', 1],
			['biryani', '🍛', 'Unlimited Biryani', 'food', 1200000, 'The only thing guests remember.', 0],
			['47-dishes', '🍽️', 'Buffet With 47 Dishes', 'food', 3800000, 'Uncles still complain about salt.', 0],
			['cake', '🎂', '7-Tier Cake', 'food', 900000, 'Nobody eats cake at desi weddings.', 1],
			['chai-bar', '☕', 'Live Chai Bar With DJ', 'food', 450000, 'Doodh patti drops at midnight.', 0],
			['fireworks', '🎆', 'Fireworks Until 4am', 'chaos', 2200000, 'Police visit included.', 1],
			['drone-show', '🛸', 'Drone Show Spelling Your Names', 'chaos', 3000000, 'Spelling mistake guaranteed.', 1],
			['singer', '🎤', 'Famous Singer For One Song', 'chaos', 8000000, 'Sings the one song you hate.', 0],
			['bouncers', '🕴️', 'Bouncers For Rishtedaar Control', 'chaos', 600000, 'Specifically for that one phuppo.', 1],
			['ice-sculpture', '🧊', 'Ice Sculpture Of The Couple', 'chaos', 750000, 'Melts by the rukhsati. Symbolic.', 1],
			['invites', '💌', 'Gold-Plated Invitations', 'chaos', 1100000, 'Each one weighs 2 kg.', 1]
		],
		checkout: 'Lock the wedding',
		result: {
			label: 'Our wedding cost', big: 'money', sub: 'and the rishtedaar still complained.',
			scoreLabel: 'Shaadi sanity', share: 'My dream wedding cost {big}. Shaadi sanity: {q}/100 💍 Plan a worse one:',
			tiers: [
				[15, 'Seven-day shaadi disaster.', 'Your wedding will be discussed at every family gathering until 2090.'],
				[35, 'Full Bollywood mode.', 'Helicopters, dhol and zero chill. The neighbours have filed a complaint.'],
				[60, 'Loud, but manageable.', 'Big shaadi energy with a few sensible choices sneaking in.'],
				[100, 'Simple nikah, honestly.', 'Suspiciously reasonable. Phuppo is disappointed.']
			]
		}
	},

	/* ------------------------------------------------------------------ */
	'spend-1-billion': {
		engine: 'shop',
		currency: 'usd',
		budget: 100000000000,
		qty: true,
		invert: true,
		intro: 'One billion dollars. Your only job: spend every last cent. It is harder than it sounds. Not really.',
		hint: 'Tip: you can buy more than one. Nobody is stopping you.',
		categories: [['all', 'Everything', '💸'], ['big', 'Big toys', '🛩️'], ['assets', 'Assets', '🏙️'], ['silly', 'Silly', '🍔'], ['good', 'Do good', '💚']],
		items: [
			['jet', '🛩️', 'Private Jet', 'big', 6500000000, 'For your 40-minute flights.', 0],
			['superyacht', '🛳️', 'Superyacht With Helipad', 'big', 30000000000, 'And a smaller yacht inside it.', 1],
			['rocket', '🚀', 'Rocket Launch To Nowhere', 'big', 6000000000, 'Eleven minutes in space. Worth it?', 1],
			['f1', '🏎️', 'Formula 1 Team', 'big', 50000000000, 'You will finish 9th. Every year.', 0],
			['football-club', '⚽', 'Football Club', 'assets', 40000000000, 'Fans will still boo you.', 0],
			['skyscraper', '🏙️', 'Skyscraper With Your Name', 'assets', 25000000000, 'In very large letters.', 1],
			['mall', '🏬', 'Entire Shopping Mall', 'assets', 18000000000, 'Free parking for you only.', 0],
			['island-b', '🏝️', 'Private Island (Large)', 'assets', 9000000000, 'Comes with seagulls. Many seagulls.', 0],
			['burgers', '🍔', '1 Million Burgers', 'silly', 500000000, 'Feeds a city. Or you, for a week.', 1],
			['gold-bath', '🛁', 'Solid Gold Bathtub', 'silly', 120000000, 'Freezing cold. Very shiny.', 1],
			['dino', '🦖', 'T-Rex Skeleton', 'silly', 3200000000, 'For the living room.', 1],
			['diamond-phone', '💎', 'Diamond Phone Case', 'silly', 15000000, 'Drops exactly like a normal phone.', 1],
			['concert', '🎤', 'Private Concert, Every Week', 'silly', 400000000, 'Same singer. Every. Single. Week.', 1],
			['hospital', '🏥', 'Build A Hospital', 'good', 15000000000, 'Okay this one is actually great.', 0],
			['schools', '🏫', 'Fund 100 Schools', 'good', 5000000000, 'Look at you, being a good person.', 0],
			['trees', '🌳', 'Plant 10 Million Trees', 'good', 2000000000, 'The planet says shukriya.', 0]
		],
		checkout: 'Done spending',
		result: {
			label: 'I spent', big: 'money', sub: 'of a billion dollars. In minutes.',
			scoreLabel: 'Billionaire efficiency', share: 'I spent {big} of my $1 billion. Billionaire efficiency: {q}/100 💸 Beat me:',
			tiers: [
				[30, 'Accidentally still rich.', 'You had a billion dollars and could not spend it. That is a personality.'],
				[70, 'Casual billionaire.', 'Solid effort. Your accountant is crying in a good way.'],
				[95, 'Professional spender.', 'Almost empty. Almost. Your bank sends its regards.'],
				[100, 'Zero left. Legend.', 'Every last cent, gone. Generational chaos achieved.']
			]
		}
	},

	/* ------------------------------------------------------------------ */
	'dream-life': {
		engine: 'shop',
		budget: 250000000,
		invert: true,
		intro: 'Build your dream life: house, gaadi, travel, everything. Then find out how delusional it is.',
		hint: 'Manifest it. Then add to cart.',
		categories: [['all', 'Everything', '🏝️'], ['house', 'Ghar', '🏡'], ['car', 'Gaadi', '🚗'], ['travel', 'Travel', '✈️'], ['life', 'Daily life', '☕']],
		items: [
			['villa', '🏡', 'Villa With A Pool', 'house', 60000000, 'Pool is for photos only.', 0],
			['penthouse', '🌆', 'Penthouse With City View', 'house', 45000000, 'You will still watch reels.', 0],
			['farmhouse-life', '🌾', 'Farmhouse For "Peace"', 'house', 30000000, 'Visited twice a year.', 1],
			['home-cinema', '🍿', 'Home Cinema', 'house', 4500000, 'For rewatching the same show.', 0],
			['suv', '🚙', 'Big SUV', 'car', 18000000, 'Cannot fit in any parking.', 0],
			['vintage', '🚘', 'Vintage Car (Doesn\'t Start)', 'car', 9000000, 'Purely decorative.', 1],
			['driver', '🧑‍✈️', 'Personal Driver', 'car', 2400000, 'Mostly drives you to chai.', 0],
			['bike', '🏍️', 'Superbike', 'car', 6000000, 'Ammi has said no. Firmly.', 1],
			['world-tour', '🌍', 'One-Year World Tour', 'travel', 25000000, 'Posting stories every 4 minutes.', 0],
			['first-class', '💺', 'Only Flying First Class', 'travel', 12000000, 'Complaining about the champagne.', 1],
			['maldives', '🏝️', 'Maldives Every Winter', 'travel', 8000000, 'Same photo. Different year.', 0],
			['space', '🛰️', 'Space Tourism Seat', 'travel', 45000000, 'Eleven minutes. Lifetime story.', 1],
			['chef', '👨‍🍳', 'Private Chef', 'life', 3600000, 'You still order fast food.', 0],
			['trainer', '🏋️', 'Personal Trainer', 'life', 1200000, 'Ghosted after week two.', 1],
			['wake-late', '😴', 'Waking Up At 11am Forever', 'life', 500000, 'Priceless, honestly.', 0],
			['pets', '🐕', 'Three Golden Retrievers', 'life', 900000, 'The real dream.', 0],
			['tiger', '🐅', 'Pet Tiger (Bad Idea)', 'life', 7000000, 'Neighbours will move. Again.', 1]
		],
		checkout: 'Live this life',
		result: {
			label: 'My dream life costs', big: 'money', sub: 'and I still wake up at 11am.',
			scoreLabel: 'Delusion level', share: 'My dream life costs {big}. Delusion level: {q}/100 🏝️ Build yours:',
			tiers: [
				[25, 'Humble dreamer.', 'A nice house, some chai, a dog. Honestly? Respect.'],
				[50, 'Main character energy.', 'Big plans, bigger posts. Your stories will be unbearable.'],
				[75, 'Certified delusional.', 'Space trips and pet tigers. Your bank balance has questions.'],
				[100, 'Lives in a parallel universe.', 'Reality has left the chat. Manifesting harder will not help.']
			]
		}
	},

	/* ------------------------------------------------------------------ */
	'bad-decisions': {
		engine: 'quiz',
		intro: 'Seven situations. Every option is questionable. Your choices add up to a chaos level.',
		cta: 'Start making bad choices',
		questions: [
			{ e: '⏰', q: 'Your alarm rings for an important exam. You…', o: [
				['Get up. Like an adult.', 0, 'Boring. But fine.'],
				['Snooze 7 times, then panic.', 2, 'Classic.'],
				['Decide the exam is a social construct.', 4, 'Philosophy major energy.']
			] },
			{ e: '💸', q: 'Salary just arrived. First move?', o: [
				['Save 30% like a responsible person.', 0, 'Who even are you?'],
				['Order food from three apps at once.', 3, 'A buffet of regret.'],
				['Buy a ring light for a career you do not have.', 4, 'Influencer arc begins.']
			] },
			{ e: '📱', q: 'Your ex texts "hey" at 2am.', o: [
				['Ignore. Sleep. Grow.', 0, 'Therapy is working.'],
				['Reply "hey" in 0.4 seconds.', 3, 'Mana kiya tha.'],
				['Call them. On video. Crying.', 5, 'Absolute cinema.']
			] },
			{ e: '🍕', q: 'Friends ask: one slice or the whole pizza?', o: [
				['One slice, thanks.', 0, 'Restraint? Here?'],
				['Whole pizza, no sharing.', 2, 'Respectable.'],
				['Whole pizza and their pizza.', 4, 'Villain origin story.']
			] },
			{ e: '🛒', q: 'An ad shows you a gadget you will never use.', o: [
				['Scroll past.', 0, 'Strong willpower. Suspicious.'],
				['Add to cart "just to see".', 2, 'We all know how this ends.'],
				['Buy two. One for backup.', 4, 'Backup for what?!']
			] },
			{ e: '🎤', q: 'Shaadi DJ asks for volunteers to dance.', o: [
				['Hide behind the biryani.', 0, 'Safe. Hungry.'],
				['Dance politely to one song.', 1, 'Good guest.'],
				['Take the mic and perform an entire item number.', 5, 'Legend. Embarrassing legend.']
			] },
			{ e: '🧠', q: 'Final question: why are you still here?', o: [
				['I was just leaving.', 0, 'Sure you were.'],
				['One more game, then sleep.', 2, 'That is what everybody says.'],
				['This IS my personality now.', 4, 'Welcome home.']
			] }
		],
		result: {
			label: 'My chaos level', big: 'score', sub: 'out of 100. Based on real bad choices.',
			scoreLabel: 'Chaos level', share: 'My chaos level is {q}/100 🎮 Make worse decisions than me:',
			tiers: [
				[20, 'Annoyingly responsible.', 'You made zero bad decisions. Why are you even here?'],
				[45, 'Mildly chaotic.', 'A few questionable choices. Your mom still trusts you. For now.'],
				[75, 'Walking plot twist.', 'Your life is a sitcom and you are the funny side character.'],
				[100, 'Agent of pure chaos.', 'Every decision was the wrong one. Somehow, impressive.']
			]
		}
	},

	/* ------------------------------------------------------------------ */
	'red-flag-check': {
		engine: 'quiz',
		intro: 'Eight honest questions. Count your red flags. Kuch sach kadwa hota hai.',
		cta: 'Check my red flags',
		questions: [
			{ e: '👀', q: 'You check their "last seen" more than your own messages.', o: [['Never', 0, 'Healthy. Rare.'], ['Sometimes', 1, 'Hmm.'], ['Every 4 minutes', 2, '🚩🚩']] },
			{ e: '📸', q: 'You post a story just so one person sees it.', o: [['No', 0, 'Pure soul.'], ['Maybe once', 1, 'We all have.'], ['That is the only reason I post', 2, 'Targeted content.']] },
			{ e: '🎧', q: 'You say "I\'m fine" when you are clearly not.', o: [['No, I communicate', 0, 'Therapist approved.'], ['Sometimes', 1, 'Classic.'], ['Always, it is my brand', 2, '🚩 Brand identity.']] },
			{ e: '🍟', q: 'You say "I\'m not hungry" then eat their fries.', o: [['Never', 0, 'Respect the fries.'], ['Once or twice', 1, 'Fry thief.'], ['It is a lifestyle', 2, 'Criminal behaviour.']] },
			{ e: '⌛', q: 'You reply "ok" and expect them to understand everything.', o: [['No', 0, 'Clear communicator.'], ['Sometimes', 1, 'Cryptic.'], ['ok.', 2, '🚩 That full stop.']] },
			{ e: '🕵️', q: 'You have stalked someone\'s cousin\'s friend\'s profile.', o: [['Never', 0, 'Liar. But okay.'], ['Accidentally', 1, '"Accidentally".'], ['I have a whole investigation board', 2, 'FBI is hiring.']] },
			{ e: '💬', q: 'You screenshot chats to send to the group for analysis.', o: [['Never', 0, 'Private person.'], ['Only important ones', 1, 'Committee meeting.'], ['Group knows everything', 2, 'Live broadcast.']] },
			{ e: '🙃', q: 'You bring up a fight from 2019 during a new fight.', o: [['No', 0, 'Moving on. Mature.'], ['Only if relevant', 1, 'It is always relevant.'], ['I have an archive', 2, 'Historian of pain.']] }
		],
		result: {
			label: 'I have', big: 'count', countWord: 'red flags', sub: 'and honestly, I knew.',
			scoreLabel: 'Red flag level', share: 'I have {big} 🚩 Red flag level: {q}/100. Check yours:',
			tiers: [
				[20, 'Green flag (suspicious).', 'Almost no red flags. Either you are a saint or you lied.'],
				[45, 'Beige flag.', 'A few quirks. Nothing a good conversation can\'t fix.'],
				[75, 'Red flag collector.', 'Your friends have a group chat about you. Without you.'],
				[100, 'Walking red flag parade.', 'You are the warning label. Kuch sach kadwa hota hai.']
			]
		}
	},

	/* ------------------------------------------------------------------ */
	'whats-your-price': {
		engine: 'quiz',
		money: true,
		intro: 'Would you do it? For how much? Put a price on things you should never do, then compare with friends.',
		cta: 'Name my price',
		questions: [
			{ e: '🧅', q: 'Eat a raw onion like an apple, on camera.', o: [['For {m}', 3, 'Cheap and crunchy.', 500], ['For {m}', 2, 'Reasonable.', 50000], ['For {m}', 1, 'Premium onion.', 500000], ['Never', 0, 'Dignity intact.', 0]] },
			{ e: '📵', q: 'No phone for 30 days.', o: [['For {m}', 3, 'Bold.', 10000], ['For {m}', 2, 'Detox, paid.', 100000], ['For {m}', 1, 'Big price, big withdrawal.', 1000000], ['Never', 0, 'Phone is family.', 0]] },
			{ e: '🎤', q: 'Sing at your cousin\'s wedding. Solo. Off-key.', o: [['For free, honestly', 3, 'Born for this.', 0], ['For {m}', 2, 'Professional.', 20000], ['For {m}', 1, 'Hazard pay.', 200000], ['Never', 0, 'The family thanks you.', 0]] },
			{ e: '🍛', q: 'Only eat plain boiled rice for a week.', o: [['For {m}', 3, 'Cheap date.', 5000], ['For {m}', 2, 'Sad, but fair.', 50000], ['For {m}', 1, 'Rice millionaire.', 500000], ['Never', 0, 'Masala is life.', 0]] },
			{ e: '📢', q: 'Read your old school diary out loud to the family group.', o: [['For {m}', 3, 'Shameless. Love it.', 1000], ['For {m}', 2, 'Emotional damages.', 100000], ['For {m}', 1, 'That diary has secrets.', 10000000], ['Never', 0, 'Burn it instead.', 0]] },
			{ e: '💇', q: 'Let your best friend choose your next haircut.', o: [['For {m}', 3, 'Trust issues: none.', 2000], ['For {m}', 2, 'Risky.', 25000], ['For {m}', 1, 'Wig money included.', 250000], ['Never', 0, 'Smart.', 0]] },
			{ e: '🐓', q: 'Wake up at 5am every day for a month.', o: [['For {m}', 3, 'Morning person in disguise.', 15000], ['For {m}', 2, 'Coffee budget covered.', 150000], ['For {m}', 1, 'Rooster salary.', 1500000], ['Never', 0, 'Sleep is sacred.', 0]] }
		],
		result: {
			label: 'My total price', big: 'money', sub: 'to lose every shred of dignity.',
			scoreLabel: 'Bikne ka level', share: 'My price for total embarrassment: {big} 🏷️ Bikne ka level {q}/100. What\'s yours?',
			tiers: [
				[20, 'Not for sale.', 'You said "never" to almost everything. Dignity: priceless.'],
				[45, 'Expensive, but available.', 'You have a price. It is just high. Negotiable on weekends.'],
				[75, 'Discount dignity.', 'For the right amount, you will do almost anything. Respect the hustle.'],
				[100, 'Clearance sale.', 'One raw onion is all it takes. Kitne mein bikoge? Itne mein.']
			]
		}
	}
};
