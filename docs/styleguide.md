# Palefire style guide: working notes

The style guide is a public exhibition page at
**/v2/special/internal/styleguide**. These notes are its source: what it
covers, the rules behind it, and what to update when a component is added or
changed. Keep them in step with the page.

## How it is built

| Part | File | Owns |
| --- | --- | --- |
| Page content | `scripts/palefire_styleguide.php` | Galleries, prose, editor notes, live block examples. Re-running it rebuilds the page as a new revision. Edits made in the UI are lost on the next run. |
| Plates | `templates/includes/styleguide-plate.html.twig` | The Behance-style sheets: overview, colors, type, grid, spacing, lines, motion. |
| Plate styles | `scss/styleguide.scss` → `css/styleguide.css` | Its own entry point and library (`palefire/styleguide`), so only this page loads it. |
| Plate script | `js/styleguide.js` | Prints hex codes, contrast and easing curves from what the browser draws. |
| Marker | `palefire_preprocess_paragraph__guide_text()` in `palefire.theme` | Any text block whose paragraph is only `[styleguide:<plate>]` draws that plate after its text. Unknown names stay as typed. |

Plates never hard-code a token's value. Sizes, breakpoints and spacers are
printed from Sass (`content:`). Colors and curves are read in the browser. A
token change therefore shows up on the page after `npm run build` and a
deploy, with no content edit. The colour plate repaints when a visitor picks
another reading colour.

Values that live in a rule rather than a token are mirrored in
`styleguide.scss`, with a comment naming their source:

- page title size (`_landing-swiss.scss`)
- gallery title and reading text (`_exhibition.scss`)
- settle and curtain durations (`_exhibition.scss`)

If one of those changes, update its mirror. Better still, promote it to a
token in `_variables.scss`, as was done for `$pf-exhibit-ease` and
`$pf-exhibit-curtain`.

## When a component is added or changed

1. **Notes:** add or update its row in the inventory below, and its rules if
   it sets a new one.
2. **Page:** if editors can add it, add a live example and a "For editors"
   callout to the "Exhibition blocks" gallery (or a new gallery) in
   `palefire_styleguide.php`. Guard new paragraph types with
   `ParagraphsType::load()` so the script still runs where the type doesn't
   exist yet.
3. **Plate:** if it introduces a token (colour, size, easing), add it to the
   plate that shows that kind of token. For a new plate:
   - append its name to `PALEFIRE_STYLEGUIDE_PLATES` (at the end, so the
     catalogue numbers of existing plates stay the same);
   - add a branch to the plate template and a title to `titles`;
   - style it in `styleguide.scss`;
   - bump "Plates: N" in the script.
4. **Build:** run `npm run build:css`, which compiles both stylesheets.
5. **Production:** after `git pull` and `drush cr`, run
   `drush php:script scripts/palefire_styleguide.php` (add `image=<media id>`
   for the figure examples). Then run `drush cr` again.

## Rules (the short version)

- **Swiss, museum-calm.** Hierarchy comes from type, size and space, not
  boxes. Colour is kept for meaning.
- **Colour:**
  - Use tokens only. Components read `--pf-g-*` (reading colours) or the
    section tokens, never literals.
  - LSU gold appears only on purple or dark grounds.
- **Lines:** every rule, row and frame is `$pf-hairline` (20%). Control
  borders that identify a control use `--pf-section-border` at 3:1.
- **Type:**
  - Roboto everywhere.
  - Playfair Display for exhibition titles only, at display sizes.
  - Inside a block with a heading, use Heading 4: an h3 becomes a TOC link.
- **Motion:**
  - Easing curves come from `_variables.scss`.
  - Colour arrives fast (`$pf-hover-in`) and leaves slowly (`$pf-hover-out`).
  - Decorative motion sits behind `prefers-reduced-motion: no-preference`
    and `html:not(.pf-a11y-motion)`.
- **Accessibility:**
  - WCAG 2.2 AA in every reading colour.
  - Visible `:focus-visible` ring (3px, 2px offset).
  - Targets of 44px, or 36px in the compact header.
- **Icons:** Font Awesome 5.15.4 class names only (`fas`, `far`, `fab`),
  with `aria-hidden="true"`. The theme mostly inlines SVGs.
- **Performance:**
  - Attach a library only where it is needed.
  - Lazy-load images and give them width and height.
  - No new external dependencies without asking.
- **Page kinds:** `<html data-pf-page>` names the kind of Modular page
  (`PalefireHooks::pageKind()`). Scope page-wide rules with `$pf-system`
  (column, grid, section headings) or `$pf-banded` (alternating tones),
  not with a page's own class. Guides, forms and exhibitions keep their
  own layouts; guides read the shared type tokens.

## Component inventory

On page: ✔ shown live · ▣ shown as a plate · – not yet.

| Component | Template / styles | On page |
| --- | --- | --- |
| Reading colours, brand colours | `_reading-themes.scss`, `_variables.scss` | ▣ Colour |
| Type scale, fonts | `_variables.scss`, `_fonts.scss` | ▣ Typography |
| Grid, breakpoints, measures | Bootstrap 5.3, `_variables.scss` | ▣ Grid |
| Spacers, rhythm | Bootstrap `$spacers` | ▣ Spacing |
| Hairline, control border, focus ring | `_base.scss`, `_variables.scss` | ▣ Lines |
| Motion tokens | `_variables.scss` | ▣ Motion |
| Buttons (primary, secondary, arrow) and text links | `_buttons.scss` (`%pf-button-primary`, `%pf-button-secondary`, `%pf-button-arrow`; `.pf-button--*` for markup of its own), `.pf-index-rule__link` | ▣ Buttons and links |
| Exhibition gallery (story) | `paragraph--exhibit-page` | ✔ every gallery |
| Exhibition title page | `includes/exhibit-cover` | ✔ the page's own |
| Text (Plain, Callout, Sources) | `paragraph--guide-text` | ✔ |
| Catalogue record | `paragraph--exhibit-citation` | ✔ |
| Entry with biography | `paragraph--exhibit-entry` | ✔ |
| Figure | `paragraph--exhibit-figure` | ✔ with `image=` |
| Image group / readings | `paragraph--exhibit-gallery`, `--exhibit-reading` | ✔ readings |
| Accordion | `paragraph--guide-accordion` | ✔ |
| Wall display, compare slider | `paragraph--exhibit-page` (wall) | – needs images |
| Audio clip, tape deck, reel | `paragraph--exhibit-audio` | – needs a file |
| Video | `paragraph--guide-video` | – |
| Resource list, librarian card | `paragraph--resource-list`, `--librarian-card` | – guide only |
| Tile showcase, covers shelf | `paragraph--tile-showcase` | – landing only |
| Landing hero, stacks, department | `paragraph--landing-*` | – landing only |
| Events carousel, Visit strip | `paragraph--events-carousel`, `_visit.scss` | – |
| Building map, parish map, family trees | `paragraph--building-map`, `--parish-map`, `--family-trees` | – |
| Location page: plate (title + map), ledger, map chip; its parts as sections (room photos, hours and directions, house rules, booking), in index columns with a sticky head from lg; policy marks | `paragraph--location-space`, `-location-photos`, `-location-visit`, `-location-rules`, `-location-booking`, `includes/space-icon`, `_location.scss`, `js/location-plate.js` | – location pages only |
| Ask Special Collections | `includes/ask-special` | – |
| Exhibitions showcase | `includes/exhibit-showcase` | – its own page (`/v2/special/exhibitions`): two bands with index rules 01 and 02, the breadcrumb in the landing's glass bar, the rail TOC, alternating band colours |
| Header, utility drawers, TOC, footer | `layout/*`, `_utility-panels.scss`, `_toc*.scss` | ✔ around the page |
| Breadcrumb bar (glass sub-header, sticky under the header) | `layout/page.html.twig`, `.pf-landing__bar` / `.pf-page-bar` in `_landing-features.scss` | ✔ the exhibition's own; every page but guides |
| Mobile dock: hours, action, Contents ring; hidden-state tab | `includes/dock.html.twig`, `_dock.scss`, `js/dock.js` | – Special Collections pages, below 768px |
| 404 page: display case, wall text, quips | `includes/not-found`, `_not-found.scss`, `js/not-found.js` | – its own page (`/v2/404`); editors keep the quips in that page's Intro, one a line |

## Changelog

- **2026-11-02:** `pf-location-backdrop-whole`: bottom 0 with `max-height: 100lvh` (was `height: 100lvh`), so the backdrop never passes the Location section's foot.
- **2026-11-02:** Map: `[aria-current]` room back to the hatch at full strength, its name in the ink (`.is-here.is-active`); the solid `--pf-map-hover` fill and dark name are undone.
- **2026-11-02:** Location ledger: `.pf-location__facts::before` has no fill (hairline, shadow, blur over a backdrop); six facts get six columns from 52rem. Backdrop: a swatch-colour `color` layer over the veil; `--pf-location-muted` lifted 40% toward `--pf-g-heading` over it. New `includes/about.html.twig` ("~100", read "about 100") and space mark `receipt`. Map: the `[aria-current]` room is filled with `--pf-map-hover`, its name in `--pf-map-on-hover`, focus ring in the ink; the key groups Walk in / Book online / Room rental (`s.hatch`, `s.access_label`); indexed key two columns from md, one per group from 1200px.
- **2026-11-02:** Location top: the map's empty head and aside rows lose their row gap, so the drawing starts level with the eyebrow (it sat 2.7rem lower). The ledger sits on a panel (`.pf-location__facts::before`): the section color at 80%, hairline frame, the hero search field's shadow (`0 1.5rem 3rem -1rem`, .18, .5 over a backdrop, where it is also frosted); 1.5rem past the text from 52rem, edge to edge below. More contrast and forced colors keep the hairline only.
- **2026-11-02:** Buttons: one system in `_buttons.scss`. Primary is the swatch accent filled (`--pf-g-accent`, ink `--pf-g-on-accent`), hover to the text color; secondary is unfilled with the control border (`--pf-section-border`, else `--pf-g-scroll`); both 13px .12em mono capitals, 48px (dense rows 44px). Arrow: → on the site, ↗ for `target="_blank"`, stepping the way it points. The location button, the landing's Register and Quick guide buttons, the Ask buttons and the footer button follow it; index-rule links show ↗ when they open a new tab; text-link underlines sit at .35em. New plate 08, Buttons and links.
- **2026-11-02:** Location pages: the eyebrow is the landing's kicker (building, rule, floor); the lede is `--pf-hero-text`; the head sits at the top of its column. Google Maps is linked once (the index rule). The facts run in one row of equal columns from 40rem. Reserve splits its text at the subheads: intro, the steps (across from md), the button, then the other parts on 14rem columns; no index-rule link. Room photos below md: the first across at 4:3, the rest in pairs.
- **2026-11-02:** Exhibition walls below md: a tile alone on its row fills it (cap 60svh tall, not 1.75 times the row), and the last row fills too.
- **2026-11-02:** Bare exhibition walls below lg: the foot wash is sized to the text (solid behind it, eased out over `$pf-bare-fade`, 8rem), not a share of the screen.
- **2026-11-02:** Phones: with the section crumb showing, the breadcrumb drops the crumbs between Home and the page, and the section keeps its name (up to 55% of the row). Bare exhibition walls below lg get a lighter foot wash (`$pf-bare-wash`, 36% to 76%).
- **2026-11-02:** Location pages: today's hours sit right under the title, as on the landing hero (title, hours, line, marks, button), with the landing's own spacing (`.pf-landing__hours`; the location override is gone).
- **2026-11-02:** Location pages: the sound level is the last policy mark (`.pf-space-pin--sound`, the facts' three-bar meter; tooltip "Quiet" / "Sound level", no state flag). House rules fill the content column (no auto margins) and their groups sit on `auto-fill` 14rem columns, so two groups take two of three columns at 1440 instead of stacking.
- **2026-11-02:** Collapsed dock glass thins to 66% at the top edge (`$pf-dock-glass-top`); the solid page-color foot is the bottom 25% so muted text keeps 4.5:1.
- **2026-11-02:** The dock's solid foot is the page color (`--pf-dock-foot-rgb`), not the chrome, so it meets Safari 26's toolbar band (tinted from the page background) without a seam; the fade into the 75% chrome glass ends at 90%.
- **2026-11-02:** Dock action pill and Contents ring share a 34px size (pill 34px tall, 12px from the ring; the open ring fills a 34px disc in its 44px target). `.pf-dock-foot`, a 16px solid strip behind the dock's solid foot, lets Safari 26 tint its toolbar band to match.
- **2026-11-02:** Collapsed dock glass is a gradient (`--pf-dock-fill`): solid in the bottom 20%, thinning to 75% (`$pf-dock-glass-top`) by 85% of its height; open panels stay 92%.
- **2026-11-01:** The dock rests on Safari 26's floating toolbar instead of running under it: the tint strip, the bleed and the 76px toolbar reserve are removed (12px under the row everywhere).
- **2026-11-01:** `.pf-dock-tint`, a 12px solid strip at the bottom edge in the dock's color (iOS WebKit only), so Safari 26 paints the band behind its toolbar to match the dock.
- **2026-11-01:** The dock's glass bleeds 160px below its edge (`--pf-dock-bleed`), so it runs under Safari 26's floating toolbar, where `bottom: 0` is the toolbar's top.
- **2026-11-01:** Location parts in Swiss index columns from lg: rule across, `.pf-section__head` (heading + lede, sticky) in 4/12, content in 8/12 (`.pf-section--aside`, `index.aside`). Hours beside its links; Booking text in two columns from xl. Dark backdrop fades into the section color at the Location section's foot (no bottom padding).
- **2026-11-01:** Location pages are one section per part, as /v2/special: Location (the top only), `location_photos` (`.pf-location__photos`), `location_visit` (`.pf-location__visit`), `location_rules`, `location_booking`, numbered by `_palefire_location_index()`. The floors' frost fades in with the map's draw (`.pf-map.is-playing`). The ledger has no rules; over the photo it is frosted like the floors. On portrait screens and iOS the backdrop fills one screen from the top and fades out (`pf-location-backdrop-whole`).
- **2026-11-01:** The dock is set in Roboto (mono only for uppercase labels and section numbers) on the landing bar's chrome frost at 92%, so muted text holds 4.5:1 over anything. The closed dot uses `--pf-g-scroll`. The dot steps in to the second line's 16px indent. The Contents ring shows "5", not "05".
- **2026-11-01:** The dock's week gains Ask Us, an accent pill like the row's action (icon dropped below 360px), beside "See full hours". The utility menu stays in the header on every page; its Hours tile opens the dock's week on dock pages.
- **2026-11-01:** Mobile dock on Special Collections pages, below 768px: the drawers' colors in a thicker glass (90%, `$pf-dock-glass`, so muted text holds 4.5:1 over anything), 28px top corners, a hairline top edge. Status dots differ by shape as well as color (filled open, accent ring soon, muted ring closed). The Contents ring uses the TOC's accent and mono numbers; the current TOC row takes the accent rail. Actions are accent pills (Call desk the quieter rule tone), at least 118px wide, dropping their icon below 360px. Motion: `--pf-dock-ease` (`cubic-bezier(.2, .8, .2, 1)`) over `--pf-dock-dur` (.35s); none with reduced motion.
- **2026-10-31:** No shadow between the header and the breadcrumb bar (the compact header's fell across it). The 404 page's spotlight and dust reach up behind the header and bar, which are thinner glass there until the page scrolls.
- **2026-10-31:** First version.
  - Seven plates: overview, colour, type, grid, spacing, lines, motion.
  - "Exhibition blocks" gallery with editor notes.
  - `$pf-exhibit-ease` and `$pf-exhibit-curtain` moved to `_variables.scss`.
- **2026-10-31:** The breadcrumb bar is on every page but guides. Plain pages and the 404 page print it at the top of `#swup`, Modular pages at the top of `<main>` (`.pf-page-bar`), sticky under the header for the whole page, its crumbs starting where the logo does. Landings, exhibitions, the showcase and location pages keep theirs. The 404 page's mono label is now one of the editors' quips at random.
- **2026-10-31:** Exhibitions showcase follows the landing's page furniture. The breadcrumb sits in the glass sub-header bar (`.pf-showcase-head`, pinned like an exhibition's), the rail TOC lists Browse and All exhibitions, each band opens with an index rule, and the second band and every other section after it take the swatch surface, as on the Special Collections landing. Everything starts on the section padding line, and the covers line up with the summary's top.
- **2026-10-31:** Exhibitions showcase shares the landing's styles: `data-pf-hero` (1280px column, type tokens, section headings), the clear-then-glass header and bar, the 4/5-column grid with `--pf-grid-gutter`, the index-rule rhythm, and a new shared token, `--pf-hero-title-size`.
- **2026-10-31:** The Grid / List switch (`.pf-tiles__view`) is now also a filter: the Exhibitions showcase's All / Digital / Physical buttons reuse it, with a count after each label.
- **2026-10-31:** Page kinds. `data-pf-page` on `<html>`, with `$pf-system` and `$pf-banded` in `_variables.scss`: the 1280px column, section headings and alternating bands now apply to every Modular page but guides, forms and exhibitions. The `--pf-hero-*` type and rhythm tokens moved to `:root`; guide section headings use `--pf-hero-h2-size`. Location pages use the five-column grid, the shared title and h2, and index rules.
- **2026-10-31:** Location pages: the map plate (the building drawing, small, at the foot of the details column once the map has scrolled away; a chip at the bottom of the screen on phones or wherever the column would cut it off). Policy marks lose their built-in square: a required mark shows a corner flag on its frame instead, and the Required group's mark is an asterisk.
- **2026-10-31:** Location pages, layout 01: the title beside the map (2/5 and 3/5, key folded), a full-width ledger of facts with the policy marks, then numbered parts (`.pf-location__part`, heading over two columns, content over three; `--band` on odd parts). The map chip is always the bottom-left chip. New placeholder `%pf-visit-tokens` for the Visit colors; Booking text styles its h3, lists and numbered steps.
- **2026-10-31:** Location pages: the policy marks sit under the title's line; the head is centred on the drawing; the building map shows without its legend (`map.no_key`), the room lit and marked.
- **2026-10-31:** Location pages: "The room" part (`.pf-location__photos`, `.pf-location__photo`): the room's photos three across at 4:5, captioned in the label voice under a hairline. Image style `pf_location_photo`.
- **2026-10-31:** Location pages: `.pf-location__intro.has-backdrop` puts the room's first photo, blurred and faint, behind the top on dark swatches (`--pf-location-backdrop`, painted as an outset border image so it spans the screen without scrolling).
- **2026-10-31:** Location backdrop: the photo darkened rather than blurred, covering and fading out on every side, with a frosted oval under the map (`.pf-location__map::before`, backdrop-filter, radial mask).
- **2026-10-31:** Location backdrop: the photo is fixed to the window; the oval is replaced by `.pf-location__frost`, a backdrop blur masked to the map's floors (`pf-map__floors` + room hit areas, built by `js/location-plate.js`).
- **2026-10-31:** Location backdrop: no edge fades; one even 75% black wash, the least that keeps the top's text at AA over the photo's whitest pixel. It runs on to the first band.
- **2026-10-31:** Location parts follow /v2/special's section pattern: index rule (with its link), `.pf-location__part-title`, `.pf-section__lede`, then the body at the full width; `.pf-location__part-grid` removed. Backdrop: the room term's photo in `pf_location_backdrop`; the frost blurs only.
- **2026-10-31:** Location backdrop: monochrome and low in contrast instead of the black wash: a grey `color`-blend layer plus a 35-grey veil at 87% (tones 30-64 of 255, the same AA ceiling as 75% black).
- **2026-10-31:** Building map: `--pf-map-dim-text` (.6) for the other names and numbers while one room is lit, `--pf-map-dim` (.35) for hatching and dashed lines only; floor names and icons never dim. A page's own room rests lit after the draw-on (`data-map-here`), its name in the ink (`.pf-map__label.is-here`). Location backdrop: a top fall to 22rem; policy marks (`.pf-space-pin__button`) frosted; floor ink #b0b0b0 over it.
