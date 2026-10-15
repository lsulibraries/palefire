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

### Hill Memorial building map

An optional `building_map` paragraph renders in the bento section, under the
search form (`bento_search.field_bento_map`), with one `map_space` per room.
Rooms show a tooltip on hover or focus. The legend & spaces list puts Floor 1
first, and the background video dims and blurs while the map is in view.
"Search LSU Libraries" and the map heading are h3s so the TOC lists them.

```bash
npm run build      # compiles _building-map.scss into css/style.css
drush php:script themes/custom/palefire/scripts/palefire_building_map_setup.php
drush php:script themes/custom/palefire/scripts/palefire_building_map_seed.php -- /special
drush cr
```

The setup script creates the paragraph types and fields; it adds no content.
The seed script adds a starter map (all eight rooms, placeholder text) to the
bento on the given page and skips a bento that already has one. Edit the room
text and add links in the bento's "Building map" field. Rooms: `reading_room`,
`gallery_1`, `lecture_hall` (Floor 1); `gallery_2`, `quiet_study`,
`seminar_space`, `mcilhenny_room`, `oral_history` (Floor 2).

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
