# ImageGallery

Manage image galleries from the Thelia back-office: slides are organized by **group**, each slide carries a
**desktop image** and a **mobile image** (both mandatory, rendered through `<picture>`), a link with target,
a CTA label, and an optional **publication window** (start/end dates). The configuration screen provides drag & drop
ordering, per-slide edition, and a live preview of the front rendering at desktop, tablet and mobile widths.

> **Thelia 3 only** (module 1.0.0): requires Thelia 3 with the Twig back-office
> (`active-admin-template = default-twig`). This module is the successor of the
> [Carousel](https://github.com/mabruchet/Carousel) module, which keeps targeting Thelia 2 —
> see « Migrating from the Carousel module » below.

## Installation

* Copy the module into `<thelia_root>/local/modules/` (the directory must be named `ImageGallery`), or require it
  with composer (`thelia/image-gallery-module`).
* Activate it in the administration panel (this creates the `image_gallery` tables).

## Upgrading

**Always clear the Symfony cache after updating the module**, before browsing the back-office:

```bash
php bin/console cache:clear
```

The compiled container holds the service definitions of the previous version: until it is rebuilt, Symfony
injects arguments matching the *old* constructor signatures, which fails at boot with a `TypeError` as soon as
a service of the module changed its dependencies (observed in development after a `Presenter` refactor). The
same applies to the compiled Twig templates and to the module's compiler pass (preview component detection).

## Migrating from the Carousel module

Coming from Carousel (any 2.x, or the never-released 3.0.0 rewrite this module is born from)? The data model
is identical, only the names change. With the ImageGallery module present **but not activated**:

```sql
RENAME TABLE carousel TO image_gallery, carousel_i18n TO image_gallery_i18n;
-- If coming from Carousel 2.x, first make sure the 3.0 columns exist
-- (mobile_file, link_target, button_label — see Carousel's Config/update/3.0.0.sql).
UPDATE module SET code = 'ImageGallery', full_namespace = 'ImageGallery\\ImageGallery', version = '1.0.0'
WHERE code = 'Carousel';
UPDATE module_i18n mi JOIN module m ON m.id = mi.id SET mi.title = 'ImageGallery' WHERE m.code = 'ImageGallery';
```

Then move the media directory and purge the image cache:

```bash
mv local/media/images/carousel local/media/images/imagegallery
rm -rf public/cache/images/carousel
```

Remove the Carousel module directory (or keep it deactivated), clear the Thelia caches, and update any theme
code that referenced `Carousel\` PHP classes (e.g. a component extending
`Carousel\Twig\Components\Carousel` now extends `ImageGallery\Twig\Components\ImageGallery`). Mobile images
are **mandatory**: legacy slides without one keep rendering on the front (desktop fallback) but cannot be
saved in the back-office until a mobile image is uploaded.

> **Server hardening (recommended).** Thelia's image cache exposes the original files under
> `public/cache/images/…` (by symlink with the default `original_image_delivery_mode`). Make sure the web
> server never executes PHP from that directory — e.g. nginx `location ~* /cache/.*\.php$ { deny all; }`. The
> module already refuses non-image uploads and derives stored extensions from the server-detected MIME type,
> so this is defense in depth, not the primary control.

## Back-office

The configuration screen lives on the module page (`/admin/module/ImageGallery`, also linked from the Tools menu):

* one table per group, ordered by drag & drop;
* slide creation requires **both** the desktop and the mobile image;
* per-slide edit page: content (title, chapo, description with WYSIWYG, postscriptum), link + CTA label,
  desktop/mobile images with instant preview, visibility toggle and publication window, per-language edition.
  Saving is blocked (danger flash) while the slide has no mobile image — this only happens on legacy 2.x rows;
* preview card per group rendering the gallery inside an iframe, switchable between three viewport presets
  — **Desktop 1280px / Tablet 768px / Mobile 390px**. The iframe width *is* the inner viewport, so the
  theme's media queries really apply. Switching a preset **reloads the iframe**: theme components (sliders,
  Stimulus controllers) measure themselves on mount, so a plain resize would leave them laid out for the
  previous width. The preset list lives in
  `templates/backOffice/default-twig/imagegallery/hook/module-configuration.html.twig` (the first entry is
  the default width, read back from the DOM by the JS) — add or reorder presets there, nothing to change in
  the JS.

### Preview rendering (transparent for administrators)

The preview automatically shows the **actual front rendering** when the active theme ships its own gallery
component: a compiler pass (`DependencyInjection/PreviewComponentPass.php`) detects any registered Twig
component **extending the module's base component** (the documented override contract, e.g.
`Flexy:ImageGallery`) and the preview renders it with the theme's `app` Encore entry (detected in the front
theme's `entrypoints.json`). No configuration, nothing to do: if the theme has no gallery component, the
preview falls back to the module's neutral template.

Developer overrides (module config values, no UI — normally unnecessary): `preview_component` forces the
component name (useful if several themes/components qualify), `preview_assets_entries` forces the
comma-separated Encore entries. Resolution lives in `Service/PreviewSettings.php`.

Notes: if the resolved component cannot be rendered (renamed, removed), the preview silently falls back to
the default rendering (a warning is logged). The component resolves slides with the admin request locale,
while the default rendering uses the back-office edition locale. A LiveComponent override renders its
initial HTML in the iframe (no live interactivity there). `PreviewSettings::ensureFrontAssets()` also ensures
the `templates-assets` public symlink of the front theme exists, since Thelia only creates it during front
requests.

## Demo data (seed command)

```bash
php Thelia image_gallery:seed --group=demo --count=6 --purge
```

Creates ready-to-use demo slides in a group, with **both images generated** (GD): 1920×600 desktop and
828×1104 mobile — the mobile visual is mandatory in this module, so a desktop-only seed would produce rows
the back-office refuses to save. The first four slides cover the four publication states (**online**,
**disabled**, **scheduled** in a future window, **expired**), and every site locale gets a translation
(`title`, `alt`, `chapo`, `description`, `postscriptum`, `button_label`; French wording for `fr_FR`,
English otherwise).

| Option | Default | Meaning |
|---|---|---|
| `--group` | `demo` | Group to seed. Must match `ImageGallery::GROUP_PATTERN` (letters, digits, `-`, `_`, max 64). |
| `--count` | `4` | Number of slides, minimum 4 (below that the publication states are not all covered). |
| `--purge` | off | Delete every existing slide of the group first. |

The command is **idempotent**: without `--purge` it refuses to touch a non-empty group (warning, exit 0);
with `--purge` it deletes the group through the slide service — image files and image cache included — then
re-seeds it. Slides go through `ImageGallerySlideService`, so the business events of the next section are
dispatched exactly as they are for a back-office creation.

## Front-office integration (to do in your theme)

The module ships **no automatic front insertion**: the theme decides where galleries appear. Two supported
channels — the **Twig component** and the **front API resource** — plus a theme hook that is inert on the
current core (see below).

### 1. Twig component (reusable, overridable) — recommended

The module ships a `ImageGallery` Twig component built on the module's neutral markup
(`templates/theme-hook/image-gallery.html.twig`):

```twig
{{ component('ImageGallery', { group: 'home', autoplay: 5000 }) }}
```

It reads the published slides straight through `ImageGalleryPresenter` (no internal HTTP call, no cache
needed) and exposes them in the flat presenter shape (`slide.title`, `slide.chapo`, `slide.buttonLabel`,
`slide.alt`, `slide.imageUrl`, `slide.imageSrcset`, `slide.mobileImageUrl`, `slide.mobileImageSrcset`,
`slide.url`, `slide.linkTarget`…).

**Overriding from a theme** — two non-exclusive ways:

1. **Class extension** (change markup and/or data logic — the `Flexy:Hero` pattern). The base class is
   deliberately not final and its dependencies are `protected`; the attribute is *not* inherited, so the
   child must redeclare it with its own name and template:

   ```php
   #[AsTwigComponent(name: 'Flexy:ImageGallery', template: '@UiComponents/ImageGallery/ImageGallery.html.twig')]
   class ImageGallery extends \ImageGallery\Twig\Components\ImageGallery
   {
       // inherit getSlides(), or override it (add caching, filtering…)
   }
   ```

   Both components stay registered: `ImageGallery` (module default) and the theme one.

   The extension can just as well be declared as a **LiveComponent** (a LiveComponent *is* a TwigComponent),
   which makes the gallery reactive — e.g. a writable `group` that re-renders the slides without a page
   reload, or extra `LiveAction`s. Redeclare the inherited props you want reactive as `LiveProp`; the
   inherited `getSlides()` recomputes on every re-render, so a `group` change fetches the right slides:

   ```php
   use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
   use Symfony\UX\LiveComponent\Attribute\LiveProp;
   use Symfony\UX\LiveComponent\DefaultActionTrait;

   #[AsLiveComponent(name: 'Flexy:ImageGallery', template: '@UiComponents/ImageGallery/ImageGallery.html.twig')]
   class ImageGallery extends \ImageGallery\Twig\Components\ImageGallery
   {
       use DefaultActionTrait;

       #[LiveProp(writable: true)]
       public string $group = 'home';
   }
   ```

   LiveComponent requirement: the component template must have a **single root element** carrying
   `{{ attributes }}`. The module's neutral template does *not* satisfy this (it is a conditional include),
   so a LiveComponent extension must ship its own template, e.g. a root `<div {{ attributes }}>` that
   includes `@ImageGalleryModule/theme-hook/image-gallery.html.twig` or provides its own markup.

2. **Template-only override** — prepend a theme path to the module's Twig namespace in the theme's
   `config/packages/twig.yaml` (theme config is prepended, so it wins over the module path):

   ```yaml
   twig:
     paths:
       "%kernel.project_dir%/templates/frontOffice/%thelia_front_template%/modules/ImageGallery": ImageGalleryModule
   ```

   Then place your `components/ImageGallery.html.twig` (or `theme-hook/image-gallery.html.twig`) under that directory.

The module's neutral template (`templates/theme-hook/image-gallery.html.twig`, rendered by the component) is
self-contained: CSS scroll-snap track, `<picture>` with the mobile image under 768px, caption with
title/chapo/description/CTA, prev/next buttons and optional autoplay (vanilla JS, honours
`prefers-reduced-motion`). Override it as shown above, or swap it for a library such as Swiper if you need
more (thumbnails, fade transitions…).

### 2. API resource (build your own markup)

```twig
{% set slides = resources('/api/front/image-galleries', {
    group: 'home',
    published: 1,
    'order[position]': 'asc',
}) %}
```

`GET /api/front/image-galleries` returns the slides with processed image URLs and `srcset` values (`imageUrl`,
`mobileImageUrl`, `imageSrcset`, `mobileImageSrcset` — see the next section), the link (`url`, `linkTarget`), the position, and the
translations (`i18ns.<locale>.title|chapo|description|postscriptum|alt|buttonLabel`). The `published=1` filter
keeps only enabled slides currently inside their publication window. An authenticated
`GET /api/admin/image-galleries` variant exposes the raw fields as well (`file`, `mobileFile`, `disable`, `limited`,
`startDate`, `endDate`).

### 3. Theme hook — **not functional as it stands**

> **Do not use this in a theme yet.** The module provides `Hook/Theme/ImageGalleryThemeHook.php`, tagged
> `thelia.theme_hook` (via `ThemeHookInterface`), which would answer
> `theme_hook('image_gallery', { group: 'home', autoplay: 5000 })` — or the shorthand
> `theme_hook('image_gallery.home')` — with the module's neutral markup. **But the current Thelia core ships
> no consumer of the `thelia.theme_hook` tag and no `theme_hook()` Twig function**, so writing that call in a
> template raises an "unknown function" error. The class is kept ready for the day the core provides the
> mechanism; until then use the Twig component (1) or the API resource (2).

## Responsive images (desktop / mobile)

### Approach: art direction + resolution switching

Each slide carries **one content** (title, texts, link) and **two mandatory visuals**: a **desktop image**
and a **mobile image**. This is *art direction*: the mobile visual can be a different framing
(portrait crop, zoom on the subject), not just a scaled-down copy of the desktop one. The browser picks the
variant through `<picture><source media="(max-width: …)">`.

Within each variant, *resolution switching* is handled by `srcset`/`sizes`: several widths of the same image
(desktop: 768 / 1280 / 1920&nbsp;w — mobile: 480 / 828&nbsp;w), generated on the fly by the Thelia image
cache (ratio-preserving resize). The browser downloads the one best suited to the display width and pixel
density.

### Storage

Table `image_gallery`: the `file` column holds the desktop file name, `mobile_file` the mobile one (`NULL` only
for legacy 2.x rows — the desktop image is then served everywhere). Files live in `local/media/images/imagegallery/`,
named `<name>-<id>-desktop.<ext>` / `<name>-<id>-mobile.<ext>` (the variant suffix prevents the two images
of a slide from colliding on disk — see `ImageGallerySlideService::attachImage()`). The size variants are **not**
stored in the database: they are derived on demand from the source file by the image cache
(`/cache/images/imagegallery/…`).

### What the API returns

* `imageUrl` / `mobileImageUrl` — full-size URL, or resized when `?width=&height=` is passed;
* `imageSrcset` / `mobileImageSrcset` — ready-to-drop `srcset` attribute values (only filled on full-size
  renders, i.e. without `width`/`height` parameters).

`mobileImageSrcset` is `null` only for legacy slides that predate the mandatory mobile image: in that case do
**not** render the `<source>` tag, the desktop `<img>` covers every viewport (the default template keeps its
`{% if slide.mobileImageSrcset %}` guard for this reason).

### Theme markup

Recommended pattern:

```twig
<picture>
    {% if slide.mobileImageSrcset %}
        <source media="(max-width: 1023px)" srcset="{{ slide.mobileImageSrcset }}" sizes="100vw">
    {% endif %}
    <img src="{{ slide.imageUrl }}" srcset="{{ slide.imageSrcset }}" sizes="100vw"
         alt="{{ slide.i18ns.alt }}" loading="lazy">
</picture>
```

The module's neutral template (`templates/theme-hook/image-gallery.html.twig`) already applies this markup, and the
vallereuil-scierie theme ships its own `Flexy:Carousel` component (extending this module's base component) built on
the same pattern.

### Adjusting the widths

The width sets are constants of `Service/ImageGalleryImageProcessor.php` (`DESKTOP_SRCSET_WIDTHS`,
`MOBILE_SRCSET_WIDTHS`): adapt them if your theme breakpoints change. The `sizes` attribute must reflect the
actual rendered width — `100vw` for a full-width gallery, e.g. `50vw` if the gallery sits in a half-width
column.

## Extending the module (no fork needed)

Four extension points cover what would otherwise require a fork:

1. **Form fields** — the module forms have stable names: listen to
   `TheliaEvents::FORM_AFTER_BUILD . '.' . ImageGallerySlideForm::getName()` (`image_gallery_slide`, edition)
   or `.image_gallery_create` (creation) and add your fields to the builder. The core still dispatches
   `FORM_AFTER_BUILD` in Thelia 3 (`Thelia\Form\BaseForm::__construct`), so this channel is unchanged from
   Thelia 2. Extra submitted values then travel with the validated form data.
2. **Rendering** — back-office hooks declared by `ImageGallery::getHooks()` and rendered with `safe_hook()`:

   | Hook code | Rendered in | Arguments |
   |---|---|---|
   | `imagegallery.configuration.top` | top of the module configuration page | — |
   | `imagegallery.configuration.bottom` | bottom of the module configuration page | — |
   | `imagegallery.slide-edit.extra-fields` | **inside** the slide edition `<form>` | `slide_id`, `group` |
   | `imagegallery.slide-edit.js` | `scripts_extra` block of the slide edition page | `slide_id`, `group` |

3. **Persistence** — business events (`ImageGallery\Event\ImageGalleryEvents`):
   `image_gallery.slide.create|update|delete` (pre-save, inside the transaction) and
   `image_gallery.slide.created|updated|deleted` (post-save, after commit). `ImageGallerySlideEvent` carries
   the slide model and the COMPLETE validated form data (your fields included): persist on the post-save
   events.
4. **Lists** — `image_gallery.query.extend` (alter the `ImageGalleryQuery` before it runs — check
   `isBackendContext()`) and `image_gallery.row.enrich` (add keys to the presented row). Both are dispatched
   by `ImageGalleryPresenter`, so they apply to the back-office tables, the front Twig component and the
   theme hook at once.

> The **API resources** are extended the standard API Platform / Propel-bridge way
> (`ResourceAddonInterface`, state providers, filters) — the module does not duplicate that mechanism with
> events of its own. Use the addon contract for `/api/front/image-galleries` and `/api/admin/image-galleries`.

### How to add business fields to the slide edition form

This is **the** supported way for a project module to attach its own business data to a slide. The example
adds a single `my_field` choice to the slide edition page from a module named `MyModule`. Everything is
auto-discovered: as long as your module's `configureServices()` loads its namespace with
`->autowire(true)->autoconfigure(true)` (the Thelia 3 default), there is **nothing to declare in
`config.xml`** — neither the subscribers nor the hook.

#### 1. Add the form field

```php
<?php
// local/modules/MyModule/FormExtend/ImageGallerySlideFormExtend.php

declare(strict_types=1);

namespace MyModule\FormExtend;

use ImageGallery\Form\ImageGallerySlideForm;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Core\Translation\Translator;

class ImageGallerySlideFormExtend implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::FORM_AFTER_BUILD.'.'.ImageGallerySlideForm::getName() => [
                ['addMyFields', 64],
            ],
        ];
    }

    public function addMyFields(TheliaFormEvent $event): void
    {
        $translator = Translator::getInstance();

        $event->getForm()->getFormBuilder()
            ->add('my_field', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    $translator->trans('All customers', [], 'mymodule') => 'all',
                    $translator->trans('Members only', [], 'mymodule') => 'members',
                ],
                'label' => $translator->trans('Audience', [], 'mymodule'),
            ]);
    }
}
```

Notes:

- Keep `'required' => false` unless you really want to break every other code path that submits the form
  (creation dialog, seed command, programmatic saves): a missing value must never invalidate a slide.
- Use `ImageGallerySlideForm::getName()` rather than the literal `'image_gallery_slide'`.
- Declaring the field here is **mandatory**, not optional: the module keeps Symfony's default
  `allow_extra_fields = false`, so an input the form does not know about is rejected by validation
  (« This form should not contain extra fields »). Declaring it is also what makes it appear in the
  validated data — the two go together.
- For a field that must also be filled at creation time, subscribe the same way to
  `TheliaEvents::FORM_AFTER_BUILD.'.'.ImageGalleryCreateForm::getName()` (`image_gallery_create`).

#### 2. Render the field in the back-office

The slide edition template exposes a slot **inside** the `image_gallery_slide` form:
`{{ safe_hook('imagegallery.slide-edit.extra-fields', { slide_id: slide.id, group: slide.group }) }}`.
Implement a back-office hook, load the current value from your own tables and render your template:

```php
<?php
// local/modules/MyModule/Hook/ImageGalleryBackHook.php

declare(strict_types=1);

namespace MyModule\Hook;

use MyModule\Model\MySlideDataQuery;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class ImageGalleryBackHook extends BaseHook
{
    public static function getSubscribedHooks(): array
    {
        return [
            'imagegallery.slide-edit.extra-fields' => [
                'type' => 'back',
                'method' => 'onSlideEditExtraFields',
            ],
        ];
    }

    public function onSlideEditExtraFields(HookRenderEvent $event): void
    {
        $slideId = (int) $event->getArgument('slide_id');

        $event->add($this->render('image-gallery-slide-extra-fields.html.twig', [
            'slide_id' => $slideId,
            'my_field' => MySlideDataQuery::create()->findPk($slideId)?->getMyField() ?? 'all',
        ]));
    }
}
```

```twig
{# local/modules/MyModule/templates/backOffice/default-twig/image-gallery-slide-extra-fields.html.twig #}
<div class="card mb-3">
    <div class="card-header">{{ 'Audience'|trans({}, 'mymodule') }}</div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label" for="my-field">{{ 'Audience'|trans({}, 'mymodule') }}</label>
            <select id="my-field" name="image_gallery_slide[my_field]" class="form-select">
                <option value="all"{{ my_field == 'all' ? ' selected' }}>{{ 'All customers'|trans({}, 'mymodule') }}</option>
                <option value="members"{{ my_field == 'members' ? ' selected' }}>{{ 'Members only'|trans({}, 'mymodule') }}</option>
            </select>
        </div>
    </div>
</div>
```

**The key constraint is the input name**: `image_gallery_slide[<field>]` — and
`image_gallery_slide[<field>][]` for a `multiple` field. It must match the form name of step 1, otherwise
the value never reaches the validated data. The hook renders inside the module's `<form>`, so no extra
`<form>` tag is needed.

Two points about hook wiring:

- The four hook **codes** are inserted in the `hook` table by ImageGallery itself, through
  `ImageGallery::getHooks()` — the Thelia 3 mechanism for this (idempotent create-or-update, replayed on
  activation and on every module install/update pass). They are declared with `module => false`
  (`by_module = 0`), which is exactly what makes them attachable by *your* module: a `by_module` hook is
  dispatched under a module-scoped event name a plain `safe_hook()` call never reaches.
- The `module_hook` row binding **your** module to that hook is created automatically by Thelia's
  `RegisterHookListenersPass` on the next `cache:clear`. You never insert it by hand. If ImageGallery has
  not been (re)activated since these hooks were added, the code is missing from the `hook` table, the pass
  logs `Hook … is unknown` and silently skips your hook — check that first when nothing renders.

#### 3. Persist the value

`ImageGallerySlideEvent::getData()` returns a `ParameterBag` holding the **complete validated form data**,
including the fields added in step 1. Persist on the *post-save* events (`SLIDE_CREATED` / `SLIDE_UPDATED`),
dispatched once the module transaction is committed:

```php
<?php
// local/modules/MyModule/EventListener/ImageGalleryMyModuleListener.php

declare(strict_types=1);

namespace MyModule\EventListener;

use ImageGallery\Event\ImageGalleryEvents;
use ImageGallery\Event\ImageGallerySlideEvent;
use MyModule\Model\MySlideData;
use MyModule\Model\MySlideDataQuery;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ImageGalleryMyModuleListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ImageGalleryEvents::SLIDE_CREATED => ['persistMyFields', 64],
            ImageGalleryEvents::SLIDE_UPDATED => ['persistMyFields', 64],
            ImageGalleryEvents::SLIDE_DELETED => ['cleanupMyData', 64],
        ];
    }

    public function persistMyFields(ImageGallerySlideEvent $event): void
    {
        $data = $event->getData();

        // Field absent from the submitted data (creation dialog, seed command,
        // programmatic save): leave the existing value untouched.
        if (!$data->has('my_field')) {
            return;
        }

        $slideId = (int) $event->getSlide()->getId();

        $myData = MySlideDataQuery::create()->findPk($slideId)
            ?? (new MySlideData())->setImageGalleryId($slideId);

        $myData
            ->setMyField((string) $data->get('my_field'))
            ->save();
    }

    public function cleanupMyData(ImageGallerySlideEvent $event): void
    {
        MySlideDataQuery::create()
            ->filterByImageGalleryId((int) $event->getSlide()->getId())
            ->delete();
    }
}
```

Notes:

- **Never add columns to the `image_gallery` / `image_gallery_i18n` tables.** Store your data in your own
  module's tables, keyed by the slide id — that is what keeps the module updatable without a fork.
- Always guard with `$data->has(...)`: the creation form does not carry the edition fields, and other code
  paths dispatch the events with a partial (or empty) bag.
- For an array value (a `multiple` choice), read it through `$data->all()['my_field'] ?? []` — a
  `ParameterBag` is meant for scalars.
- `SLIDE_DELETE` / `SLIDE_DELETED` are dispatched with an **empty** data bag: only `getSlide()` is
  meaningful there. `SLIDE_DELETED` fires after the commit *and* after the image files are removed.
- `$event->getLocale()` gives the back-office edition locale, when your data is localized.
- The pre-save events (`SLIDE_CREATE` / `SLIDE_UPDATE`) run **inside** the module transaction, right before
  the model is written: use them only to alter the slide model itself, never to write your own tables (a
  rollback would keep them).

#### 4. (Optional) Filter or enrich the lists

`image_gallery.query.extend` hands you the `ImageGalleryQuery` before it runs — the place for front filters.
Skip it when `isBackendContext()` is true, or the back-office tables would be filtered too and
administrators would stop seeing their own slides:

```php
use ImageGallery\Event\ImageGalleryQueryEvent;
use ImageGallery\Event\ImageGalleryRowEvent;

// …add to getSubscribedEvents():
//   ImageGalleryEvents::QUERY_EXTEND => ['applyFrontFilters', 64],
//   ImageGalleryEvents::ROW_ENRICH   => ['enrichRow', 64],

public function applyFrontFilters(ImageGalleryQueryEvent $event): void
{
    if ($event->isBackendContext()) {
        return;
    }

    $event->getQuery()
        ->useMySlideDataQuery(null, Criteria::LEFT_JOIN)
            ->filterByMyField(['all', $this->currentAudience()], Criteria::IN)
        ->endUse();
}

public function enrichRow(ImageGalleryRowEvent $event): void
{
    $event->set(
        'myModuleAudience',
        MySlideDataQuery::create()->findPk((int) $event->getSlide()->getId())?->getMyField() ?? 'all',
    );
}
```

`ImageGalleryRowEvent::set()` adds a key to the flat read-model returned by
`ImageGalleryPresenter::present()` — visible in the back-office rows, the `ImageGallery` Twig component and
the theme hook. Namespace your keys; the native ones are reserved.

## Developer notes — Twig back-office workarounds

Two limitations of the current Thelia Twig back-office stack are worked around inside the module. Both
workarounds become removable once fixed upstream.

### Flash messages never render through `app.flashes`

Thelia's `TwigParser` (TwigEngine module) exposes `app` as a plain object with only
`environment` / `request` / `session` / `debug` — **no `flashes`** — so the flash block of the theme's
`base.html.twig` silently renders nothing, for every module. Controllers should still use the standard
`$this->addFlash('danger', …)` (BaseController), but the page template must render the messages itself:
`slide-edit.html.twig` overrides `{% block flash %}` and reads `app.session.flashBag.all` directly (which
also consumes them). Reuse that block on any new module page that needs flashes.

### Sidebar active state on module pages

The theme sidebar (`_side_nav.html.twig`) resolves the open section by URL prefix, so every page under
`/admin/module/*` lights up the **Modules** section, and `main.top-menu-tools` fragments cannot carry an
active state (the fragment `class` is never rendered). The partial
`templates/backOffice/default-twig/imagegallery/hook/_side-nav-active.html.twig` (small CSS + JS) re-targets the
sidebar to **Tools → “Edit your image gallery”** on the module's own pages. It is included from
`hook/module-config-js.html.twig` (configuration page, hook `module.config-js`) and from the
`scripts_extra` block of `slide-edit.html.twig` — include it likewise on any new full page added to the
module.

## Loop (deprecated)

The legacy `image_gallery` Smarty loop (same arguments as the `image` loop, plus `group` and
`filter_disable_slides`) still works for Smarty themes but is **deprecated** and will be removed in
2.0 — use the Twig component or the API resource instead. Note: the loop never writes to the
database; the publication state is computed at read time.

## Data model

Table `image_gallery`: `file`, `mobile_file` (desktop / mobile visuals, both mandatory since 3.0 — `mobile_file`
is `NULL` only on legacy 2.x rows, see « Responsive images » above),
`group`, `position` (unique per group, managed by drag & drop),
`url`, `link_target`, `disable`, `limited` + `start_date`/`end_date`, timestamps.
Table `image_gallery_i18n`: `alt`, `title`, `chapo`, `description`, `postscriptum`, `button_label`.
