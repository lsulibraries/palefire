# AVES: migration corrections

Exhibition: AVES: A Survey of the Literature of Neotropical Ornithology (Hill Online Exhibitions, 2012), selections from the E. A. McIlhenny Natural History Collection, funded by the Coypu Foundation.
Old site: the Wayback Machine’s copies of exhibitions.blogs.lib.lsu.edu/?p=5206, all 20 pages and the 53 “Read more” pop-ups.
New page: `/v2/special/exhibitions/aves`, built by `scripts/palefire_exhibit_aves.php` from `scripts/data/aves.json`, then `palefire_exhibit_aves_typos.php`, `palefire_exhibit_aves_lead_captions.php` and `palefire_exhibit_aves_update.php`.

## Changed on the page

- The 20 pages are 19 galleries on one page. Each person or book is an entry with its own heading and anchor, so a link such as `#schauensee` lands on it.
- Each pop-up’s full biography replaces the shortened lead the gallery showed. The “Image:” and “Biography:” labels became the figure and the biography themselves. 43 entries have one, from 118 to 1,293 words. See Biographies, below.
- Links between pages point at the new anchors. The Biographies gallery keeps its hand-written list of names.
- Entry names written in capitals use Capital Case (Francisco Hernández, Georg Markgraf, Juan Ignacio Molina and others).
- 16 of the 17 images the 2012 site showed without a 4K file were added from “Section 4 (additions)”, made from the catalog’s TIFFs.
- Unused 4K plates and photographs became the galleries’ lead images.

## Corrected

Names (entry titles, so also the contents and “In this gallery”):

- “Alcide d’Orbigny(1802-1857)” → “Alcide d’Orbigny (1802-1857)”
- “John Gould 1804-1881)” → “John Gould (1804-1881)”
- “Robert Schomburgk 1804-1865)” → “Robert Schomburgk (1804-1865)”
- “William Phelps, Jr.(1902-1988)” → “William Phelps, Jr. (1902-1988)”
- “Louis Jean Pierre Vieillot (1610-1644)” → “Louis Jean Pierre Vieillot (1748-1831)” (the dates were Markgraf’s)
- “Francis L. De LaPorte” → “Francis L. de Laporte”

Text:

- “tyrant- flycatchers” → “tyrant-flycatchers”; “fast- growing” → “fast-growing”; “ninety- three” → “ninety-three”
- “Markgraf’s *Historia naturalis Brasiliae,* (Leiden” → “Markgraf’s *Historia naturalis Brasiliae* (Leiden”
- “hummingbirds;and” → “hummingbirds; and”; “rooms,artificial” → “rooms, artificial”; “mammals,and” → “mammals, and”; “setting,and” → “setting, and”
- “habitudes.Rio” → “habitudes. Rio”
- “to the base camp,” → “to the base camp.” (end of a paragraph)
- “passes rapidly from from blossom” → “passes rapidly from blossom”
- “jail– “a putrid” → “jail – “a putrid”; “of the world –that” → “of the world – that”; “and others– who” → “and others – who”; “ornithologists– Ridgway” → “ornithologists – Ridgway”; “perfect mentor– I” → “perfect mentor – I”
- “(Weimer, 1825-33)” → “(Weimar, 1825-33)”; “(Weimer, 1822-1831)” → “(Weimar, 1822-1831)”
- “Charles Chubbs’s” → “Charles Chubb’s”
- “Middleton Library” → “Main Library” (every text)

## To verify

- Schomburgk’s *Twelve Views* has no file in any of the image folders, so its figure has no image. Is there a scan?
- The lead images’ captions are taken from the print catalog’s illustration lists. Seven leads (Greenfield, Red-capped Manakin, the Andes, Buenaventura, Chubb paddling, the ongoing expedition and the Solitary Eagle) have no source and are decorative, with no caption.

## New wording to check

- The 16 gallery lead captions, the credit line and alt text for every image.
