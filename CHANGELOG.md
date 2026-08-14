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
- Per-group live preview with desktop/mobile widths, transparently rendering the theme's own gallery
  component (auto-detected) with its Encore assets.
- API Platform resources: `GET /api/front/image-galleries` (published only) and `/api/admin/image-galleries`.
- Front integration left to the theme: `image_gallery` theme hook with a neutral default template, and a
  reusable Twig component designed for theme extension (class extension or template override).
- Security: strict upload validation (server-detected MIME), forced publication filter on front endpoints,
  rich text sanitization.

## Deprecated
- The legacy `image_gallery` Smarty loop (inherited from Carousel) is deprecated, removal planned in 2.0.
