/* CLICK MAT KAR. — game content packs. Shared by the game engines and the result page.
 * Money is stored in "Rs base units" (1 USD = 100 base units, see main.js).
 * Shop item: [id, emoji, name, category, price, quip, silly]
 * Quiz option: [text, points, reaction, money?]
 */
window.CMK_GAMES = {

	/* ------------------------------------------------------------------ */
	'shop-like-youre-rich': {
		engine: 'shop',
		budgets: { usd: 1000000, pkr: 250000000, inr: 100000000 },
		intro: 'Spend it on things nobody needs, then see what your Financial IQ says about you.',
		hint: 'Pick something. Anything. Preferably something stupid.',
		categories: [['all', 'Everything', '🌀'], ['fashion', 'Drip', '👟'], ['tech', 'Gadgets', '📱'], ['rides', 'Rides', '🏎️'], ['home', 'Home', '🏰'], ['weird', 'Why though', '🦒']],
		items: [
			{ id: 'adibas-sneakers', e: '👟', brand: 'Adibas', name: '"Limited" Sneakers', cat: 'fashion', usd: 1200, quip: 'Three stripes. Four if you count the crease.', silly: 0, tag: 'Bestseller' },
			{ id: 'gucchi-bag', e: '👜', brand: 'Gucchi', name: 'Mini Bag (Holds 1 Mint)', cat: 'fashion', usd: 3200, quip: 'Last copy, not first copy.', silly: 1, tag: 'Influencer pick' },
			{ id: 'hermes-burkin', e: '👛', brand: 'Hermes Burkin', name: 'Burkin 25', cat: 'fashion', usd: 30000, quip: 'Waitlist: 6 years. They choose you.', silly: 0, tag: 'Only 1 left' },
			{ id: 'rolax-watch', e: '⌚', brand: 'Rolax', name: 'Daytona "Relax" Edition', cat: 'fashion', usd: 45000, quip: 'Tells time. Slowly. Very relaxed.', silly: 0 },
			{ id: 'shanel-shades', e: '🕶️', brand: 'Shanel', name: 'Indoor Sunglasses', cat: 'fashion', usd: 650, quip: 'For dramatic entries at the chai dhaba.', silly: 1 },
			{ id: 'balajiaga-jacket', e: '🧥', brand: 'Bala-ji-aga', name: 'Trash Bag Jacket', cat: 'fashion', usd: 1800, quip: 'Looks like a garbage bag. Is a garbage bag.', silly: 1, tag: 'Ammi disapproved' },
			{ id: 'dyor-lipstick', e: '💄', brand: 'Dyor', name: 'Every Shade Of Red', cat: 'fashion', usd: 900, quip: '47 reds. All look the same.', silly: 0 },
			{ id: 'ifone', e: '📱', brand: 'iFone', name: '17 Pro Max Ultra Plus', cat: 'tech', usd: 1600, quip: 'Same as last year. New colour.', silly: 0, tag: 'Bestseller' },
			{ id: 'macbuk', e: '💻', brand: 'MacBuk', name: 'Pro Max Chacha Edition', cat: 'tech', usd: 4000, quip: 'For watching reels. Professionally.', silly: 0 },
			{ id: 'hairpods', e: '🎧', brand: 'HairPods', name: 'Max (Rishtedaar Cancelling)', cat: 'tech', usd: 550, quip: 'Cancels noise. And family.', silly: 0 },
			{ id: 'playstasion', e: '🎮', brand: 'PlayStasion', name: '6 (Unreleased, Somehow)', cat: 'tech', usd: 700, quip: 'You will play it for 2 days.', silly: 1, tag: 'Pre-order' },
			{ id: 'teslaa-robot', e: '🤖', brand: 'Teslaa', name: 'Optimist Robot Butler', cat: 'tech', usd: 30000, quip: 'Sighs at your 3am biryani orders.', silly: 1, tag: 'Pre-order' },
			{ id: 'dysun-dryer', e: '💨', brand: 'Dysun', name: 'Hair Dryer (Costs More Than Hair)', cat: 'tech', usd: 600, quip: 'Blows air. Expensively.', silly: 1 },
			{ id: 'lambo-ghanta', e: '🏎️', brand: 'Lambo-Ghanta', name: 'Huracan, No Parking Space', cat: 'rides', usd: 260000, quip: 'Will live in the driveway. Forever.', silly: 0, tag: 'Ammi disapproved' },
			{ id: 'furrari', e: '🚗', brand: 'Furrari', name: 'Red Car That Goes "Vroom"', cat: 'rides', usd: 330000, quip: 'Speed breakers are its natural enemy.', silly: 0 },
			{ id: 'rolls-rice', e: '🚘', brand: 'Rolls Rice', name: 'Phantom With Biryani Boot', cat: 'rides', usd: 460000, quip: 'Starlight roof. Biryani boot.', silly: 1, tag: 'Only 1 left' },
			{ id: 'honda-gold-70', e: '🛵', brand: 'Honda Gold 70', name: '24k Gold-Plated Bike', cat: 'rides', usd: 15000, quip: 'Same traffic. More shine.', silly: 1 },
			{ id: 'sunseeker-yacht', e: '🛥️', brand: 'Sunsinker', name: 'Small Yacht', cat: 'rides', usd: 650000, quip: 'You get seasick. Who cares.', silly: 0 },
			{ id: 'farmhouse-3-gates', e: '🏡', brand: 'Farm-house', name: 'With 3 Gates And 0 Farming', cat: 'home', usd: 300000, quip: 'One gate per rishtedaar faction.', silly: 0 },
			{ id: 'ikeya-sofa', e: '🛋️', brand: 'IKEYA', name: 'Sofa With Plastic Cover (Forever)', cat: 'home', usd: 3500, quip: 'Nobody is allowed to sit on it.', silly: 1 },
			{ id: 'gold-tap', e: '🚿', brand: 'Solid Gold', name: 'Bathroom Tap', cat: 'home', usd: 2500, quip: 'Water tastes exactly the same.', silly: 1 },
			{ id: 'self-portrait', e: '🖼️', brand: 'Oil Painting', name: 'Of Yourself, Facing The Door', cat: 'home', usd: 8000, quip: 'Guests must salute it.', silly: 1 },
			{ id: 'jhoomar', e: '💡', brand: 'Crystal Jhoomar', name: 'For The Drawing Room Nobody Uses', cat: 'home', usd: 12000, quip: 'Opened twice a year. For guests.', silly: 1, tag: 'Influencer pick' },
			{ id: 'giraffe', e: '🦒', brand: 'Zoo-ish', name: 'Emotional Support Giraffe', cat: 'weird', usd: 25000, quip: 'Needs a taller house. Buy one.', silly: 1, tag: 'Influencer pick' },
			{ id: 'baraat-camel', e: '🐪', brand: 'Desi Classic', name: 'Camel For Baraat Entry', cat: 'weird', usd: 4000, quip: 'The baraat will never be the same.', silly: 1 },
			{ id: 'banana-art', e: '🍌', brand: 'Art Basel-ish', name: 'Banana Taped To A Wall', cat: 'weird', usd: 120000, quip: 'Art. Apparently.', silly: 1 },
			{ id: 'gold-butter-knife', e: '🧈', brand: 'Solid Gold', name: 'Butter Knife', cat: 'weird', usd: 400, quip: 'Butters exactly like a normal knife.', silly: 1 },
			{ id: 'star-named', e: '🌟', brand: 'Galaxy Registry', name: 'A Star Named After You', cat: 'weird', usd: 60, quip: 'You will never, ever see it.', silly: 1, tag: 'Bestseller' },
			{ id: 'bored-bandar', e: '🐒', brand: 'Bored Bandar', name: 'NFT Monkey (Worth $0 Now)', cat: 'weird', usd: 90000, quip: 'Right-click, save. Free.', silly: 1 }
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
		budgets: { usd: 250000, pkr: 70000000, inr: 20000000 },
		intro: 'Seven functions, one budget, zero restraint. Plan the shaadi everyone will talk about (for the wrong reasons).',
		hint: 'Phuppo is watching. Choose wisely. Or don\'t.',
		categories: [['all', 'Everything', '💍'], ['venue', 'Venue', '🏰'], ['entry', 'Entry', '🐎'], ['outfit', 'Outfits', '👗'], ['food', 'Food', '🍛'], ['chaos', 'Pure chaos', '🎆']],
		items: [
			{ id: 'farmhouse-stages', e: '🏡', brand: 'Farm-house', name: 'With 3 Stages', cat: 'venue', usd: 18000, quip: 'One stage per rishtedaar faction.', silly: 0, tag: 'Bestseller' },
			{ id: 'lake-villa', e: '🏰', brand: 'Lake Como-ish', name: 'Villa Abroad', cat: 'venue', usd: 90000, quip: 'Half the guests need visas.', silly: 1, tag: 'Only 1 left' },
			{ id: 'yacht-mehndi', e: '🛥️', brand: 'Sunsinker', name: 'Mehndi On A Yacht', cat: 'venue', usd: 35000, quip: 'Henna + seasickness. Iconic.', silly: 1 },
			{ id: 'sheesh-mahal', e: '🪞', brand: 'Sheesh Mahal', name: 'Hall Made Of Mirrors', cat: 'venue', usd: 12000, quip: 'Infinite uncles. Infinite selfies.', silly: 1 },
			{ id: 'ghori', e: '🐎', brand: 'Classic', name: 'Ghori Entry', cat: 'entry', usd: 1500, quip: 'The horse hates the dhol.', silly: 0 },
			{ id: 'helicopter-entry', e: '🚁', brand: 'Heli-Baraat', name: 'Helicopter Entry', cat: 'entry', usd: 25000, quip: 'Lands on the buffet. Probably.', silly: 1, tag: 'Ammi disapproved' },
			{ id: 'elephant-lights', e: '🐘', brand: 'Royal', name: 'Elephant With Fairy Lights', cat: 'entry', usd: 9000, quip: 'Main character energy.', silly: 1 },
			{ id: 'dhol-500', e: '🥁', brand: 'Dhol Squad', name: '500 Dhol Players', cat: 'entry', usd: 6000, quip: 'Neighbours have moved out.', silly: 1 },
			{ id: 'rolls-rice-doli', e: '🚘', brand: 'Rolls Rice', name: 'Bridal Car With 400 Roses', cat: 'entry', usd: 4000, quip: 'Rukhsati in 4K.', silly: 0 },
			{ id: 'lehenga', e: '👗', brand: 'Sabya-saachi-much', name: 'Lehenga Heavier Than You', cat: 'outfit', usd: 18000, quip: 'Requires two cousins to walk.', silly: 0, tag: 'Only 1 left' },
			{ id: 'sherwani', e: '🤵', brand: 'Gold Thread', name: 'Sherwani That Glows', cat: 'outfit', usd: 7000, quip: 'Shines brighter than the future.', silly: 0 },
			{ id: 'outfit-changes', e: '👠', brand: 'Couture', name: '3 Outfit Changes Per Function', cat: 'outfit', usd: 14000, quip: 'Seven functions. Do the math.', silly: 1 },
			{ id: 'matching-cousins', e: '🧥', brand: 'Family Pack', name: 'Matching Outfits For 80 Cousins', cat: 'outfit', usd: 15000, quip: 'Colour code: "dusty pista".', silly: 1 },
			{ id: 'kundan-set', e: '💎', brand: 'Kundan', name: 'Jewellery Set (Bank Locker Required)', cat: 'outfit', usd: 40000, quip: 'Worn for 3 hours. Insured for 30 years.', silly: 0, tag: 'Influencer pick' },
			{ id: 'unlimited-biryani', e: '🍛', brand: 'Degh Masters', name: 'Unlimited Biryani (Fights Included)', cat: 'food', usd: 5000, quip: 'The only thing guests remember.', silly: 0, tag: 'Bestseller' },
			{ id: 'buffet-47', e: '🍽️', brand: 'Mega Buffet', name: '47 Dishes', cat: 'food', usd: 15000, quip: 'Uncles still complain about salt.', silly: 0 },
			{ id: 'cake-7', e: '🎂', brand: 'Patisserie', name: '7-Tier Cake', cat: 'food', usd: 3500, quip: 'Nobody eats cake at desi weddings.', silly: 1 },
			{ id: 'chai-bar', e: '☕', brand: 'Chai Wala', name: 'Live Chai Bar With DJ', cat: 'food', usd: 1800, quip: 'Doodh patti drops at midnight.', silly: 0 },
			{ id: 'golgappa-fountain', e: '🫓', brand: 'Street Royale', name: 'Golgappa Fountain', cat: 'food', usd: 1200, quip: 'Pani puri on tap. Dignity off tap.', silly: 1 },
			{ id: 'fireworks', e: '🎆', brand: 'Boom Bros', name: 'Fireworks Until 4am', cat: 'chaos', usd: 9000, quip: 'Police visit included.', silly: 1 },
			{ id: 'drone-show', e: '🛸', brand: 'Sky Show', name: 'Drones Spelling Your Names', cat: 'chaos', usd: 12000, quip: 'Spelling mistake guaranteed.', silly: 1 },
			{ id: 'lip-sync-singer', e: '🎤', brand: 'Famous Singer', name: 'One Song (Lip-sync)', cat: 'chaos', usd: 30000, quip: 'Sings the one song you hate.', silly: 0 },
			{ id: 'phuppo-bouncers', e: '🕴️', brand: 'Security', name: 'Bouncers For Phuppo Control', cat: 'chaos', usd: 2500, quip: 'Specifically for that one phuppo.', silly: 1, tag: 'Influencer pick' },
			{ id: 'ice-couple', e: '🧊', brand: 'Ice Art', name: 'Ice Sculpture Of The Couple', cat: 'chaos', usd: 3000, quip: 'Melts by the rukhsati. Symbolic.', silly: 1 },
			{ id: 'gold-invites', e: '💌', brand: 'Gold-Plated', name: 'Invitations (2 kg Each)', cat: 'chaos', usd: 4500, quip: 'Guests need a trolley to RSVP.', silly: 1 }
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
		budgets: { usd: 1000000000 },
		qty: true,
		invert: true,
		intro: 'One billion dollars. Your only job: spend every last cent. It is harder than it sounds. Not really.',
		hint: 'Tip: you can buy more than one. Nobody is stopping you.',
		categories: [['all', 'Everything', '💸'], ['big', 'Big toys', '🛩️'], ['assets', 'Assets', '🏙️'], ['silly', 'Silly', '🍔'], ['good', 'Do good', '💚']],
		items: [
			{ id: 'gulf-jet', e: '🛩️', brand: 'Gulf-stream', name: 'G-Whiz Private Jet', cat: 'big', usd: 65000000, quip: 'For your 40-minute flights.', silly: 0, tag: 'Bestseller' },
			{ id: 'superyacht', e: '🛳️', brand: 'Sunsinker', name: 'Superyacht With Helipad', cat: 'big', usd: 300000000, quip: 'And a smaller yacht inside it.', silly: 1 },
			{ id: 'space-ex', e: '🚀', brand: 'Space-Ex-Boyfriend', name: 'Rocket Launch To Nowhere', cat: 'big', usd: 60000000, quip: 'Eleven minutes in space. Worth it?', silly: 1 },
			{ id: 'f1-team', e: '🏎️', brand: 'Furrari', name: 'Formula 1 Team', cat: 'big', usd: 500000000, quip: 'You will finish 9th. Every year.', silly: 0 },
			{ id: 'football-club', e: '⚽', brand: 'Manchester Unitedish', name: 'Football Club', cat: 'assets', usd: 400000000, quip: 'Fans will still boo you.', silly: 0, tag: 'Only 1 left' },
			{ id: 'skyscraper', e: '🏙️', brand: 'Your Name', name: 'Skyscraper', cat: 'assets', usd: 250000000, quip: 'In very large letters.', silly: 1 },
			{ id: 'mall', e: '🏬', brand: 'Mega', name: 'Entire Shopping Mall', cat: 'assets', usd: 180000000, quip: 'Free parking for you only.', silly: 0 },
			{ id: 'island-large', e: '🏝️', brand: 'Private', name: 'Island (Large)', cat: 'assets', usd: 90000000, quip: 'Comes with seagulls. Many seagulls.', silly: 0 },
			{ id: 'labuboo', e: '🧸', brand: 'Labuboo', name: 'Every Labuboo Ever Made', cat: 'silly', usd: 80000000, quip: 'Ugly-cute. Mostly ugly.', silly: 1, tag: 'Bestseller' },
			{ id: 'mcdanalds', e: '🍔', brand: 'McDanalds', name: '1 Million Burgers', cat: 'silly', usd: 5000000, quip: 'Feeds a city. Or you, for a week.', silly: 1 },
			{ id: 'gold-bathtub', e: '🛁', brand: 'Solid Gold', name: 'Bathtub', cat: 'silly', usd: 1200000, quip: 'Freezing cold. Very shiny.', silly: 1 },
			{ id: 't-rex', e: '🦖', brand: 'Jurassic-ish', name: 'T-Rex Skeleton', cat: 'silly', usd: 32000000, quip: 'For the living room.', silly: 1 },
			{ id: 'diamond-case', e: '💎', brand: 'iFone', name: 'Diamond Phone Case', cat: 'silly', usd: 150000, quip: 'Drops exactly like a normal phone.', silly: 1 },
			{ id: 'weekly-concert', e: '🎤', brand: 'Pop Star', name: 'Private Concert, Every Week', cat: 'silly', usd: 4000000, quip: 'Same singer. Every. Single. Week.', silly: 1, tag: 'Influencer pick' },
			{ id: 'hospital', e: '🏥', brand: 'Do Good', name: 'Build A Hospital', cat: 'good', usd: 150000000, quip: 'Okay this one is actually great.', silly: 0 },
			{ id: 'schools', e: '🏫', brand: 'Do Good', name: 'Fund 100 Schools', cat: 'good', usd: 50000000, quip: 'Look at you, being a good person.', silly: 0 },
			{ id: 'trees', e: '🌳', brand: 'Do Good', name: 'Plant 10 Million Trees', cat: 'good', usd: 20000000, quip: 'The planet says shukriya.', silly: 0 }
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
		budgets: { usd: 5000000, pkr: 1400000000, inr: 400000000 },
		invert: true,
		intro: 'Build your dream life: house, gaadi, travel, everything. Then find out how delusional it is.',
		hint: 'Manifest it. Then add to cart.',
		categories: [['all', 'Everything', '🏝️'], ['house', 'Ghar', '🏡'], ['car', 'Gaadi', '🚗'], ['travel', 'Travel', '✈️'], ['life', 'Daily life', '☕']],
		items: [
			{ id: 'villa-pool', e: '🏡', brand: 'Beverly Hills-ish', name: 'Villa With A Pool', cat: 'house', usd: 2200000, quip: 'Pool is for photos only.', silly: 0, tag: 'Bestseller' },
			{ id: 'penthouse', e: '🌆', brand: 'Sky Living', name: 'Penthouse With City View', cat: 'house', usd: 1500000, quip: 'You will still watch reels.', silly: 0 },
			{ id: 'peace-farmhouse', e: '🌾', brand: 'Farm-house', name: 'For "Peace"', cat: 'house', usd: 600000, quip: 'Visited twice a year.', silly: 1 },
			{ id: 'hunza-cabin', e: '🏔️', brand: 'Northern Areas', name: 'Cabin With A View', cat: 'house', usd: 150000, quip: 'No signal. Finally peace.', silly: 0, tag: 'Influencer pick' },
			{ id: 'home-cinema', e: '🍿', brand: 'Sony-ish', name: 'Home Cinema', cat: 'house', usd: 40000, quip: 'For rewatching the same show.', silly: 0 },
			{ id: 'range-rober', e: '🚙', brand: 'Range Rober', name: 'SUV That Fits No Parking', cat: 'car', usd: 180000, quip: 'Parks across three spots.', silly: 0, tag: 'Bestseller' },
			{ id: 'vintage-mercedez', e: '🚘', brand: 'Mercedez', name: '1965 Classic (Doesn\'t Start)', cat: 'car', usd: 120000, quip: 'Purely decorative.', silly: 1 },
			{ id: 'dukati', e: '🏍️', brand: 'Dukati', name: 'Superbike', cat: 'car', usd: 30000, quip: 'Ammi has said no. Firmly.', silly: 1, tag: 'Ammi disapproved' },
			{ id: 'cybertruk', e: '🛻', brand: 'Teslaa', name: 'Cybertruk (Shaped Like A Doorstop)', cat: 'car', usd: 100000, quip: 'Bulletproof. Taste-proof.', silly: 1 },
			{ id: 'driver', e: '🧑‍✈️', brand: 'Full-time', name: 'Personal Driver (Mostly To Chai)', cat: 'car', usd: 15000, quip: 'Drives you 400m. Daily.', silly: 0 },
			{ id: 'world-tour', e: '🌍', brand: 'Wanderlust', name: 'One-Year World Tour', cat: 'travel', usd: 200000, quip: 'Posting stories every 4 minutes.', silly: 0 },
			{ id: 'first-class', e: '💺', brand: 'Emirats', name: 'Only First Class, Forever', cat: 'travel', usd: 80000, quip: 'Complaining about the champagne.', silly: 1 },
			{ id: 'maldives', e: '🏝️', brand: 'Maldives', name: 'Every Winter', cat: 'travel', usd: 40000, quip: 'Same photo. Different year.', silly: 0 },
			{ id: 'space-seat', e: '🛰️', brand: 'Blue Origin-ish', name: 'Space Tourism Seat', cat: 'travel', usd: 450000, quip: 'Eleven minutes. Lifetime story.', silly: 1, tag: 'Only 1 left' },
			{ id: 'chef', e: '👨‍🍳', brand: 'Private', name: 'Chef (You Still Order Fast Food)', cat: 'life', usd: 60000, quip: 'Michelin skills. Ketchup requests.', silly: 0 },
			{ id: 'trainer', e: '🏋️', brand: 'Personal', name: 'Trainer (Ghosted Week 2)', cat: 'life', usd: 12000, quip: 'Paid for the year. Went twice.', silly: 1 },
			{ id: 'starbuks', e: '☕', brand: 'Starbuks', name: 'Coffee Every Day Forever', cat: 'life', usd: 30000, quip: 'Name spelled wrong every time.', silly: 1 },
			{ id: 'golden-dogs', e: '🐕', brand: 'Good Boys', name: 'Three Golden Retrievers', cat: 'life', usd: 5000, quip: 'The real dream, honestly.', silly: 0, tag: 'Influencer pick' },
			{ id: 'pet-tiger', e: '🐅', brand: 'Bad Idea', name: 'Pet Tiger', cat: 'life', usd: 50000, quip: 'Neighbours will move. Again.', silly: 1, tag: 'Ammi disapproved' }
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
