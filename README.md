# Palefire

Drupal 11 theme (starterkit-generated) built on Bootstrap 5.3 + Dart Sass.

## Build

    npm install        # bootstrap, bootstrap-icons, swup, sass
    npm run build      # scss/style.scss -> css/style.css, copies Bootstrap JS, Swup + icons
    npm run watch      # recompile on save

## Structure

- `palefire.info.yml` – regions, global libraries, `core_version_requirement: ^11`
- `palefire.breakpoints.yml` – Bootstrap 5 breakpoints (sm 576 / md 768 / lg 992 / xl 1200 / xxl 1400)
- `scss/` – `_variables.scss` (brand + Bootstrap overrides), `base/`, `layout/`, `components/`
- `components/toc/` – Single Directory Component: "On this page" navigation
- `src/Hook/PalefireHooks.php` – OOP theme hooks (TOC, section anchors, branding)
- `templates/paragraphs/` – section markup for Modular pages
- `scripts/palefire_modular_setup.php` – one-time Paragraphs/field setup (drush php:script)

## Modular pages

Any paragraph type with `field_section_title` placed in `field_sections` on a
`modular_page` node becomes a `<section>` with an H2, a stable anchor, and a TOC
entry. `field_full_page` turns on full-viewport scroll-snap (md and up).

### Building maps

A bento section can show a building floor map under its search form
(`bento_search.field_bento_map` -> `building_map` paragraph). Rooms show a
tooltip on hover (after a short wait, `--pf-map-tip-delay`, so sweeping the
pointer across the map doesn't flash tooltips), on keyboard focus, or on tap;
it fades in with a small bump (just a fade under reduced motion). The
"Legend & spaces" list puts Floor 1 first, and the background video dims and
blurs while the map is in view.

Every room you can select (map area, its name, or its legend entry) shows a
pointer cursor. Rooms with a link are links; rooms without one are buttons
(`role="button"` on the drawing, `<button>` in the list) that open the
tooltip, so Enter and Space work and screen readers announce something
actionable. The tooltip text also reaches screen readers through
`aria-describedby`. Clicking a room's name does what clicking the room does,
unless you are selecting the text.

"Search LSU Libraries" and the map heading are plain h3s: they take the
modular page's h3 styles, with no type of their own. They carry `data-toc`,
so the TOC lists them under their section in every mode. The TOC block's
"Include h3 sub-links on these modes" setting (palefire_modular,
`deep_modes`, default `guide`) still decides whether *all* h3s are listed;
on other modes only `h3[data-toc]` are.

Each building is one drawing plus an entry in `maps/buildings.yml` (label,
default heading, Twig include, rooms with floor). The content lives in the
**Building spaces** taxonomy: one term per building (its description is the
default intro) with a child term per room (tooltip text, access, optional
link). Unpublish a room term to draw it as plain floor. Editors add a
**Building map** to the bento and pick the building; nothing else is needed.
**Room overrides (optional)** on the paragraph change one room's tooltip,
access or link on that page only. It stays collapsed to an "Add Room
override" button until used. **You are here (optional)** is for a page about
one room: pick the room and the map marks it with a bobbing down arrow over
its name, a "Here" badge in its legend entry and tooltip, and
`aria-current="location"`. It is ignored when the room is in another
building or unpublished.

```bash
npm run build      # compiles _building-map.scss into css/style.css; commit it
drush php:script themes/custom/palefire/scripts/palefire_building_map_setup.php   # adds field_map_here
drush cr
```

The setup script creates the vocabulary, paragraph types and fields, and
creates any building/room terms missing from Building spaces (it never
overwrites existing terms, so editors own the text). Re-run it after editing
`buildings.yml`. To add a building: add its drawing under
`templates/includes/`, add an entry to `buildings.yml`, and re-run.

Layout: the legend sits beside the map (15-18rem column) while the map
still gets about 36rem; in a narrower container (under 58rem, a container
query on `.pf-bento__visit`, so the TOC sidebar is taken into account) it
moves under the map. The "Legend & spaces" toggle always shows: open beside
the map, collapsed under it. Inside a section the bento adds no padding of
its own (the section's `--pf-section-pad-*` already applies).

Icons: the squares behind the galleries are the elevators. They are outline
only (not part of a gallery region) with the elevator arrows drawn straight
on the floor: no tile, fill or border behind them
(`templates/includes/map-icon-elevator.svg.twig`). The restroom tiles keep
their outline but have no fill either. List the icons a drawing
uses under `legend` in `buildings.yml` (currently `elevator`) to explain them
in the legend; this is theme-only, so `npm run build` and `drush cr` are
enough.

Swatches: the legend and tooltip swatches are the map's own hatch, a 5-unit
line every 20 units at the same white and opacity as the rooms.
`building-map.js` sets `--pf-map-hatch-period` to those 20 units in screen
pixels whenever the map resizes, so the swatch lines keep the map's weight
and spacing at any size.

Dark chrome: while a dark section is active, the top bar uses the TOC
sidebar's wash and blur (`--pf-sidebar-bg`, `scss/layout/_chrome-dark.scss`),
so the two match. To make both darker, change `sidebar-bg` in
`$pf-chrome-dark` (`_modular-tokens.scss`).

Upgrading from the per-page "Map space" version: the old `map_space` type
and `building_map.field_map_spaces` are listed but kept. Run the script with
`-- cleanup` to delete them. The bento's map is now rendered by
`palefire_preprocess_paragraph__bento_search()`, so it no longer depends on
the bento's view display.

### Full-page backgrounds and frosted glass

Section background images (`field_section_bg`) and bento background videos
run past their section to fill the page: left under the "On this page"
sidebar (lg and up) and, for the first section, up under the top bar. The top
bar and the sidebar are frosted glass (translucent + `backdrop-filter`), so
the background shows through, blurred. Only the decorative layer moves;
section content and solid section colours stay in the main column.

- Tune the glass in `scss/_variables.scss`: `$pf-glass-blur`,
  `$pf-glass-saturate`, `$pf-header-bg-alpha`. The sidebar wash is
  `sidebar-bg` in `scss/components/_modular-tokens.scss`.
- `js/palefire.js` (`palefireBleedOffset`) measures how far the first
  background has to reach up, so a breadcrumb, title or tabs above the
  sections are covered too. Those stay on top of the image.
- Users with `prefers-reduced-transparency` or `prefers-contrast: more` get
  a solid top bar and sidebar. Browsers without `backdrop-filter` get a
  nearly opaque top bar.

## Page transitions (Swup 4)

`js/palefire-swup.js` swaps `#swup` (breadcrumb + body) and `#pf-primary-menu`
for anonymous visitors, re-running Drupal behaviors and syncing drupalSettings,
meta tags, and body classes. Pages that need new assets or contain inline
scripts fall back to a full load. Opt out per link with `data-no-swup`.
