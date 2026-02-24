# CartFlows

WordPress plugin (v2.2.1) — Custom checkout pages & sales funnels for WooCommerce.
**Full coding standards:** see `agent.md` in this root.

## Tech Stack

- PHP (WPCS, PHPStan level 9, PHPUnit 8)
- React 18, Redux 4, React Router 5
- Tailwind CSS 3, @wordpress/scripts (Webpack), Grunt
- Playwright + wp-env (E2E)

## Commands

```bash
# PHP
composer lint          # PHPCS — WordPress Coding Standards
composer format        # PHPCBF auto-fix
composer phpstan       # PHPStan level 9 static analysis
composer test          # PHPUnit unit tests
php -l <file>          # Quick syntax check

# JavaScript
npm run build          # Production build (wp-scripts + grunt minify)
npm run start          # Dev watch mode
npm run lint-js        # ESLint
npm run lint-js:fix    # ESLint auto-fix
npm run lint-css       # Stylelint
npm run lint-css:fix   # Stylelint auto-fix
npm run pretty:fix     # Prettier auto-format

# E2E / Playwright
npm run env:start      # Start wp-env Docker environment
npm run test:e2e       # Jest E2E tests
npm run play:run       # Playwright tests

# i18n
npm run i18n           # Generate .pot file
```

## Project Structure

```
cartflows/
├── cartflows.php           # Plugin entry — constants + loader
├── classes/                # Core PHP classes (singleton pattern)
├── admin-core/
│   ├── ajax/               # AJAX handlers (extend Cartflows_Ajax_Base)
│   ├── api/                # REST endpoints (extend Cartflows_API_Base)
│   ├── assets/src/         # React source (components/, pages/, store/)
│   └── views/              # PHP view templates
├── modules/                # Feature modules (checkout, flow, optin, thankyou…)
├── compatibilities/        # Theme & plugin compatibility classes
├── woocommerce/template/   # WooCommerce template overrides
├── wizard/                 # Setup wizard
├── tests/
│   ├── php/                # PHPUnit tests
│   ├── e2e/                # Jest + Puppeteer E2E
│   └── play/               # Playwright tests
└── agent.md                # Full AI coding standards reference
```

## Coding Standards

**PHP:**
- Every file: `if ( ! defined( 'ABSPATH' ) ) { exit; }`
- Classes: `Cartflows_{Feature}` with singleton `get_instance()`
- Tabs for indentation; PHPDoc on all classes/methods
- Hooks prefixed `cartflows_`; always verify nonces, check capabilities, sanitize input, escape output

**JavaScript/React:**
- Functional components only; JSDoc on all exported functions
- Imports via path aliases: `@Admin`, `@Components`, `@Fields`, `@Utils`
- i18n: `__( 'Text', 'cartflows' )` — text domain is always `'cartflows'`
- API calls via `apiFetch` from `@wordpress/api-fetch`
- Redux for global state; `useState` for local state

**CSS:**
- Class prefix: `wcf-` or `cartflows-`; BEM naming for legacy CSS
- Tailwind utilities in admin UI
- RTL generated automatically via `grunt-rtlcss`

## Patterns

- Singleton pattern for all PHP classes
- Factory pattern for step types (`class-cartflows-step-factory.php`)
- AJAX handlers extend `Cartflows_Ajax_Base`
- REST handlers extend `Cartflows_API_Base`
- Module loaders: `class-cartflows-{module}-loader.php`
- PHP files: `class-{name}.php`; JS components: `PascalCase.js`

## Security Checklist (every PR)

- [ ] All `$_GET`/`$_POST` sanitized (`absint`, `sanitize_text_field`, `wp_unslash`)
- [ ] All output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- [ ] Nonces verified (`check_ajax_referer` or `wp_verify_nonce`)
- [ ] Capability checked (`current_user_can`)
- [ ] `$wpdb->prepare()` for all custom queries
- [ ] No `console.log` or `error_log` left in code

## Gotchas

- Existing code is assumed to pass PHPCS/PHPStan unless told otherwise — don't auto-fix unrelated issues
- Default behavior is **patch-only**: no refactors or architectural changes unless explicitly requested
- Build output goes to `admin-core/assets/build/` — never edit build files directly
- PHPStan baseline is `phpstan-baseline.neon` — new code must not add entries to it
- WooCommerce compat tested up to 9.8.5; Elementor up to 3.28.4
- Text domain is always `cartflows` (lowercase) — never `CartFlows`
- Pre-commit hook runs lint checks (`npm run lint-js:fix` + `composer format` before committing)
- `.claude/settings.local.json` is gitignored — use it for personal overrides

## Current Focus

Working on: ...
Next up: ...

## Imports

<!-- Org standards loaded via: /plugin install bsf-developers -->
<!-- Agents, commands, rules, hooks, and context skills are available via the bsf-developers plugin -->
