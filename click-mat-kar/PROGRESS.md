# Click Mat Kar theme — build progress

Fresh theme (not based on the v0.9.0 zip). Source of truth: the 2026-10-10 master handoff
(Master Plan, Brand Guide V2, Homepage UX/Sitemap, Technical QA, brand tokens, analytics event map).

## Done
- [x] Theme skeleton: `style.css`, `functions.php`, `inc/` (setup, games CPT + catalog, template tags, installer)
- [x] Design system CSS (`assets/css/main.css`): brand tokens, hard borders/offset shadows, buttons, pills, cards, wordmark, reduced-motion
- [x] Header (sticky, mobile menu) + footer
- [x] Homepage, all 12 sections from the UX map (hero with playable mini shop, ticker, game grid, featured swipe demo,
      chaos level, how it works, lab/upcoming, results, manifesto, challenge, final CTA)
- [x] `cmk_game` CPT, catalog of 7 games (only Shop Like You're Rich is live; rest are Drafts → "Soon" cards, never links)
- [x] Appearance > Click Mat Kar 1-click setup (pages, front page, privacy page, games, menus, permalinks; idempotent)
- [x] Analytics helper `CMKUI.track()` → dataLayer / gtag / plausible + `cmk:track` DOM event
- [x] Currency localisation (PKR / INR / USD via timezone, `?cur=usd|pkr|inr` override)
- [x] QA: homepage at 1440 / 1024 / 390 / 320, no console errors, no page-level horizontal scroll
- [x] `single-cmk_game.php` + game shell (`template-parts/game/shop.php`) + "cooking" fallback for non-live games
- [x] Shop Like You're Rich engine (`assets/js/game-shop.js`): 24 products, 6 categories, sticky budget HUD, fly-to-cart,
      reactions, cart drawer (remove items, Esc to close), fake checkout → Financial IQ → `/result/?r=…`
- [x] `/result/` (`page-result.php` + `assets/js/result.js`): animated result card, IQ tier verdict, share (Web Share,
      WhatsApp, X, copy link), 1080×1920 story-card PNG download, challenge link, play again / play another, empty state,
      owner vs. visitor view ("Accept the challenge")
- [x] Challenge flow: `?challenge=` banner on the game page (`challenge_open`)
- [x] Server-side OG title for shared results ("I wasted Rs … Financial IQ: 40/100"), result pages `noindex`
- [x] `/games/` library (`archive-cmk_game.php`) with Everything / Play now / Cooking filter
- [x] `/about/` (`page-about.php`) "WTF is this?" page, styled 404
- [x] All analytics events from the event map wired: page_view, game_view, game_start, product_add, cart_open,
      game_complete, result_share, challenge_create, challenge_open, play_another (+ cta_click)
- [x] Runtime QA on real WordPress 6.8.3: 1-click setup (fresh + re-run), homepage/game/result/library/about/404 at
      1440/1024/390/320, full flow add → cart → checkout → result → story PNG → visitor → challenge, no console/PHP errors


## Next (in order)
- [ ] Real product art: swap emoji for WebP illustrations (3D sticker style from the moodboard) with set dimensions
- [ ] Site icon / OG share image (1200×630) + per-result OG image (needs server-side render or a static set by IQ tier)
- [ ] Customizer/settings: analytics ID field (GA4 / Plausible) — `CMKUI.track` already pushes to dataLayer/gtag/plausible
- [ ] Optional micro-sounds (off by default) and haptics on mobile add-to-cart
- [ ] Second game on the same engine shell (data decides: Dream Wedding is #2 on the roadmap)
- [ ] Before release: rename version to 1.0.0, zip `click-mat-kar/` (zips are git-ignored), install on staging,
      run setup, repeat the 4-width QA

## Local test rig (cloud session)
WordPress 6.8.3 from GitHub mirror + SQLite integration v2.2.2 in the scratchpad, theme symlinked, `php -S localhost:8080`
with a router; Playwright screenshots at 1440/1024/390/320.
