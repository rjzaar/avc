# AVC Theme Architecture

## Theme Inheritance Chain

```
Drupal Core (Stable/Starterkit)
  └── socialbase (Open Social base theme)
        └── socialblue (Open Social default theme)
              └── avc_theme (AV Commons custom theme)
```

### socialbase

Open Social's base theme. Provides foundational templates, regions, and CSS for community platform features (activity streams, groups, profiles, events). Not intended for direct use.

### socialblue

Open Social's default frontend theme built on Material Design principles. Provides the responsive UI, color scheme, component library, and region structure that AVC inherits.

### avc_theme

AV Commons custom theme extending socialblue. Located at `themes/avc_theme/`.

**Customisations:**
- Custom branding and colour scheme via `brand-override` library
- Global styling overrides via `global` library
- Inherits all socialblue regions (header, hero, content, sidebar, footer, etc.)

**Key files:**
- `avc_theme.info.yml` - Theme definition, base theme declaration, library references
- `avc_theme.libraries.yml` - CSS/JS asset definitions
- `css/` - Custom stylesheets
- `templates/` - Twig template overrides (if any)

## Libraries

### `avc_theme/global`
Loaded on every page. Contains site-wide styling overrides.

### `avc_theme/brand-override`
Extends `socialblue/brand` library. Overrides Open Social's default brand colours and logo.

## Regions

AVC theme inherits all socialblue regions:

| Region | Purpose |
|--------|---------|
| `header_top` | Top bar area |
| `header` | Main navigation |
| `hero` | Hero banner area |
| `content_top` | Above main content |
| `content` | Main content area |
| `sidebar_first` | Left sidebar |
| `sidebar_second` | Right sidebar |
| `footer` | Site footer |

## Customisation Guide

To override a template from socialblue:
1. Copy the template from `themes/socialblue/templates/` to `themes/avc_theme/templates/`
2. Modify as needed
3. Clear cache: `drush cr`

To add new CSS:
1. Add stylesheet to `themes/avc_theme/css/`
2. Reference in `avc_theme.libraries.yml`
3. Attach via `avc_theme.info.yml` libraries or a preprocess hook

*Last Updated: 2026-02-22*
