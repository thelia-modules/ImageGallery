# 1.0.0

Initial release. ImageGallery is the Thelia 3 successor of the [Carousel](https://github.com/mabruchet/Carousel)
module (which keeps targeting Thelia 2) — see « Migrating from the Carousel module » in the Readme.

## Features
- Slides organized by **group**, each carrying a mandatory **desktop and mobile image** (art direction),
  a link with target, a CTA label and an optional publication window (start/end dates).
- Responsive images: `srcset` variants served through `<picture>`, generated on the fly by the Thelia
  image cache.
- Twig back-office: one data table per group, drag & drop ordering, per-slide edit page (WYSIWYG,
  publication window, per-language edition), CSRF everywhere.
- Per-group live preview with three viewport presets (Desktop 1280px / Tablet 768px / Mobile 390px),
  transparently rendering the theme's own gallery component (auto-detected) with its Encore assets. The
  iframe is reloaded on every preset switch so theme components re-measure themselves at the new width.
- `image_gallery:seed` console command generating demo slides (desktop **and** mobile images via GD, the four
  publication states, fr/en translations), idempotent and purgeable per group.
- API Platform resources: `GET /api/front/image-galleries` (published only) and `/api/admin/image-galleries`.
- Front integration left to the theme: `image_gallery` theme hook with a neutral default template, and a
  reusable Twig component designed for theme extension (class extension or template override).
- Security: strict upload validation (server-detected MIME), forced publication filter on front endpoints,
  rich text sanitization.
- **Extension points, so a project module never has to fork this one** — see the Readme,
  « Extending the module (no fork needed) »:
  - business events `image_gallery.slide.create|update|delete` (pre-save, in the transaction) and
    `image_gallery.slide.created|updated|deleted` (post-save, after commit), carrying the slide model, the
    complete validated form data (extra fields included) and the edition locale;
  - list events `image_gallery.query.extend` (alter the Propel query, with a back-office context flag) and
    `image_gallery.row.enrich` (add keys to the presented row), dispatched by `ImageGalleryPresenter` for the
    back-office tables, the front Twig component and the theme hook alike;
  - back-office rendering slots `imagegallery.configuration.top|bottom`,
    `imagegallery.slide-edit.extra-fields` (inside the edition form, inputs named
    `image_gallery_slide[<field>]`) and `imagegallery.slide-edit.js`, declared through
    `ImageGallery::getHooks()` with `by_module = 0` so third-party modules can attach to them;
  - form fields added through `TheliaEvents::FORM_AFTER_BUILD . '.image_gallery_slide'` (and
    `.image_gallery_create`) reach the business events untouched. API resources keep their standard API
    Platform / Propel-bridge extension contract, not duplicated here.

## Deprecated
- The legacy `image_gallery` Smarty loop (inherited from Carousel) is deprecated, removal planned in 2.0.
