# Talkwyn plugins

| Folder | What it is |
|---|---|
| `talkwyn/` | **Talkwyn**, the free plugin for WordPress.org (renamed and rebuilt from Nabia AI Chatbot 1.9.0). PHP 7.4+. |
| `talkwyn-pro/` | **Talkwyn Pro**, the paid add-on sold through Talkwyn Hub. PHP 8.0+. Requires the free plugin. |
| `QA-CHECKLIST.md` | Manual test plan: fresh install, migration from Nabia, trial to Pro to expiry, paid activation, updates. |

Docs: [`talkwyn/readme.txt`](talkwyn/readme.txt), [`talkwyn/HOOKS.md`](talkwyn/HOOKS.md), [`talkwyn/MIGRATION.md`](talkwyn/MIGRATION.md), [`talkwyn-pro/readme.txt`](talkwyn-pro/readme.txt).

## Tests

```
cd talkwyn && phpunit
cd talkwyn-pro && phpunit
```

## Building ZIPs

Zip each folder so the archive root is `talkwyn/` or `talkwyn-pro/`, without `tests/` and `phpunit.xml.dist`. For Pro, add the Hub public key to `talkwyn-pro/includes/hub-keys.php` first. The WordPress.org icons and banners live in `talkwyn/.wordpress-org/` and go to the SVN `assets/` folder, not the ZIP.
