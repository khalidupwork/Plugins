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

## Next (in order)
- [ ] `single-cmk_game.php` + Shop Like You're Rich engine (`assets/js/game-shop.js`): products, categories, cart drawer,
      reactions, checkout → result
- [ ] `/result/` page template + `assets/js/result.js`: result card, Financial IQ, share (Web Share, WhatsApp, X, copy),
      download PNG story card (canvas), challenge link, play-another
- [ ] Challenge flow: `?challenge=` banner on game page (`challenge_open` event)
- [ ] `archive-cmk_game.php` (games library with filter chips)
- [ ] `page-about.php` (WTF is this?) styled page
- [ ] Full QA pass of game + result at 4 widths, then package zip + bump version

## Local test rig (cloud session)
WordPress 6.8.3 from GitHub mirror + SQLite integration v2.2.2 in the scratchpad, theme symlinked, `php -S localhost:8080`
with a router; Playwright screenshots at 1440/1024/390/320.
