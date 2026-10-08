# Moving from Nabia AI Chatbot to Talkwyn

Talkwyn 2.0.0 is the renamed and rebuilt Nabia AI Chatbot 1.9.0. Your data moves over by itself.

## What happens

1. Install and activate Talkwyn while Nabia AI Chatbot is still installed.
2. The first time an administrator opens wp-admin, Talkwyn finds the old data and copies it once:

| Nabia | Talkwyn |
|---|---|
| Option `nac_settings` | Option `talkwyn_settings` (same keys) |
| Table `{prefix}nac_chunks` | Table `{prefix}talkwyn_chunks` |
| Table `{prefix}nac_leads` | Table `{prefix}talkwyn_leads` |
| Table `{prefix}nac_logs` | Table `{prefix}talkwyn_logs` |

3. A notice shows what was copied, with two buttons:
   * **Delete old Nabia data** deactivates Nabia AI Chatbot, then drops the `nac_*` tables and options.
   * **Keep it for now** hides the notice. The old data stays until you delete Nabia.

## Settings that change on the way

* **Avatar and launcher icon.** The robot and sparkle icons become the Talkwyn bubble. "AI letters" becomes "First letter of the name". Custom images are kept.
* **Colour.** The old default `#111111` becomes Talkwyn Red `#D7263D`. Any colour you picked is kept.
* **Custom fields.** Indexing public custom fields is now opt-in with an allow list, so it is switched off after the move. Turn it on under Knowledge and list the fields that are safe to show.
* **Typing text.** The old default "AI is typing" becomes "{bot} is typing".
* **Log retention.** Kept as you set it. New installs default to 30 days.

API keys, models, fallback order, lead texts and visibility rules carry over unchanged.

## After the move

* Deactivate Nabia AI Chatbot so visitors see one chat (the delete button does this for you).
* Run **Scan entire site** once under Talkwyn, Knowledge. The scan also removes any knowledge copied from posts that no longer exist.
* The shortcode is now `[talkwyn_chat]`. The old `[nabia_ai_chatbot]` keeps working once Nabia is deactivated, but replace it when you can.

## Running it again

The migration runs once and records `talkwyn_migrated_from_nabia`. To copy again (for example on a staging copy), delete that option and reload wp-admin. Rows are appended, so empty the Talkwyn tables first if you do not want duplicates.
