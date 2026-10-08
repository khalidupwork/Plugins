# Talkwyn hooks

Talkwyn Pro adds every Pro feature through these actions and filters. You can use them too.

## Renamed in 2.1.0

| Old | New |
|---|---|
| `talkwyn_retrieve` | `talkwyn_sources` |
| `talkwyn_pre_reply` | `talkwyn_before_answer` |
| `talkwyn_after_reply` | `talkwyn_after_answer` |
| `talkwyn_lead_saved` | `talkwyn_lead_created` |
| `talkwyn_show_powered_by` | `talkwyn_show_badge` |

## Lifecycle

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `talkwyn_loaded` | action | `$version` | Talkwyn finished loading. Add-ons start here. |
| `talkwyn_pro_active` | filter | `bool $active` | Return true when a Pro add-on is running. Hides upgrade prompts. |
| `talkwyn_daily_cleanup_done` | action | `$cutoff` | After old logs were deleted. |
| `talkwyn_migrated_from_nabia` | action | `array $copied` | After Nabia data was copied. |

## Settings

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `talkwyn_settings_defaults` | filter | `array $defaults` | Add setting keys and defaults. |
| `talkwyn_settings_schema` | filter | `array $schema` | Register keys by type (`bool`, `int`, `textarea`, `email`, `url`, `color`, `key`, `array`). |
| `talkwyn_sanitize_settings` | filter | `array $out, array $input` | Sanitize structured values (arrays that are not in the schema). |
| `talkwyn_settings_saved` | action | `$tab` | After a settings form was saved. |
| `talkwyn_translatable_keys` | filter | `array $keys` | Settings visitors read, registered with WPML and Polylang. |

## Knowledge

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `talkwyn_index_post_types` | filter | `array $types` | Post types scanned. |
| `talkwyn_is_indexable` | filter | `bool $ok, WP_Post $post` | Exclude or include a post. |
| `talkwyn_index_post_content` | filter | `string $text, WP_Post $post` | Change the text stored for a post. |
| `talkwyn_post_indexed` | action | `$post_id, $text` | A post was stored. |
| `talkwyn_post_removed` | action | `$post_id` | A post was removed (unpublished, trashed, deleted, protected). |
| `talkwyn_scan_complete` | action | | A full scan finished. |
| `talkwyn_index_cleared` | action | | Site knowledge was cleared. |

`Talkwyn_Indexer::store_chunks( $source_key, $type, $source_id, $url, $title, $text, $modified, $lang )` and `Talkwyn_Indexer::remove_source( $source_key )` let add-ons store their own sources. Use your own key prefix (Pro uses `qa:` and `src:`). "Clear knowledge" only removes `post:` and `site:` sources.

## Answers

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `talkwyn_providers` | filter | `array $providers` | Add AI providers. Each entry: `label`, `fields`, `key_field`, `model_field`, `signup`, `free`, `ready( $settings )`, `call( $messages, $settings )`, `models( $settings )`. |
| `talkwyn_sources` | filter | `array $chunks, $query, $limit, array $context` | Replace or re-rank retrieved knowledge (Pro: smart search). |
| `talkwyn_before_answer` | filter | `null, array $ctx` | Return `array( 'text' => ..., 'provider' => ... )` to answer without the AI (Pro: custom answers, order lookup, away mode). |
| `talkwyn_system_prompt` | filter | `string $prompt, array $ctx` | Change the system prompt. |
| `talkwyn_reply_extra` | filter | `array $extra, array $ctx, $reply` | Extra data saved with the reply and sent to the widget (Pro: product cards). |
| `talkwyn_reply` | filter | `array $data, array $ctx` | Change the response sent to the widget. Set `lead_offer` and `lead_direct` to show the lead form. |
| `talkwyn_after_answer` | action | `array $data, array $ctx, array $gen` | After a reply (analytics). |
| `talkwyn_history_ttl` | filter | `int $seconds` | How long a conversation is kept for continuity (default one day). |

`Talkwyn_Chat::prepare()` and `Talkwyn_Chat::finish()` split a turn so an add-on can call the provider itself (Pro streams replies this way). `Talkwyn_Providers::openai_compatible()` and `Talkwyn_Providers::json()` are public helpers.

## Leads and feedback

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `talkwyn_lead_created` | action | `$id, array $lead` | A lead was saved (Pro: Slack and Telegram alerts). |
| `talkwyn_feedback_saved` | action | `$log_id, $value, $session` | Helpful or not helpful was saved. |
| `talkwyn_widget_event` | action | `$type, $session, WP_REST_Request $req` | Custom events sent to `POST talkwyn/v1/event`. |
| `talkwyn_personal_data_erased` | action | `$email, array $sessions` | After a privacy erase request. |

## REST and widget

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `talkwyn_rest_routes` | action | `$namespace` | Register routes in `talkwyn/v1`. Use `Talkwyn_REST::guard( $request )` to check the token and session. |
| `talkwyn_widget_config` | filter | `array $config` | Data passed to the widget as `window.TalkwynConfig`. |
| `talkwyn_enqueue_widget` | action | | The widget assets were enqueued. Enqueue yours here. |
| `talkwyn_show_widget` | filter | `bool $show` | Hide the floating widget on a request. |
| `talkwyn_show_badge` | filter | `bool $show` | Show or hide the "Powered by Talkwyn" link. |
| `talkwyn_widget_menu` | filter | `array $items` | Items in the chat menu. Each item: `id`, `label`, optional `url`. Built-in ids: `name`, `transcript`, `sound`, `language`, `popout`, `reset`, `add_chat`. An item with `url` opens that link; other unknown ids fire the `menu` event in JavaScript. |
| `talkwyn_languages` | filter | `array $languages` | Languages in the chat menu picker, keyed by code: `native`, `en`, `rtl`. |

### JavaScript API

`window.Talkwyn` is available after `widget.js` loads.

| Method | Use |
|---|---|
| `on( event, fn )` | Events: `ready`, `open`, `close`, `message` (`{ role, el, bubble, data }`), `reply` (`{ data, el }`), `lead`, `reset`. They are also dispatched on `document` as `talkwyn:<event>`. |
| `open()`, `close()`, `send( text )` | Control the chat. |
| `addMessage( role, html, options )` | Add a message. Pass `html` as `null` and `options.text` for plain text. |
| `setTransport( fn )` | Replace how messages are sent. `fn( payload, { onDelta } )` returns a promise of the reply data; call `onDelta( html )` to show partial text. |
| `request( path, body )` | POST to a `talkwyn/v1` route with the token and session. |
| `auth( force )` | Promise of `{ token, session }` for custom requests. |

## Admin

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `talkwyn_admin_tabs` | filter | `array $tabs` | Add tabs to the Talkwyn screen. |
| `talkwyn_admin_tab_{$tab}` | action | | Render a tab you added. |
| `talkwyn_admin_menu_title` | filter | `string $title` | Rename the admin menu (Pro: white label). |
| `talkwyn_admin_logo` | filter | `string $url` | Logo in the admin header (Pro: white label). |
| `talkwyn_hub_url` | filter | `string $url` | Base URL for the optional email opt-in in the setup wizard. |
| `talkwyn_admin_dashboard`, `talkwyn_admin_knowledge`, `talkwyn_admin_providers`, `talkwyn_admin_appearance`, `talkwyn_admin_behavior` | action | | Add content to a built-in tab. |

`Talkwyn_Admin::form_open()`, `form_close()`, `card()`, `field()`, `toggle()` and `select()` render settings that save through Talkwyn's own handler.

## Talkwyn Pro hooks

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `talkwyn_pro_loaded` | action | | Pro modules loaded (license active). |
| `talkwyn_pro_license_active` | filter | `bool $active` | Override the license state (testing only). |
| `talkwyn_pro_public_keys` | filter | `array $keys` | Trusted Talkwyn Hub public keys. |
| `talkwyn_pro_hub_url` | filter | `string $url` | Talkwyn Hub API base URL. |
| `talkwyn_pro_stream_spec` | filter | `null, $id, $messages, $settings` | Stream a custom provider: return `url`, `headers`, `body`, `format` (`openai` or `anthropic`). |
| `talkwyn_pro_qa_threshold` | filter | `float $min` | How close a question must be to a custom answer (default 0.72). |
| `talkwyn_pro_crawl_limit` | filter | `int $limit` | Pages read from one sitemap (default 200). |
| `talkwyn_pro_order_id` | filter | `int $number` | Map an order number to an order ID (for sequential order number plugins). |
| `talkwyn_pro_order_text` | filter | `string $text, WC_Order $order` | Change the order status reply. |
| `talkwyn_pro_sources_changed` | action | | Extra knowledge changed (smart search re-embeds). |
