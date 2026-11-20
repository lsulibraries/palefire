# AVES: migration corrections

Exhibition: AVES: A Survey of the Literature of Neotropical Ornithology (Hill Online Exhibitions, 2012), selections from the E. A. McIlhenny Natural History Collection, funded by the Coypu Foundation.
Old site: the Wayback Machine’s copies of exhibitions.blogs.lib.lsu.edu/?p=5206, all 20 pages and the 53 “Read more” pop-ups.
New page: `/v2/special/exhibitions/aves`, built by `scripts/palefire_exhibit_aves.php` from `scripts/data/aves.json`, then `palefire_exhibit_aves_typos.php`, `palefire_exhibit_aves_lead_captions.php` and `palefire_exhibit_aves_update.php`.

## Changed on the page

- The 20 pages are 19 galleries on one page. Each person or book is an entry with its own heading and anchor, so a link such as `#schauensee` lands on it.
- Each pop-up’s full biography replaces the shortened lead the gallery showed. The “Image:” and “Biography:” labels became the figure and the biography themselves. 43 entries have one, from 118 to 1,293 words. See Biographies, below.
- Links between pages point at the new anchors. The Biographies gallery is now a timeline of the lives in the order born, each opening its biography (2026-11-20), in place of its hand-written list of names; Humboldt’s life, told in Opening Scientific Exploration, is linked from its intro.
- Six galleries hang as walls (2026-11-20, `scripts/palefire_exhibit_aves_layout.php`): Collecting by Individuals, Museum Men, Collecting by Museums and Life Histories, each chapter’s essay as the wall’s intro and a tile for each person (a name tile where there is no portrait), and People and Places and Modern Fieldwork, each photograph an entry with a short title. Life dates moved from the headings into each entry’s Date; the anchors stay as they were.
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
- “took himwest” → “took him west” (Hernández, 2026-11-20)
- “Middleton Library” → “Main Library” (every text)

## To verify

- Schomburgk’s *Twelve Views* has no file in any of the image folders, so its figure has no image. Is there a scan?
- The lead images’ captions are taken from the print catalog’s illustration lists. Seven leads (Greenfield, Red-capped Manakin, the Andes, Buenaventura, Chubb paddling, the ongoing expedition and the Solitary Eagle) have no source and are decorative, with no caption.

- Modern Fieldwork, “Transportation on the Rio Shesha”: the caption ends with “Unfortunately, the lush scenery and abundant wildlife experienced by Weid are gone; today the Rio Doce valley is almost completely deforested.”, the last sentence of the Wied caption in People and Places, and opens a quotation from Stap it never closes. Both as on the 2012 site. Remove the sentence?

## New wording to check

- The 16 titles for the photographs on the People and Places and Modern Fieldwork walls (2026-11-20), each written from its caption: People and Places: The interior of Guiana, Wied on the Rio Doce, Spix and a ritual drink, Bates mobbed by aracaris, Camp in British Guiana, The Beebes at the barranca, Blair Beebe’s tent, Two castles, Field research today, Candles and computers. Modern Fieldwork: John O’Neill on the Rio Heath, 1977; On the Rio Shesha, 1987; Condor Expedition, 2006; Life in the Condor camp; Preparing Jocotoco antpittas; Tumbes, 2009. And the Biographies timeline’s intro.
- The 42 biography cards’ Summary and Role (`scripts/palefire_exhibit_aves_bio_cards.php`, 2026-11-20): two sentences each, drafted only from that entry’s own biography. Change any on the entry’s edit form; the script never overwrites a filled field.
- The 16 gallery lead captions, the credit line and alt text for every image.
