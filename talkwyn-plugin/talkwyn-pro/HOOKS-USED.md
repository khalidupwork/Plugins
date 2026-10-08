# Free plugin hooks used by Talkwyn Pro

Talkwyn Pro never edits the free plugin. Everything below goes through a hook the free plugin (2.1.0 or newer) fires. Pro also fires `talkwyn_pro_active` through the free admin to hide upgrade buttons.

| Hook | Type | Pro file | What Pro does with it |
| --- | --- | --- | --- |
| `talkwyn_settings_defaults` | filter | Settings.php | Adds the Pro setting keys and their defaults. |
| `talkwyn_settings_schema` | filter | Settings.php | Adds types (bool, int, url, text) for the Pro keys. |
| `talkwyn_sanitize_settings` | filter | Settings.php | Cleans structured Pro values (proactive messages, business hours). |
| `talkwyn_admin_tabs` | filter | Admin.php | Adds Extra knowledge, Analytics, Unanswered, Pro settings and License. Without a license only License is added. |
| `talkwyn_admin_tab_{tab}` | action | Admin.php | Prints each Pro tab. |
| `talkwyn_admin_dashboard` | action | Admin.php | Adds the Pro summary on the dashboard. |
| `talkwyn_pro_active` | filter | Plugin.php | Tells the free admin Pro is licensed, so the trial and upgrade buttons hide. |
| `talkwyn_admin_logo` | filter | WhiteLabel.php | Swaps the admin header logo for the agency logo. |
| `talkwyn_show_badge` | filter | WhiteLabel.php | Hides "Powered by Talkwyn" when white label is on. |
| `talkwyn_widget_menu` | filter | WhiteLabel.php | Replaces the "Add chat to your website" item with the agency link when both link fields are set. |
| `talkwyn_providers` | filter | Providers.php | Registers OpenAI, Anthropic, Mistral and DeepSeek. |
| `talkwyn_sources` | filter | Search.php | Mixes embedding results with keyword results. |
| `talkwyn_scan_complete` | action | Search.php | Queues new embeddings after a scan. |
| `talkwyn_post_indexed` | action | Search.php | Queues embeddings for a saved post. |
| `talkwyn_before_answer` | filter | Knowledge.php, Woo.php, Engage.php | Custom answers (priority 5), order lookup (8), away reply (1). |
| `talkwyn_reply` | filter | Engage.php | Turns replies into a lead request while away. |
| `talkwyn_reply_extra` | filter | Woo.php | Adds WooCommerce product cards to a reply. |
| `talkwyn_lead_created` | action | Alerts.php | Sends Slack and Telegram alerts. |
| `talkwyn_rest_routes` | action | Stream.php | Registers the streaming endpoint. |
| `talkwyn_enqueue_widget` | action | Frontend.php | Loads the Pro widget script. |
| `talkwyn_widget_config` | filter | Frontend.php, Engage.php | Adds stream, proactive messages and business hours to the widget config. |

## Hook names changed in Talkwyn 2.1.0

| Old | New |
| --- | --- |
| `talkwyn_retrieve` | `talkwyn_sources` |
| `talkwyn_pre_reply` | `talkwyn_before_answer` |
| `talkwyn_after_reply` | `talkwyn_after_answer` |
| `talkwyn_lead_saved` | `talkwyn_lead_created` |
| `talkwyn_show_powered_by` | `talkwyn_show_badge` |

Pro 1.2.0 uses only the new names and shows a notice if Talkwyn is older than 2.1.0.
