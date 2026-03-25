# CartFlows — Developer Skill Reference

**Plugin**: CartFlows
**Package**: `cartflows`
**Version**: 2.2.2
**Text Domain**: `cartflows`
**Constant Prefix**: `CARTFLOWS_`
**PHP Namespace**: `CartflowsAdmin` (admin layer), no namespace for legacy core classes
**Author**: Brainstorm Force / CartFlows Inc

---

## 1. Plugin Identity

| Property | Value |
|---|---|
| Main file | `cartflows.php` |
| Global accessor | `wcf()` — returns `Cartflows_Loader::get_instance()` |
| Admin page hook | `toplevel_page_cartflows` |
| Admin menu slug | `cartflows` |
| Capability: flows | `cartflows_manage_flows_steps` |
| Capability: settings | `cartflows_manage_settings` |
| WC requires | >= 3.0, tested up to 9.8.5 |
| Elementor tested up to | 3.28.4 |
| Pro plugin constant | `CARTFLOWS_PRO_VER` |
| Min required Pro | `CARTFLOWS_REQ_CF_PRO_VER` = `'2.2.0'` |
| REST namespace | `cartflows/v1` |
| AJAX action prefix | `cartflows_` |
| Cookie prefix | `CARTFLOWS_ACTIVE_CHECKOUT` (`wcf_active_checkout`) |
| Log dir | `CARTFLOWS_LOG_DIR` = `{uploads}/cartflows-logs/` |

---

## 2. File & Folder Structure

```
cartflows/
├── cartflows.php                    # Plugin header, defines CARTFLOWS_FILE, boots Cartflows_Loader
├── admin-loader.php                 # CartflowsAdmin namespace — SPL autoloader, Admin_Loader singleton
├── uninstall.php                    # Cleanup on plugin deletion
├── classes/                         # Legacy core PHP classes (no namespace)
│   ├── class-cartflows-loader.php   # Cartflows_Loader — main singleton, constants, loads all files
│   ├── class-cartflows-helper.php   # Cartflows_Helper — settings accessor, caches, site slug
│   ├── class-cartflows-utils.php    # Cartflows_Utils — utility helpers (step type checks, URL helpers)
│   ├── class-cartflows-functions.php # Global helper functions (wcf(), _is_woo_installed(), etc.)
│   ├── class-cartflows-step-factory.php # Cartflows_Step_Factory — step object with A/B test support
│   ├── class-cartflows-default-meta.php # Cartflows_Default_Meta — default field values per step type
│   ├── class-cartflows-frontend.php # Cartflows_Frontend — enqueues scripts/styles on CartFlows pages
│   ├── class-cartflows-flow-frontend.php # Cartflows_Flow_Frontend — routing, current step detection
│   ├── class-cartflows-woo-hooks.php # Cartflows_Woo_Hooks — WooCommerce hook integrations
│   ├── class-cartflows-admin.php    # Cartflows_Admin — legacy admin UI helpers
│   ├── class-cartflows-admin-notices.php # Admin notice management
│   ├── class-cartflows-logger.php   # Cartflows_Logger — wraps WC logger
│   ├── class-cartflows-tracking.php # Analytics / conversion tracking
│   ├── class-cartflows-update.php   # Version-to-version migration tasks
│   ├── class-cartflows-rollback.php # Plugin rollback feature
│   ├── fields/
│   │   └── typography/
│   │       └── class-cartflows-font-families.php
│   ├── importer/
│   │   └── class-cartflows-importer-loader.php
│   ├── logger/                      # WC-compatible logger interfaces and handlers
│   └── deprecated/
│       └── deprecated-hooks.php     # Deprecated hook aliases for backward compat
├── admin-core/
│   ├── ajax/                        # AJAX handlers (CartflowsAdmin\AdminCore\Ajax namespace)
│   │   ├── ajax-base.php            # AjaxBase abstract class — registers wp_ajax_ hooks, nonces
│   │   ├── ajax-init.php            # AjaxInit — instantiates all AJAX handler classes
│   │   ├── ajax-errors.php          # AjaxErrors singleton — standardised error messages
│   │   ├── flows.php                # Flows — CRUD, clone, trash, reorder, export, status
│   │   ├── flows-stats.php          # FlowsStats — analytics data for flows list
│   │   ├── steps.php                # Steps — CRUD, clone, save meta settings
│   │   ├── meta-data.php            # MetaData — product/coupon search endpoints
│   │   ├── common-settings.php      # CommonSettings — global settings save, CSS regeneration
│   │   ├── ab-steps.php             # AbSteps — A/B test step AJAX operations
│   │   ├── debugger.php             # Debugger — system info and log access
│   │   ├── importer.php             # Importer — template library import handler
│   │   └── learn.php                # Learn — knowledge base article fetching
│   ├── api/                         # REST endpoints (CartflowsAdmin\AdminCore\Api namespace)
│   │   ├── api-base.php             # ApiBase — extends WP_REST_Controller, namespace cartflows/v1
│   │   ├── api-init.php             # ApiInit — registers all REST route classes
│   │   ├── flows.php                # Flows API — list flows (POST /admin/flows/)
│   │   ├── flow-data.php            # FlowData — get single flow (GET /admin/flow-data/{id})
│   │   ├── step-data.php            # StepData — get single step (GET /admin/step-data/{id})
│   │   ├── common-settings.php      # CommonSettings API — GET /admin/commonsettings/
│   │   ├── home-page.php            # HomePage API — GET /admin/homepage/
│   │   └── learn.php                # Learn API — knowledge base REST endpoints
│   ├── inc/                         # Admin PHP includes
│   │   ├── admin-menu.php           # AdminMenu — registers WP admin menu, enqueues React app
│   │   ├── store-checkout.php       # StoreCheckout — global WC checkout override management
│   │   └── wp-cli.php               # WP-CLI command registrations
│   ├── assets/
│   │   ├── build/                   # Compiled JS/CSS — NEVER edit directly
│   │   │   ├── settings-app.js      # Compiled settings/dashboard React app
│   │   │   └── editor-app.js        # Compiled flow editor React app
│   │   └── src/                     # React source
│   │       ├── SettingsApp.js       # Settings app entry point
│   │       ├── SettingsRoute.js     # React Router v5 routes
│   │       ├── settings-app/
│   │       │   ├── pages/
│   │       │   │   ├── HomePage.js  # Analytics dashboard (ApexCharts)
│   │       │   │   ├── FlowsPage.js # Flows list management
│   │       │   │   └── Analytics.js # Detailed analytics views
│   │       │   ├── hooks/
│   │       │   │   └── usePublishedFlows.js
│   │       │   └── data/
│   │       │       └── reducer.js   # Redux reducer for settings state
│   │       ├── common/
│   │       │   └── global-settings/ # Settings form components
│   │       ├── components/          # Reusable React components
│   │       ├── fields/              # Form field components
│   │       └── utils/               # Utility functions
│   └── views/                       # PHP view templates for admin pages
├── modules/                         # Feature modules (self-contained)
│   ├── flow/
│   │   ├── class-cartflows-flow.php # Flow module loader
│   │   └── classes/
│   │       ├── class-cartflows-flow-post-type.php  # CPT registration
│   │       └── class-cartflows-step-post-type.php  # Step CPT registration
│   ├── checkout/
│   │   ├── class-cartflows-checkout.php
│   │   └── classes/
│   │       ├── class-cartflows-checkout-ajax.php
│   │       ├── class-cartflows-checkout-fields.php
│   │       ├── class-cartflows-checkout-markup.php
│   │       ├── class-cartflows-checkout-meta-data.php
│   │       ├── class-cartflows-global-checkout.php
│   │       └── layouts/
│   │           ├── class-cartflows-instant-checkout.php
│   │           └── class-cartflows-modern-checkout.php
│   ├── thankyou/                    # Thank you page module
│   ├── optin/                       # Opt-in / lead capture module
│   ├── landing/                     # Landing page module
│   ├── elementor/                   # Elementor widgets (4 widgets)
│   ├── beaver-builder/              # Beaver Builder modules (2 modules)
│   ├── bricks/                      # Bricks builder elements (2 elements)
│   ├── gutenberg/                   # WordPress block editor (4 blocks)
│   ├── woo-dynamic-flow/            # Product-based automatic funnel routing
│   └── email-report/                # Scheduled funnel performance emails
├── compatibilities/
│   ├── class-cartflows-compatibility.php  # Loader for all compat classes
│   ├── plugins/                     # Per-plugin compatibility (Astra, Divi, etc.)
│   └── themes/                      # Per-theme compatibility
├── woocommerce/
│   └── template/                    # WooCommerce template overrides
├── wizard/                          # Setup wizard (separate React app)
│   ├── class-cartflows-wizard.php
│   └── inc/
│       └── wizard-core.php
├── libraries/                       # Third-party libraries (committed in repo)
│   ├── action-scheduler/            # Action Scheduler (async jobs)
│   ├── astra-notices/               # Admin notice library
│   ├── bsf-analytics/               # Usage analytics
│   └── cartflows-plugin-update-notifications/
├── assets/                          # Legacy frontend assets
│   ├── js/                          # Frontend JS (jQuery-based)
│   └── css/                         # Frontend CSS
├── admin/                           # Legacy admin assets (CSS, JS, images)
├── tests/
│   ├── php/                         # PHPUnit unit tests
│   ├── e2e/                         # Jest + Puppeteer E2E tests
│   └── play/                        # Playwright tests
└── vendor/                          # Composer dependencies (committed)
```

---

## 3. Core Concepts & Terminology

### Plugin Init Flow

1. `cartflows.php` — defines `CARTFLOWS_FILE`, requires `class-cartflows-loader.php`
2. `Cartflows_Loader::get_instance()` — called immediately; defines all `CARTFLOWS_*` constants, registers activation/deactivation hooks, loads `action-scheduler`, hooks `plugins_loaded` (priority 99) → `load_plugin()`
3. `load_plugin()` — checks WooCommerce, loads helper files, core files, core components; hooks `wp_loaded` → `initialize()`; fires `do_action('cartflows_init')`
4. `admin-loader.php` — registered via `include_once` inside `load_core_files()`; bootstraps `CartflowsAdmin\Admin_Loader` singleton with SPL autoloader; instantiates `ApiInit`, `AjaxInit`, `AdminMenu`, `WizardCore`, `StoreCheckout` (admin-only)
5. `do_action('cartflows_loaded')` fires immediately inside `Cartflows_Loader::get_instance()` after instantiation
6. `do_action('cartflows_init')` fires after all core files are loaded but before `wp_loaded`

### Global Accessor

```php
wcf()  // returns Cartflows_Loader::get_instance()

wcf()->utils    // Cartflows_Utils instance
wcf()->options  // Cartflows_Default_Meta instance
wcf()->logger   // Cartflows_Logger instance
wcf()->flow     // Cartflows_Flow_Frontend instance
wcf()->meta     // meta accessor (set during step rendering)
wcf()->alldata  // Cartflows_Tracking instance
wcf()->wcf_step_objs  // array of Cartflows_Step_Factory objects keyed by step ID
```

### Autoloader Convention (Admin Namespace)

`CartflowsAdmin\AdminCore\Ajax\Flows` → `admin-core/ajax/flows.php`

Rules: Strip `CartflowsAdmin\`, convert `\` to directory separator, convert `PascalCase` to `kebab-case`, lowercase. Handled by `Admin_Loader::autoload()`.

### Singleton Pattern

All legacy classes use:
```php
private static $instance;
public static function get_instance() {
    if ( ! isset( self::$instance ) ) {
        self::$instance = new self();
    }
    return self::$instance;
}
```

### Custom Post Types

| CPT | Constant | Public | Description |
|---|---|---|---|
| `cartflows_flow` | `CARTFLOWS_FLOW_POST_TYPE` | No | Funnel container — not publicly queryable |
| `cartflows_step` | `CARTFLOWS_STEP_POST_TYPE` | Yes | Individual page within a funnel |

### Step Types

Set as a term on `cartflows_step_type` taxonomy. Each step type maps to a module:

| Term | Module | Description |
|---|---|---|
| `landing` | `modules/landing/` | Sales/landing page (no WooCommerce required) |
| `checkout` | `modules/checkout/` | WooCommerce checkout page |
| `optin` | `modules/optin/` | Lead capture form |
| `thankyou` | `modules/thankyou/` | Post-purchase thank you page |
| `upsell` | Pro only | One-click upsell offer |
| `downsell` | Pro only | One-click downsell offer |

### Step Factory (`Cartflows_Step_Factory`)

Created per-step with `new Cartflows_Step_Factory( $step_id )`. Holds:
- `$step_id`, `$flow_id`, `$step_type`
- `$flow_steps` — ordered array of all step IDs in the flow
- `$flow_steps_map` — step-order map
- A/B test fields: `$ab_test`, `$all_variations`, `$control_step_id`

Stored in `wcf()->wcf_step_objs[ $step_id ]`.

### Checkout Layouts

| Layout | Class | Description |
|---|---|---|
| `default` / `standard` | Base | Standard single-column WooCommerce checkout |
| `modern` | `Cartflows_Modern_Checkout` | Two-column layout with sticky order summary |
| `instant` | `Cartflows_Instant_Checkout` | Full-screen distraction-free checkout |

### Store Checkout (Global Checkout)

When enabled, CartFlows replaces the WooCommerce default checkout page with a designated CartFlows checkout step globally. Managed by `CartflowsAdmin\AdminCore\Inc\StoreCheckout`. Toggle via `cartflows_update_store_checkout_status` AJAX action.

### WooCommerce Dynamic Flow

Module in `modules/woo-dynamic-flow/` — automatically routes customers into a specific funnel based on which product they are purchasing. Reads `cartflows_redirect_flow_id` product meta.

### Permalink Structure

Default: `/{flow-base}/{step-slug}` — configurable via `_cartflows_permalink` option.
- Default flow base: `wcf`
- Default step base: `wcf-step`
- Example: `https://example.com/wcf/my-funnel/checkout/`

Permalinks flushed via `flush_rewrite_rules()` when permalink settings change.

### A/B Test Steps

Steps can have A/B test variations (Pro). The factory class tracks `$control_step_id` and `$all_variations`. Cannot clone A/B test steps via the standard `cartflows_clone_step` AJAX action.

### Dynamic CSS

Each step generates scoped CSS stored in `wcf-dynamic-css` post meta. Cache-busted by `cartflows-assets-version` option (a Unix timestamp). Force regeneration via `cartflows_regenerate_css_for_steps` AJAX action (updates option to `time()`).

### AJAX Base Pattern

All admin AJAX handlers extend `CartflowsAdmin\AdminCore\Ajax\AjaxBase`:
```php
// Init in constructor:
$this->init_ajax_events( ['save_example', 'delete_example'] );

// Results in:
add_action( 'wp_ajax_cartflows_save_example', [ $this, 'save_example' ] );
```
Nonces are auto-generated and localised per action via `cartflows_admin_localized_vars` filter.

---

## 4. Key APIs & Extension Points

### PHP Action Hooks

**Plugin Lifecycle**

| Hook | Arguments | When |
|---|---|---|
| `cartflows_loaded` | — | Immediately after `Cartflows_Loader` constructed |
| `cartflows_init` | — | After all core files loaded (inside `load_plugin()`) |
| `cartflows_update_before` | — | Before automatic plugin update runs |
| `cartflows_update_after` | — | After automatic plugin update completes |
| `cartflows_pro_init` | — | Fired by CartFlows Pro when it initialises |

**Frontend Page Hooks**

| Hook | Arguments | When |
|---|---|---|
| `cartflows_wp` | `$step_id` | On `wp` action when current page is a CartFlows step |
| `cartflows_wp_footer` | — | In footer of CartFlows step pages |
| `cartflows_checkout_scripts` | — | Enqueue checkout-specific scripts |
| `cartflows_thank_you_scripts` | — | Enqueue thank you page scripts |

**Checkout Rendering**

| Hook | Arguments | When |
|---|---|---|
| `cartflows_checkout_form_before` | `$checkout_id` | Before the checkout form renders |
| `cartflows_checkout_cart_empty` | `$checkout_id` | When cart is empty on a checkout step |
| `cartflows_add_before_main_section` | `$checkout_layout` | Before the main checkout container |
| `cartflows_add_after_main_section` | — | After the main checkout container |
| `cartflows_primary_container_bottom` | — | Bottom of the primary checkout container |
| `cartflows_checkout_before_shortcode` | `$checkout_id` | Before checkout shortcode renders (editor preview) |

**Admin / AJAX**

| Hook | Arguments | When |
|---|---|---|
| `cartflows_admin_save_step_meta` | `$step_id` | After step meta saved via AJAX (also triggers CSS regen) |
| `cartflows_admin_save_global_settings` | `$setting_tab, $nonce_action` | After global settings saved |
| `cartflows_admin_after_delete_flow` | `$flow_id` | After a flow is permanently deleted |
| `cartflows_after_save_store_checkout` | — | After store checkout status updated |

**Template Importer**

| Hook | Arguments | When |
|---|---|---|
| `cartflows_import_complete` | — | After a template import batch completes |
| `cartflows_after_template_import` | `$new_step_id, $response` | After a single template is imported into a step |

**Tracking / Analytics**

| Hook | When |
|---|---|
| `cartflows_facebook_pixel_events` | Output custom Facebook Pixel events |
| `cartflows_google_analytics_events` | Output custom Google Analytics events |
| `cartflows_tiktok_pixel_events` | Output custom TikTok Pixel events |
| `cartflows_google_ads_events` | Output custom Google Ads events |
| `cartflows_pinterest_tag_events` | Output custom Pinterest Tag events |
| `cartflows_snapchat_pixel_events` | Output custom Snapchat Pixel events |

---

### PHP Filter Hooks

**Frontend / Rendering**

| Filter | Default | Signature |
|---|---|---|
| `cartflows_remove_theme_styles` | `true` | `apply_filters( 'cartflows_remove_theme_styles', true )` — return `false` to keep theme styles |
| `cartflows_remove_theme_scripts` | `true` | `apply_filters( 'cartflows_remove_theme_scripts', true )` |
| `cartflows_page_template` | step template file | `apply_filters( 'cartflows_page_template', $template )` |
| `cartflows_load_min_assets` | `false` | Return `true` to use minified assets |
| `cartflows_checkout_form_layout` | CSS class string | Modify CSS classes on checkout form wrapper |
| `cartflows_is_compatibility_theme` | `$bool` | Override whether theme triggers compatibility mode |
| `cartflows_maybe_load_font_awesome` | `true` | Control Font Awesome loading |
| `cartflows_do_not_cache_step` | `true` | Return `false` to allow caching on a specific step |
| `cartflows_enable_append_query_string` | `false` | Enable passing query strings between funnel steps |
| `cartflows_may_be_append_query_strings_args` | `$strings` | Modify query strings passed between steps |

**WooCommerce / Cart**

| Filter | Signature |
|---|---|
| `cartflows_selected_checkout_products` | `apply_filters( 'cartflows_selected_checkout_products', $products, $checkout_id )` |
| `cartflows_checkout_next_step_id` | `apply_filters( 'cartflows_checkout_next_step_id', $step_id, $order, $checkout_id )` |
| `cartflows_checkout_cart_empty_message` | `apply_filters( 'cartflows_checkout_cart_empty_message', $message )` |
| `cartflows_auto_prefill_checkout_fields` | `apply_filters( 'cartflows_auto_prefill_checkout_fields', true )` |
| `cartflows_supported_product_types_for_search` | `apply_filters( 'cartflows_supported_product_types_for_search', $types )` |
| `cartflows_skip_configure_cart` | `apply_filters( 'cartflows_skip_configure_cart', false )` |
| `cartflows_show_coupon_field` | `apply_filters( 'cartflows_show_coupon_field', $show )` |

**Checkout UI Text**

| Filter | Default |
|---|---|
| `cartflows_woo_billling_text` | Billing section heading |
| `cartflows_woo_your_order_text` | "Your Order" section heading |
| `cartflows_woo_shipping_text` | Shipping section heading |

**Admin Settings**

| Filter | Signature |
|---|---|
| `cartflows_admin_global_data_options` | `apply_filters( 'cartflows_admin_global_data_options', $options )` — modify data sent to settings app |
| `cartflows_admin_flows_step_data` | `apply_filters( 'cartflows_admin_flows_step_data', $steps )` — modify step data in flow API response |
| `cartflows_admin_action_slug` | `apply_filters( 'cartflows_admin_action_slug', $slug, $flow_id )` — change edit step action slug |
| `cartflows_admin_localized_vars` | `apply_filters( 'cartflows_admin_localized_vars', $vars )` — modify admin JS localized variables |
| `cartflows_admin_{type}_step_default_meta_fields` | `apply_filters( 'cartflows_admin_checkout_step_default_meta_fields', $fields, $step_id )` |
| `cartflows_admin_flow_settings` | `apply_filters( 'cartflows_admin_flow_settings', $settings )` |
| `cartflows_user_role_default_settings` | `apply_filters( 'cartflows_user_role_default_settings', $settings )` |

**Analytics / Tracking**

| Filter | Signature |
|---|---|
| `cartflows_enable_non_sensitive_data_tracking` | `apply_filters( 'cartflows_enable_non_sensitive_data_tracking', $bool )` |
| `cartflows_get_specific_stats` | `apply_filters( 'cartflows_get_specific_stats', $data )` |
| `cartflows_view_content_offer` | `apply_filters( 'cartflows_view_content_offer', $params, $step_id )` |
| `cartflows_is_offer_type` | `apply_filters( 'cartflows_is_offer_type', $bool )` |

**Template / Import**

| Filter | Signature |
|---|---|
| `cartflows_templates_url` | `apply_filters( 'cartflows_templates_url', $url )` — override template library API URL |
| `cartflows_licence_args` | `apply_filters( 'cartflows_licence_args', [] )` — add licence args to template API requests |
| `cartflows_template_import_meta_data` | `apply_filters( 'cartflows_template_import_meta_data', $meta )` |
| `cartflows_image_importer_skip_image` | `apply_filters( 'cartflows_image_importer_skip_image', false, $attachment )` |

**Misc**

| Filter | Signature |
|---|---|
| `cartflows_languages_directory` | `apply_filters( 'cartflows_languages_directory', $lang_dir )` |
| `cartflows_enable_setup_wizard` | `apply_filters( 'cartflows_enable_setup_wizard', $bool )` |
| `cartflows_file_mod_disabled` | `apply_filters( 'cartflows_file_mod_disabled', $bool )` |
| `cartflows_bsf_analytics_deactivation_survey_data` | Filter deactivation survey configuration |

---

### AJAX Endpoints

All actions use prefix `cartflows_` and are **admin-only** (`wp_ajax_` only, no `wp_ajax_nopriv_`). Security nonces are localised per-action under `{action}_nonce` key in `wcfEditorAppData` / `wcfSettingsData`.

**Flows (`admin-core/ajax/flows.php`) — requires `cartflows_manage_flows_steps`**

| Action | Key POST params | Returns |
|---|---|---|
| `cartflows_update_flow_title` | `flow_id`, `flow_title` | `{success: true}` |
| `cartflows_clone_flow` | `flow_id` | `{success: true, data: {flow_id, redirect_url}}` |
| `cartflows_delete_flow` | `flow_id` | `{success: true}` |
| `cartflows_trash_flow` | `flow_id` | `{success: true}` |
| `cartflows_restore_flow` | `flow_id` | `{success: true}` |
| `cartflows_reorder_flow_steps` | `flow_id`, `steps[]` | `{success: true}` |
| `cartflows_trash_flows_in_bulk` | `flow_ids[]` | `{success: true}` |
| `cartflows_update_flow_post_status` | `flow_ids[]`, `status` | `{success: true}` |
| `cartflows_delete_flows_permanently` | `flow_ids[]` | `{success: true}` |
| `cartflows_save_flow_meta_settings` | `flow_id`, `settings` | `{success: true}` |
| `cartflows_export_flows_in_bulk` | `flow_ids[]` | JSON file download |
| `cartflows_update_status` | `id`, `status` | `{success: true}` |
| `cartflows_update_store_checkout_status` | `status` (`enable`/`disable`) | `{success: true}` |
| `cartflows_hide_instant_checkout_notice` | — | `{success: true}` |
| `cartflows_get_published_flows` | — | `{success: true, data: flows[]}` |

**Steps (`admin-core/ajax/steps.php`) — requires `cartflows_manage_flows_steps`**

| Action | Key POST params | Returns |
|---|---|---|
| `cartflows_update_step_title` | `step_id`, `step_title` | `{success: true}` |
| `cartflows_clone_step` | `step_id`, `flow_id` | `{success: true}` |
| `cartflows_delete_step` | `step_id`, `flow_id` | `{success: true}` |
| `cartflows_save_meta_settings` | `step_id`, `settings` | `{success: true}` — also regenerates dynamic CSS; fires `cartflows_admin_save_step_meta` |

**Meta Data (`admin-core/ajax/meta-data.php`) — requires `cartflows_manage_flows_steps`**

| Action | Key POST params | Returns |
|---|---|---|
| `cartflows_json_search_products` | `term`, `allowed_products?`, `include_products?`, `exclude_products?`, `display_stock?` | `{success: true, data: products[]}` |
| `cartflows_json_search_coupons` | `term` | `{success: true, data: coupons[]}` |

**Common Settings (`admin-core/ajax/common-settings.php`) — requires `cartflows_manage_settings`**

| Action | Key POST params | Returns |
|---|---|---|
| `cartflows_save_global_settings` | `setting_tab`, tab-specific fields | `{success: true}` — fires `cartflows_admin_save_global_settings` |
| `cartflows_regenerate_css_for_steps` | — | `{success: true}` — updates `cartflows-assets-version` |
| `cartflows_track_kb_search` | `search_term` | `{success: true}` — stores in `cartflows_kb_searches` option |

---

### REST API Endpoints

Base URL: `/wp-json/cartflows/v1/`
Authentication: `X-WP-Nonce` header with WordPress nonce; `@wordpress/api-fetch` handles this automatically.

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| `POST` | `/admin/flows/` | `cartflows_manage_flows_steps` | List flows with pagination/filtering |
| `GET` | `/admin/flow-data/{id}` | `cartflows_manage_flows_steps` | Get single flow with steps and settings |
| `GET` | `/admin/step-data/{id}` | `cartflows_manage_flows_steps` | Get single step with all settings, tabs, links |
| `GET` | `/admin/commonsettings/` | `cartflows_manage_flows_steps` | Get global settings and field definitions |
| `GET` | `/admin/homepage/` | `cartflows_manage_flows_steps` | Get dashboard visibility config |
| `POST` | `/admin/setup-checklist/` | `cartflows_manage_flows_steps` | Get onboarding checklist data |

**List Flows request body params:**

| Param | Type | Default | Description |
|---|---|---|---|
| `status` | string | `publish` | `publish`, `draft`, `trash`, `any` |
| `page` | int | `1` | Page number |
| `per_page` | int | `10` | Items per page |
| `search` | string | `""` | Search term |
| `start_date` | string | — | Date range filter start |
| `end_date` | string | — | Date range filter end |
| `test_mode` | bool | `false` | Include test-mode flows |

---

## 5. Configuration & Settings

### Constants

| Constant | Value | Description |
|---|---|---|
| `CARTFLOWS_FILE` | `__FILE__` | Absolute path to `cartflows.php` |
| `CARTFLOWS_BASE` | `plugin_basename(CARTFLOWS_FILE)` | Plugin base for action links |
| `CARTFLOWS_DIR` | `plugin_dir_path(CARTFLOWS_FILE)` | Absolute directory path (trailing slash) |
| `CARTFLOWS_URL` | `plugins_url('/', CARTFLOWS_FILE)` | Plugin URL (trailing slash) |
| `CARTFLOWS_VER` | `'2.2.2'` | Current plugin version |
| `CARTFLOWS_SLUG` | `'cartflows'` | Plugin slug |
| `CARTFLOWS_SETTINGS` | `'cartflows_settings'` | Legacy general option key |
| `CARTFLOWS_NAME` | `'CartFlows'` | Plugin display name |
| `CARTFLOWS_REQ_CF_PRO_VER` | `'2.2.0'` | Minimum supported Pro version |
| `CARTFLOWS_LEGACY_ADMIN` | `false` | Always false; legacy admin UI disabled |
| `CARTFLOWS_ASSETS_VERSION` | `get_option('cartflows-assets-version')` | Timestamp for asset cache-busting |
| `CARTFLOWS_FLOW_POST_TYPE` | `'cartflows_flow'` | Flow CPT slug |
| `CARTFLOWS_STEP_POST_TYPE` | `'cartflows_step'` | Step CPT slug |
| `CARTFLOWS_FLOW_PERMALINK_SLUG` | `'flow'` | Default flow URL base |
| `CARTFLOWS_STEP_PERMALINK_SLUG` | `'step'` | Default step URL base |
| `CARTFLOWS_SERVER_URL` | `'https://my.cartflows.com/'` | CartFlows account/server URL |
| `CARTFLOWS_DOMAIN_URL` | `'https://cartflows.com/'` | CartFlows marketing site URL |
| `CARTFLOWS_TEMPLATES_URL` | `'https://templates.cartflows.com/'` | Template library base URL |
| `CARTFLOWS_LOG_DIR` | `{uploads}/cartflows-logs/` | Log file directory |
| `CARTFLOWS_ACTIVE_CHECKOUT` | `'wcf_active_checkout'` (or with prefix) | Cookie name for active checkout tracking |
| `CARTFLOWS_ADMIN_CORE_DIR` | `CARTFLOWS_DIR . 'admin-core/'` | Admin core directory (set in Admin_Loader) |
| `CARTFLOWS_ADMIN_CORE_URL` | `CARTFLOWS_URL . 'admin-core/'` | Admin core URL (set in Admin_Loader) |
| `CARTFLOWS_TAXONOMY_STEP_TYPE` | `'cartflows_step_type'` | Step type taxonomy |
| `CARTFLOWS_TAXONOMY_STEP_FLOW` | `'cartflows_step_flow'` | Step-to-flow relationship taxonomy |
| `CARTFLOWS_TAXONOMY_STEP_PAGE_BUILDER` | `'cartflows_step_page_builder'` | Page builder taxonomy |
| `CARTFLOWS_TAXONOMY_FLOW_CATEGORY` | `'cartflows_flow_category'` | Flow category taxonomy |

### WordPress Options

**Global Settings**

| Option | Type | Description |
|---|---|---|
| `_cartflows_common` | array | General: default page builder, store checkout config |
| `_cartflows_permalink` | array | URL slugs: flow base, step base, permalink mode |
| `_cartflows_roles` | array | Per-role capability assignments |

**Integrations**

| Option | Description |
|---|---|
| `_cartflows_facebook` | Facebook Pixel ID and event settings |
| `_cartflows_google_analytics` | Google Analytics tracking ID and settings |
| `_cartflows_google_auto_address` | Google Places Autocomplete API key |
| `_cartflows_google_ads` | Google Ads conversion ID and settings |
| `_cartflows_tiktok` | TikTok Pixel settings |
| `_cartflows_pinterest` | Pinterest Tag settings |
| `_cartflows_snapchat` | Snapchat Pixel settings |

**Reporting & Analytics**

| Option | Description |
|---|---|
| `cartflows_stats_report_emails` | `'enable'`/`'disable'` email reports |
| `cartflows_stats_report_email_ids` | Newline-separated report recipient emails |
| `cartflows_kb_searches` | Array of last 20 knowledge base search terms |
| `cf_analytics_optin` | `'yes'`/`'no'` non-sensitive data tracking |

**System**

| Option | Description |
|---|---|
| `cartflows-version` | Installed CartFlows version string |
| `cartflows-assets-version` | Unix timestamp for dynamic CSS cache-busting |
| `cartflows_delete_plugin_data` | `'yes'`/`'no'` delete data on uninstall |
| `cartflows_permalink_refresh` | Pending rewrite rules flush flag |
| `wcf_start_onboarding` | `true` on fresh activation — triggers wizard |

### Post Meta (Flow)

| Meta Key | Type | Description |
|---|---|---|
| `wcf-steps` | serialized array | Ordered array of step post IDs |
| `wcf-flow-title` | string | Flow title |
| `wcf-test-mode` | `'yes'`/`'no'` | Test mode flag |
| `wcf-gcp-primary-color` | hex string | Primary brand colour |
| `wcf-gcp-secondary-color` | hex string | Secondary colour |
| `wcf-gcp-primary-text-color` | hex string | Primary text colour |
| `wcf-gcp-text-color` | hex string | Field text colour |
| `wcf-gcp-accent-color` | hex string | Accent/heading colour |

### Post Meta (Step)

| Meta Key | Type | Description |
|---|---|---|
| `wcf-flow-id` | int | Parent flow post ID |
| `wcf-step-type` | string | Step type slug |
| `wcf-step-note` | string | Internal developer note |
| `wcf-dynamic-css` | string | Generated CSS for this step |
| `wcf-dynamic-css-version` | string | CSS cache version identifier |
| `wcf-checkout-layout` | string | `'modern'`, `'instant'`, or default |
| `wcf-custom-checkout-fields` | `'yes'`/`'no'` | Custom checkout fields enabled |
| `wcf-enable-product-options` | `'yes'`/`'no'` | Product option selection |
| `wcf-order-bump` | array | Order bump product configuration |
| `wcf-pre-checkout-offer` | array | Pre-checkout offer settings |
| `wcf-{field_name}` | `'yes'`/`'no'` | Whether a checkout field is enabled |
| `wcf-field-width-{field_name}` | `'full'`/`'half'` | Checkout field width |
| `wcf-field-required-{field_name}` | `'yes'`/`'no'` | Field required status |
| `wcf-field-label-{field_name}` | string | Custom field label |
| `wcf-field-placeholder-{field_name}` | string | Custom field placeholder |

### Post Meta (Product)

| Meta Key | Type | Description |
|---|---|---|
| `cartflows_redirect_flow_id` | int | Redirect this product's purchase into a specific flow |
| `cartflows_add_to_cart_text` | string | Custom "Add to Cart" button text |

### Transients

| Key | TTL | Description |
|---|---|---|
| `cartflows_flow_data_{id}` | Varies | Cached flow data |
| `cartflows_knowledge_base` | 12 hours | Cached KB articles from cartflows.com |

---

## 6. Data Structures

### Flow Object (REST API)

```json
{
  "id": 123,
  "title": "My Sales Funnel",
  "slug": "my-sales-funnel",
  "link": "https://example.com/wcf/my-sales-funnel",
  "status": "publish",
  "steps": [
    {
      "id": 456,
      "title": "Checkout",
      "type": "checkout",
      "slug": "checkout",
      "status": "publish"
    }
  ],
  "meta": { "wcf-test-mode": "no" },
  "settings": { ... }
}
```

### Step Object (REST API)

```json
{
  "id": 456,
  "title": "Checkout Step",
  "type": "checkout",
  "flow_id": 123,
  "flow_title": "My Sales Funnel",
  "tabs": { ... },
  "settings": { ... },
  "page_settings": { ... },
  "design_settings": { ... },
  "meta": { "wcf-checkout-layout": "modern", ... },
  "links": {
    "view": "https://example.com/wcf/my-funnel/checkout-step",
    "edit": "https://example.com/wp-admin/post.php?post=456&action=edit",
    "page_builder_edit": "https://example.com/wp-admin/..."
  }
}
```

### `wcfEditorAppData` (JS Localized Object — Editor App)

```js
window.wcfEditorAppData = {
    ajax_url:      '/wp-admin/admin-ajax.php',
    nonce:         '...',      // WP REST nonce (X-WP-Nonce header)
    nonces: {
        clone_flow:             '...',
        delete_flow:            '...',
        save_meta_settings:     '...',
        // ... one entry per AJAX action
    },
    flow_id:        123,
    step_id:        456,
    step_type:      'checkout',
    page_builder:   'elementor',   // active page builder slug
    pro_status:     'active' | 'inactive' | 'not-installed',
    // ... other app-specific data
}
```

### `wcfSettingsData` (JS Localized Object — Settings App)

```js
window.wcfSettingsData = {
    ajax_url:          '/wp-admin/admin-ajax.php',
    nonce:             '...',
    nonces:            { /* per-action nonce map */ },
    pro_status:        'active' | 'inactive' | 'not-installed',
    settings: {
        _cartflows_common:    { /* general settings */ },
        _cartflows_permalink: { /* permalink settings */ },
        _cartflows_facebook:  { /* Facebook settings */ },
        // ... all option groups
    },
    flows_count:        0,
    analytics_data:     { /* initial analytics */ },
    // ... other settings app data
}
```

### AJAX Request Format

```js
// jQuery
$.ajax( {
    url: wcfEditorAppData.ajax_url,
    method: 'POST',
    data: {
        action:   'cartflows_clone_flow',
        security: wcfEditorAppData.nonces.clone_flow,
        flow_id:  123,
    }
} );
```

### AJAX Response Format

```json
// Success
{ "success": true, "data": { ... } }

// Error
{ "success": false, "data": { "message": "Error description" } }
```

---

## 7. Coding Conventions

### PHP

- **Namespace**: `CartflowsAdmin` for admin layer; `CartflowsAdmin\AdminCore\Ajax`, `CartflowsAdmin\AdminCore\Api`, `CartflowsAdmin\Wizard` for sub-namespaces. Legacy core classes have **no namespace**.
- **Class naming**: `Cartflows_{Feature}` for legacy; `PascalCase` within namespace (e.g., `AjaxBase`, `FlowData`)
- **File naming**: `class-{name}.php` for legacy; `{name}.php` (kebab-case) within `admin-core/` namespace
- **Singleton**: `private static $instance; public static function get_instance()`
- **ABSPATH guard**: Every PHP file must start with `if ( ! defined( 'ABSPATH' ) ) { exit; }`
- **PHPDoc**: All classes, methods, and properties require PHPDoc with `@since`, `@param`, `@return`
- **Hooks prefix**: `cartflows_` for all actions/filters/options
- **Sanitization**: `absint()` for IDs, `sanitize_text_field( wp_unslash( ... ) )` for strings, `sanitize_hex_color()` for colors, `esc_url_raw()` for URLs
- **Escaping**: `esc_html()`, `esc_attr()`, `wp_kses_post()`, `esc_url()` for output
- **Nonce verify**: `check_ajax_referer( 'cartflows_{action}', 'security' )` for AJAX
- **Capability check**: `current_user_can( 'cartflows_manage_flows_steps' )` before flow/step operations
- **DB queries**: Always `$wpdb->prepare()` — never concatenate user input
- **Logging**: `wcf()->logger->log()` for debug — never `error_log()` in production
- **i18n**: `__( 'Text', 'cartflows' )` — text domain is always `'cartflows'` (lowercase)
- **Standards**: WPCS, PHPStan level 9, `composer lint` / `composer format` / `composer phpstan`
- **Indentation**: Tabs (not spaces) for PHP

### JavaScript / React

- **Components**: Functional components only; PascalCase files (e.g., `OrderBumpProduct.js`)
- **Extensions**: `.js` only (not `.jsx`)
- **Imports**: Path aliases — `@Admin`, `@Components`, `@Fields`, `@Utils`; group React → WordPress → third-party → local
- **State**: Redux for global state; `useState` for local; `useContext` for feature-scoped shared state
- **API calls**: `apiFetch` from `@wordpress/api-fetch` — never raw `fetch`/`$.ajax` in React
- **i18n**: `__( 'Text', 'cartflows' )` with text domain always `'cartflows'`
- **Reducer**: Single `CHANGE` action type in settings app reducer replaces the full slice
- **Event emitter**: `settingsEvents` for cross-component communication not suited to Redux
- **Linting**: `npm run lint-js` (ESLint + `@wordpress/eslint-plugin/recommended-with-formatting`)
- **Format**: Prettier (`@wordpress/prettier-config`), semicolons, single quotes, trailing commas
- **No `console.log`** in production code

### CSS / SCSS

- **Class prefix**: `wcf-` or `cartflows-` for all custom classes
- **Admin UI**: Tailwind CSS 3 utilities
- **BEM naming** for legacy CSS: `.wcf-component`, `.wcf-component__element`, `.wcf-component--modifier`
- **RTL**: Auto-generated via `grunt-rtlcss` — use logical properties where possible
- **Linting**: `npm run lint-css` (Stylelint + `@wordpress/stylelint-config`)

### Module Structure

Each module in `modules/{module-name}/` follows:
```
{module-name}/
├── class-cartflows-{module}.php     # Module loader/entry
└── classes/
    └── class-cartflows-{module}-*.php  # Feature classes
```

### AJAX Handler Structure

```php
namespace CartflowsAdmin\AdminCore\Ajax;

class MyFeature extends AjaxBase {
    public function __construct() {
        $this->init_ajax_events( ['save_my_thing', 'delete_my_thing'] );
    }

    public function save_my_thing() {
        check_ajax_referer( 'cartflows_save_my_thing', 'security' );
        if ( ! current_user_can( 'cartflows_manage_flows_steps' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'cartflows' ) ] );
        }
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        // process...
        wp_send_json_success( $result );
    }
}
```

### REST API Handler Structure

```php
namespace CartflowsAdmin\AdminCore\Api;

class MyEndpoint extends ApiBase {
    public function register_routes() {
        register_rest_route(
            $this->get_api_namespace(),
            '/admin/my-endpoint/',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => [ $this, 'get_items_permissions_check' ],
                ],
            ]
        );
    }

    public function get_items_permissions_check( $request ) {
        return current_user_can( 'cartflows_manage_flows_steps' );
    }

    public function get_items( $request ) {
        return new \WP_REST_Response( [ 'success' => true, 'data' => [] ], 200 );
    }
}
```

---

## 8. Common Tasks with Code Examples

### Get a global CartFlows setting

```php
// General settings
$common = Cartflows_Helper::get_admin_settings_option( '_cartflows_common' );
$page_builder = isset( $common['default_page_builder'] ) ? $common['default_page_builder'] : 'gutenberg';

// Single value shortcut
$page_builder = Cartflows_Helper::get_common_setting( 'default_page_builder' );

// Or via the global
$page_builder = wcf()->get_site_slug();
```

### Check if the current page is a CartFlows step

```php
if ( wcf()->utils->is_step_post_type() ) {
    // current page is a cartflows_step
}

if ( wcf()->utils->is_flow_post_type() ) {
    // current page is a cartflows_flow
}
```

### Check if WooCommerce is active

```php
if ( wcf()->is_woo_active ) {
    // WooCommerce is active
}
// or
if ( function_exists( 'WC' ) ) { ... }
```

### Redirect checkout to a custom thank you page

```php
add_filter( 'cartflows_checkout_next_step_id', function( $step_id, $order, $checkout_id ) {
    if ( $checkout_id === 123 ) {
        return 456; // custom thank you step ID
    }
    return $step_id;
}, 10, 3 );
```

### Keep theme styles on CartFlows pages

```php
add_filter( 'cartflows_remove_theme_styles', '__return_false' );
add_filter( 'cartflows_remove_theme_scripts', '__return_false' );
```

### Add a custom product to the cart for a specific checkout step

```php
add_filter( 'cartflows_selected_checkout_products', function( $products, $checkout_id ) {
    if ( 999 === $checkout_id ) {
        $products[] = wc_get_product( 42 );
    }
    return $products;
}, 10, 2 );
```

### Run code after a step's settings are saved

```php
add_action( 'cartflows_admin_save_step_meta', function( $step_id ) {
    // e.g., clear a custom transient
    delete_transient( 'my_plugin_cache_' . $step_id );
} );
```

### Run code after global settings are saved

```php
add_action( 'cartflows_admin_save_global_settings', function( $setting_tab, $nonce_action ) {
    if ( 'general' === $setting_tab ) {
        // Handle general settings save
    }
}, 10, 2 );
```

### Add content to the admin localized JS vars

```php
add_filter( 'cartflows_admin_localized_vars', function( $vars ) {
    $vars['my_plugin_data'] = [ 'key' => 'value' ];
    return $vars;
} );
```

### Extend the REST flows list response

```php
add_filter( 'cartflows_admin_flows_step_data', function( $steps ) {
    foreach ( $steps as &$step ) {
        $step['my_custom_field'] = get_post_meta( $step['id'], 'my_meta_key', true );
    }
    return $steps;
} );
```

### Add custom data to global settings response

```php
add_filter( 'cartflows_admin_global_data_options', function( $options ) {
    $options['my_integration'] = get_option( 'my_plugin_settings', [] );
    return $options;
} );
```

### Override the template library URL

```php
add_filter( 'cartflows_templates_url', function( $url ) {
    return 'https://my-custom-templates.com/';
} );
```

### Log a debug message

```php
wcf()->logger->log( 'My debug message', 'info' );
// Logs to {uploads}/cartflows-logs/
```

### Register a new REST endpoint

```php
add_action( 'rest_api_init', function() {
    // Register via extending ApiBase, or directly:
    register_rest_route( 'cartflows/v1', '/my-endpoint/', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'my_callback',
        'permission_callback' => function() {
            return current_user_can( 'cartflows_manage_flows_steps' );
        },
    ] );
} );
```

### Call the REST API from React

```js
import apiFetch from '@wordpress/api-fetch';

const response = await apiFetch( {
    path: '/cartflows/v1/admin/flow-data/123',
    method: 'GET',
} );
// response.data.steps => array of steps
```

### Call a CartFlows AJAX action from jQuery

```js
$.ajax( {
    url: wcfEditorAppData.ajax_url,
    method: 'POST',
    data: {
        action:   'cartflows_clone_flow',
        security: wcfEditorAppData.nonces.clone_flow,
        flow_id:  123,
    },
    success: function( response ) {
        if ( response.success ) {
            window.location.href = response.data.redirect_url;
        }
    }
} );
```

### Hook in after CartFlows is loaded

```php
add_action( 'cartflows_loaded', function() {
    // wcf() is available here, but WooCommerce check has not yet run
} );

add_action( 'cartflows_init', function() {
    // All core files are loaded; safe to access all CartFlows classes
} );
```

---

## 9. Known Gotchas & Constraints

1. **WooCommerce required for checkout/thankyou/optin modules**: `load_core_files()` wraps these in `if ( $this->is_woo_active )`. Landing pages work without WooCommerce.

2. **Pro version gate**: If `cartflows-pro` is installed but `CARTFLOWS_PRO_VER < CARTFLOWS_REQ_CF_PRO_VER` (2.2.0), an admin notice is shown and Pro features will not function correctly.

3. **Two separate autoloaders**: Legacy core classes (`classes/`) have no namespace and are loaded with manual `include_once`. The `CartflowsAdmin` namespace uses `Admin_Loader::autoload()`. These two systems are completely independent — do not mix them up.

4. **`wcf()` is available after `plugins_loaded` priority 99**: The global `wcf()` function is registered at the top of the loader, but all classes are loaded at `plugins_loaded` priority 99. Code hooking at earlier priority may find classes uninstantiated.

5. **Admin-only classes**: `CartflowsAdmin\AdminCore\Inc\AdminMenu`, `AjaxInit`, `WizardCore`, `StoreCheckout` are only instantiated inside `is_admin()`. Never reference them from frontend code.

6. **AJAX actions are admin-only**: All CartFlows AJAX handlers register only `wp_ajax_` (not `wp_ajax_nopriv_`). There are no unauthenticated AJAX endpoints.

7. **Nonce key is `security`**: CartFlows AJAX handlers use `check_ajax_referer( 'cartflows_{action}', 'security' )` — the POST field is `security`, not `nonce` or `_wpnonce`. Do not mix this up with WooCommerce patterns.

8. **`wcf-steps` meta is serialized PHP**: The flow's step order is stored as a serialized array in `wcf-steps` post meta. Always use `get_post_meta( $flow_id, 'wcf-steps', true )` and treat the result as an array.

9. **Dynamic CSS is post-meta cached**: Step CSS is regenerated on `cartflows_save_meta_settings` AJAX call. If your code changes meta that affects CSS, call the `cartflows_regenerate_css_for_steps` AJAX action or update the `cartflows-assets-version` option directly.

10. **Compiled assets are not source**: Never edit `admin-core/assets/build/settings-app.js` or `editor-app.js`. Edit source in `admin-core/assets/src/` and run `npm run build`.

11. **`vendor/` and `node_modules/` are committed**: The Composer `vendor/` directory and `libraries/` are included in the repo for distribution. Do not gitignore them on the distribution branch.

12. **PHPStan baseline is frozen**: `phpstan-baseline.neon` suppresses known existing issues. New code must not add new entries. Run `composer phpstan` before committing any PHP changes.

13. **Legacy admin flag**: `CARTFLOWS_LEGACY_ADMIN` is always `false`. Do not check this constant to gate new admin features — it only exists for backward compatibility with very old Pro versions.

14. **Text domain is `cartflows` — never `CartFlows`**: ESLint enforces this in JavaScript. PHPCS enforces it in PHP. Capitalised or alternate domain strings will fail linting.

15. **Action Scheduler is loaded early**: `libraries/action-scheduler/action-scheduler.php` is required in `Cartflows_Loader::__construct()` — before `plugins_loaded` — because Action Scheduler needs to hook at `plugins_loaded` itself. This is intentional and follows the Action Scheduler usage documentation.

16. **Permalink slugs are configurable but default to `wcf`**: When checking if a URL is a CartFlows page, never hardcode `/wcf/` — always use `CARTFLOWS_FLOW_PERMALINK_SLUG` or `Cartflows_Helper::get_common_setting('flow_permalink')`.

17. **Step taxonomy carries parent flow ID as term slug**: `cartflows_step_flow` terms have the flow's post ID as their slug. Use `get_the_terms( $step_id, CARTFLOWS_TAXONOMY_STEP_FLOW )` to get the parent flow ID.

18. **A/B test steps have restrictions**: Cannot be cloned via `cartflows_clone_step`; have their own `cartflows_step_delete_ab_test` deletion logic. Check `Cartflows_Step_Factory::$ab_test` before assuming standard step operations apply.

19. **`cartflows_init` fires before WooCommerce is fully initialised**: Hook critical WooCommerce-dependent code at `woocommerce_init` or later, not at `cartflows_init`.

20. **Two React apps — separate builds**: The editor app (`admin-core/assets/build/editor-app.js`) and settings app (`admin-core/assets/build/settings-app.js`) are built independently via Webpack. Changes to shared components require rebuilding both. `npm run build` builds both.

---

## 10. Testing & Debugging

### Enable Debug Logging

```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

CartFlows logs go to `{uploads}/cartflows-logs/` (protected by `.htaccess deny from all`). Read via:
```php
wcf()->logger->log( 'My message', 'info' );     // info
wcf()->logger->log( 'Error detail', 'error' );  // error
```

### Running Tests

```bash
# PHPUnit
composer test

# PHPStan (level 9)
composer phpstan

# PHPCS
composer lint

# Auto-fix PHPCS
composer format

# ESLint
npm run lint-js
npm run lint-js:fix

# Stylelint
npm run lint-css
npm run lint-css:fix

# Prettier
npm run pretty:fix

# E2E (Jest + Puppeteer)
npm run env:start     # Start wp-env Docker
npm run test:e2e

# Playwright
npm run play:run
npm run play:run:interactive
```

### Test File Locations

| Test Type | Location | Framework |
|---|---|---|
| PHP unit | `tests/php/` | PHPUnit 8 |
| E2E | `tests/e2e/` | Jest + `@wordpress/e2e-test-utils` |
| Playwright | `tests/play/` | Playwright |

### Checking Plugin State

```php
// Is plugin loaded?
if ( class_exists( 'Cartflows_Loader' ) ) { ... }

// Is WooCommerce active?
if ( wcf()->is_woo_active ) { ... }

// Is CartFlows Pro active?
if ( defined( 'CARTFLOWS_PRO_VER' ) ) { ... }

// Get Pro activation status programmatically
// Check for CARTFLOWS_PRO_VER constant and version_compare against CARTFLOWS_REQ_CF_PRO_VER

// Is current page a CartFlows step?
if ( wcf()->utils->is_step_post_type() ) { ... }

// Get current step ID on frontend
$step_id = wcf()->utils->get_step_id();

// Get current step type
$step_type = wcf()->utils->get_step_type( $step_id );
```

### Debugging AJAX Responses

All AJAX responses return JSON. Use browser DevTools Network tab, filter by `admin-ajax.php`. Key debug patterns:
- Check `response.success` for boolean pass/fail
- Check `response.data.message` for error text
- Set `security` POST field to the action-specific nonce from `wcfEditorAppData.nonces.{action_name}`

### Debugging REST API

```bash
# Test endpoint with WP-CLI
wp --path=/path/to/wp eval "echo rest_url( 'cartflows/v1/admin/flows/' );"

# Or use browser DevTools: filter Network by /cartflows/v1/
```

### React DevTools

Both apps support Redux DevTools browser extension. State is stored under the `cartflows` Redux store key. Enable React DevTools for component inspection.

### CSS Regeneration

If dynamic CSS changes aren't appearing:
```php
// Force regeneration by bumping the assets version
update_option( 'cartflows-assets-version', time() );
```

Or use the admin AJAX action:
```js
$.ajax( {
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'cartflows_regenerate_css_for_steps',
        security: wcfEditorAppData.nonces.regenerate_css_for_steps,
    }
} );
```

### Admin Settings Page

Scripts/styles are only enqueued when `$hook` matches the CartFlows admin page hook. React app mounts in the DOM element rendered by `AdminMenu`. The settings app is at `/wp-admin/admin.php?page=cartflows`.

### Onboarding State

```php
// Check if onboarding wizard should show
if ( get_option( 'wcf_start_onboarding' ) ) {
    // Fresh install, wizard not yet completed
}
```

---

## 11. Analytics Dashboard Reference

The CartFlows admin dashboard (`HomePage.js`) renders the following metric groups:

**Conversion Metrics**: `TotalConversions`, `TotalPageViews`, `MobileConversions`, `LaptopConversions`, `AverageOrderValue`

**Revenue Metrics**: `RevenuePerUniqueVisitor`, `RevenuePerVisit`, `OfferRevenue`, `BumpOfferRevenue`

**Offer Metrics**: `OfferConversions`, `BumpConversions`

**Optin Metrics**: `OptinListGrowth`, `OptinTotalSubmissions`, `OptinConversionRate`

**Widgets**: `TopPerformingFunnels`, `QuickActions`, `RecentOrders`, `ExtendYourStore`

Charts use **ApexCharts v4** (`react-apexcharts` wrapper) — area/line for time-series, bar for comparisons.

Routes: `/` (dashboard), `/flows` (flows list), `/analytics` (detailed analytics), `/settings` (global settings).
