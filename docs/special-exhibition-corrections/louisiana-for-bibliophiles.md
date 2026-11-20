# Louisiana for Bibliophiles: migration corrections

Exhibition: Louisiana for Bibliophiles: A History of Reading in the Bayou State, LSU Libraries Special Collections, Hill Memorial Library, June–September 2011, curated by Tara Zachary Laver and Michael Taylor; the online version designed by Rachel Miller.
Old site: ten Showcase pages, /louisianaforbibliophile/introduction and its siblings (nodes 113705–113714: Introduction, Early Days, Creole Authors, African Americans, Women’s Reading and Novels, Practical Reading, Newspapers, Libraries, Index, About), whose characters were partly mis-encoded.
New page: `/v2/special/exhibitions/louisiana-for-bibliophiles` (unpublished), built by `scripts/palefire_exhibit_louisiana_for_bibliophiles.php` from `scripts/data/louisiana-for-bibliophiles.json`.

## Changed on the page

- The Introduction is the page’s intro; the seven sections are galleries, every one a wall with a lead picture: Early Days, Creole Authors, African Americans, Women’s Reading and Novels, Practical Reading, Newspapers, Louisiana Libraries. About the Exhibition is an eighth wall: its paragraph as the gallery text, then two plates, Curators and thanks, and Index of items.
- Each stretch of text between pictures is a text plate (a new sentence-case heading, a written summary, the text unchanged), hung before the pictures it introduces. The long sections are split where the subject turns: the subscription library (the library; its members and David McKeehan), Sidonie de la Houssaye (her life; her grandmother’s diary), Thomas Affleck (his life; the almanac; DeBow’s Review), Sherman (the books list; the library lost twice), the public libraries (the Library and Lyceum Society; Carnegie and Alexandria).
- Books shown in several pages (Du Gour, La Nouvelle Atala, the Teachers’ Journal, The Young Lady’s Guide, the Wright letters and journal, the Pan Gnostics minute book, Affleck’s list, almanac and DeBow article, Sherman’s list and letter) are one tile each that pages through them.
- Captions: the old pages’ citation in bold, the call number or collection on the next line.
- The Index: its “See …” lines link to the galleries on the page.
- “the postcard to the left” → “shown on the postcard here” (Alexandria), since the wall places it.
- Leads: Early Days, Laussat’s bookplate; Creole Authors, the Nouvelle Atala spread; African Americans, the Teachers’ Journal cover; Women’s Reading, The Young Lady’s Guide at “Novel Reading”; Practical Reading, Huey Long’s Bible; Newspapers, the 1932 student journalists; Louisiana Libraries, the Camp Claiborne library; About, Hill Memorial Library about 1902. The cover is the Hill reading room, 1903–1904 (2,560 × 2,023), filling the frame. The Teachers’ Journal cover is the only lead under 1280 × 720 (819 × 1,000; no scan exists), so African Americans’ lead is set to Show whole image: shown whole over a blur of itself rather than enlarged. The other leads are 2,560 px and fill the screen.
- Huey Long’s Bible: the old page showed only the notes; the new page shows the whole opening, the map of Palestine beside the notes.

## Corrected

- Repeated paragraphs removed: Esther Wright (“Indeed, reading was a type of discipline…” appeared twice), Sherman (“When Louisiana seceded…” and “That collection proved short-lived…” each twice).
- Fisher’s pamphlet: “Read—and share with your neighbors” → “Read, and lend to your Neighbors,” as printed on it (shown in the picture), and “the pamphlet’s title page contains” → “the pamphlet carries” (it is the mailing panel).
- Berquin-Duvallon’s quotation: its closing quotation mark put back.
- “LLLMVC” → “LLMVC”
- “Fenelon’s” → “Fénelon’s”
- “Pointe a la Hache” → “Pointe à la Hache”
- “Pouponne et Balthazaar” → “Pouponne et Balthazar”
- Cherbonnier: “appendre le français” → “apprendre le français”
- “two-hundred years” → “two hundred years”
- “volumes–mostly history–” → “volumes—mostly history—”
- “Board of Supervisors Member” → “Board of Supervisors member”
- “LOUISiana Digital Library” → “Louisiana Digital Library”
- Affleck’s almanac: its full title, *Southern Rural Almanac, and Plantation and Garden Calendar*.
- Book and newspaper titles in italics throughout; curly quotes and apostrophes; en dashes in ranges (1915–16, 1885–1886); characters that were mis-encoded on the Showcase pages restored (é, à, ’, —).
- Gallery names as the old menu had them: “Newspapers”, “Women’s Reading and Novels”; “Libraries” → “Louisiana Libraries”.

## Links

- Sidonie de la Houssaye’s portrait caption links to a Louisiana Digital Library search for her papers (82 items).
- The Concordia Eagle’s “Also in Chronicling America” is kept as text, as on the old page.

## To verify

Left as written:

- Henrietta Brock’s source: the old caption reads *Proceedings of the Thirty-Seventh Annual Session of the Louisiana Press Association* (1916); the scan’s folder on the share says “thirty-sixth annual Session”, 1915–1916.
- Cherbonnier’s title, “enfans” (the 1829 spelling).
- The Laussat bookplate shows “Ph. Favrot” written above and below it (Philogène Favrot, as the text says); the lead caption now names his signature.
- The curators’ and thanks’ titles are as of 2011.

## New wording to check

- The 45 plate titles and their summaries.
- The picture tiles’ short titles.
- The cover’s object label (Reading Room, Hill Memorial Library, 1903–1904).
- Alt text for all 74 pictures, written from each picture.

## Images

From the exhibition’s files on the Special Collections share, `R:\SpecialCollections\Exhibitions\Completed Exhibitions\Lecture Hall Exhibitions\Pre2013\LA for Bibliophiles\`: 58 pictures from the TIFF scans in `0_Scanned Images`, each cropped to the old page’s framing (matched by image correlation, checked by eye) and saved up to 2,560 px; the old pages’ were 1,000 px. 16 have no scan on the share; they are the 1,000 px files in `additional images` or the old page’s own (Fisher, Testut). The Louisiana Digital Library was searched for them (Confederate Speller, Concordia Eagle, vieux Salomon, Colored Teachers) and has none. Copied to the server as `~/bibliophiles-masters`.

- `hume-history-of-england-1763.jpg` (1,531 × 2,560): scan `DA 30 H9 1763 - The History of England/HistoryofEngland004.tif`, cropped as the old page
- `helvetius-de-l-homme-1774.jpg` (1,404 × 2,560): scan `B2043 D33 1774 v. 1 - De l Homme/DelHomme004.tif`, cropped as the old page
- `laussat-memoires-1831.jpg` (1,645 × 2,560): scan `Vault 20 - Laussat - Memoires sur ma vie/LaussatMemoires001.tif`, cropped as the old page
- `laussat-bookplate-mirabeau-1792.jpg` (1,928 × 2,560): scan `DC 146 .M7A15 - Travaux de Mirabeau/travaux0000_Inside Cover bookplate.tif`, cropped as the old page
- `du-gour-collection-1793-frontispiece.jpg` (1,661 × 2,560): scan `DC 136 A2 D44 v. 2 - Collection des meilleurs ouvrages/CollectionMeilleursOuvrages009-010.tif`, cropped as the old page
- `du-gour-collection-1793-title.jpg` (1,546 × 2,560): scan `DC 136 A2 D44 v. 2 - Collection des meilleurs ouvrages/CollectionMeilleursOuvrages009-010.tif`, cropped as the old page
- `berquin-duvallon-vue-1803.jpg` (1,521 × 2,560): scan `F 373 B53 - Vue de la Colonie/Colonie008.tif`, cropped as the old page
- `st-francisville-library-bookplate.jpg` (1,473 × 822): scan `F 269 .R17 v.1 - The History of South Carolina/SouthCarolina001.tif`, cropped as the old page
- `st-francisville-library-receipt-1815.jpg` (2,560 × 1,417): scan `1382 - James Pirrie Papers - Box 1 Folder 4/StFrancisvilleLibraryCoMemberReceipt_front.tif`, cropped as the old page
- `lavie-catalogue-1842.jpg` (1,731 × 2,560): scan `AEU0459 - Catalogue de la librairie de Gabriel Lavie/LavieCatalogue001.tif`, cropped as the old page
- `mercier-portrait-1904.jpg` (2,069 × 2,560): scan `F 369 F742 v4 - Fortier's History of Louisiana v4/Portrait of Alfred Mercier 300percent.tif`, cropped as the old page
- `mercier-habitation-saint-ybars-1881.jpg` (1,391 × 2,560): scan `PQ 3939 M5H3 1881 - L'Habitation Saint-Ybars/Title Page.tif`, cropped as the old page
- `rouquette-portrait-1913.jpg` (1,724 × 2,560): scan `PQ 3939 R6 E53 - Life of the Abbe Rouquette/Portrait.tif`, cropped as the old page
- `rouquette-nouvelle-atala-1879-portrait.jpg` (1,814 × 2,560): scan `LA Rare PQ 3939 R6A65 - La Nouvelle Atala/Portrait and Title Page.tif`, cropped as the old page
- `rouquette-nouvelle-atala-1879-title.jpg` (1,608 × 2,560): scan `LA Rare PQ 3939 R6A65 - La Nouvelle Atala/Portrait and Title Page.tif`, cropped as the old page
- `rouquette-nouvelle-atala-1879-spread.jpg` (2,560 × 2,039): scan `LA Rare PQ 3939 R6A65 - La Nouvelle Atala/Portrait and Title Page.tif`, whole
- `queyrouze-photograph.jpg` (1,937 × 2,508): scan `1204 - Queyrouze (Leona) Papers - UU 70/1204b05bf042-001.tif`, cropped as the old page
- `queyrouze-vision-manuscript.jpg` (1,572 × 2,560): scan `1204 - Queyrouze (Leona) Papers - UU 70/1204b06af045-002a_Vision page 1.tif`, cropped as the old page
- `de-la-houssaye-portrait.jpg` (1,652 × 2,560): scan `04-02-1950_Page 06 - Portrait of Sidonie only, descreened.tif`, cropped as the old page
- `cable-strange-true-stories-manuscripts.jpg` (2,560 × 1,857): scan `PS 1244 S8 1889 - Strange True Stories of Louisiana/Photo of Manuscripts.tif`, cropped as the old page
- `police-code-west-feliciana-1851.jpg` (686 × 1,000): `additional images/police code.jpg` (no scan found)
- `fisher-concessions-and-compromises-1860.jpg` (500 × 331): old page's image (no scan found)
- `testut-vieux-salomon-1872.jpg` (380 × 576): old page's image (no scan found)
- `colored-teachers-journal-1939-1.jpg` (819 × 1,000): `additional images/la colored teachers journal 1.jpg` (no scan found)
- `colored-teachers-journal-1939-2.jpg` (1,118 × 1,000): `additional images/la colored teachers journal 2.jpg` (no scan found)
- `colored-teachers-journal-1939-3.jpg` (1,133 × 1,000): `additional images/la colored teachers journal 3.jpg` (no scan found)
- `colored-teachers-journal-1939-4.jpg` (1,000 × 1,000): `additional images/la colored teachers journal 4.jpg` (no scan found)
- `negro-librarian-in-louisiana-1940.jpg` (908 × 1,000): `additional images/negro librarian.jpg` (no scan found)
- `picken-waltham-1833.jpg` (1,455 × 2,560): scan `PR 5178 .P38 W3 1833 - Waltham/Waltham014.tif`, cropped as the old page
- `newcomb-young-ladys-guide-1853-title.jpg` (1,661 × 2,560): scan `241 N43 YX - The Young Ladys Guide/YoungLadysGuide006.tif`, cropped as the old page
- `newcomb-young-ladys-guide-1853-p196.jpg` (1,583 × 2,560): scan `241 N43 YX - The Young Ladys Guide/YoungLadysGuide196-197.tif`, cropped as the old page
- `newcomb-young-ladys-guide-1853-p197.jpg` (1,645 × 2,560): scan `241 N43 YX - The Young Ladys Guide/YoungLadysGuide196-197.tif`, cropped as the old page
- `newcomb-young-ladys-guide-1853-spread.jpg` (2,560 × 2,011): scan `241 N43 YX - The Young Ladys Guide/YoungLadysGuide196-197.tif`, whole
- `esther-wright-letter-1857-1.jpg` (1,639 × 2,560): scan `3362-WrightBoydFamilyPapers/Box 1/EstherWrighttoSarahWright_4-19-1857_frontback.tif`, cropped as the old page
- `esther-wright-letter-1857-2.jpg` (1,646 × 2,560): scan `3362-WrightBoydFamilyPapers/Box 1/EstherWrighttoSarahWright_4-19-1857_inside.tif`, cropped as the old page
- `esther-wright-letter-1857-3.jpg` (1,644 × 2,560): scan `3362-WrightBoydFamilyPapers/Box 1/EstherWrighttoSarahWright_4-19-1857_inside.tif`, cropped as the old page
- `esther-wright-letter-1857-4.jpg` (1,648 × 2,560): scan `3362-WrightBoydFamilyPapers/Box 1/EstherWrighttoSarahWright_4-19-1857_frontback.tif`, cropped as the old page
- `mary-cornelia-wright-books-read-1.jpg` (1,793 × 2,560): scan `3362-WrightBoydFamilyPapers/Box 5/Journal of Mary Cornelia Wright 1861-67/MCWJournal118.tif`, cropped as the old page
- `mary-cornelia-wright-books-read-2.jpg` (1,784 × 2,560): scan `3362-WrightBoydFamilyPapers/Box 5/Journal of Mary Cornelia Wright 1861-67/MCWJournal118.tif`, cropped as the old page
- `pan-gnostics-minute-book-1885-1.jpg` (2,066 × 2,560): scan `1282 - Grace King Collection Minute Book/MinuteBook028-029.tif`, cropped as the old page
- `pan-gnostics-minute-book-1885-2.jpg` (2,039 × 2,560): scan `1282 - Grace King Collection Minute Book/MinuteBook028-029.tif`, cropped as the old page
- `affleck-list-of-papers-1.jpg` (1,615 × 2,560): scan `3, 1263 - Thomas Affleck Papers - W 115 - 17/ListofUnboundAgricandPapers_front.tif`, cropped as the old page
- `affleck-list-of-papers-2.jpg` (1,628 × 2,560): scan `3, 1263 - Thomas Affleck Papers - W 115 - 17/ListofUnboundAgricandPapers_back.tif`, cropped as the old page
- `affleck-almanac-1851-cover.jpg` (1,486 × 2,560): scan `AY81 .F3 A23 1851 - Afflecks Southern Rural Almanac/AfflecksAlmanac000.tif`, cropped as the old page
- `affleck-almanac-1851-raspberries.jpg` (1,311 × 2,560): scan `AY81 .F3 A23 1851 - Afflecks Southern Rural Almanac/AfflecksAlmanac075.tif`, cropped as the old page
- `affleck-debows-review-1856-p717.jpg` (1,453 × 2,560): scan `HF 1 D2 - Debow's Review Vol 19/p 717.tif`, cropped as the old page
- `affleck-debows-review-1856-p718.jpg` (1,486 × 2,560): scan `HF 1 D2 - Debow's Review Vol 19/p 718-719.tif`, cropped as the old page
- `affleck-debows-review-1856-p719.jpg` (1,452 × 2,560): scan `HF 1 D2 - Debow's Review Vol 19/p 718-719.tif`, cropped as the old page
- `ewell-medical-companion-1807.jpg` (1,125 × 1,000): `additional images/planters medical companion.jpg` (no scan found)
- `marcet-conversations-on-chemistry-1824.jpg` (1,127 × 1,000): `additional images/conversations on chemistry.jpg` (no scan found)
- `abrege-grammaire-espagnole-1812.jpg` (698 × 1,000): `additional images/abrege de la grammaire.jpg` (no scan found)
- `cherbonnier-alphabet-1829.jpg` (578 × 1,000): `additional images/cherbonnier alphabet.jpg` (no scan found)
- `granet-latin-grammar-1833.jpg` (513 × 1,000): `additional images/latin grammar.jpg` (no scan found)
- `confederate-speller-1864.jpg` (626 × 1,000): `additional images/confederate speller.jpg` (no scan found)
- `louisiana-english-grammar-1865.jpg` (608 × 1,000): `additional images/la english grammar.jpg` (no scan found)
- `huey-long-bible-endpapers.jpg` (2,560 × 1,814): scan `3653 - Don Devol Papers - Huey Long Bible/HueyLongBiblePalestineMap001.tif`, whole
- `nicholson-pearl-rivers.jpg` (1,604 × 2,560): scan `PS 2464 N2 Z66 1932 - Pearl Rivers/Photo of Eliza Jane Nicholson.tif`, cropped as the old page
- `brock-press-association-1916.jpg` (1,874 × 2,560): scan `PN 4844 L6L6 1915-1916 - Proceedings of the thirty-sixth annual Session of the Louisiana Press Association/Photo of Mrs. J. Vol Brock.tif`, cropped as the old page
- `mcgrath-udc-scrapbook.jpg` (1,620 × 2,560): scan `0428 - United Daughters of the Confederacy, Louisiana Division Collection - J-3/Scrapbook 15, photocopy of photo of Mattie McGrath.tif`, cropped as the old page
- `concordia-eagle.jpg` (683 × 1,000): `additional images/concordia eagle.jpg` (no scan found)
- `student-journalists-1932.jpg` (2,560 × 1,539): scan `1730 Osborn.tif`, cropped as the old page
- `sherman-books-list-1860-1.jpg` (1,587 × 2,560): scan `Box 57 - Administrative Files, folder 1028-1052/oprb57f1044-01_Books Bought For D Boyd by Sherman 01.tif`, cropped as the old page
- `sherman-books-list-1860-2.jpg` (1,579 × 2,560): scan `Box 57 - Administrative Files, folder 1028-1052/oprb57f1044-02_Books Bought For D Boyd by Sherman 02.tif`, cropped as the old page
- `sherman-books-list-1860-3.jpg` (1,605 × 2,560): scan `Box 57 - Administrative Files, folder 1028-1052/oprb57f1044-03_Books Bought For D Boyd by Sherman 03.tif`, cropped as the old page
- `sherman-books-list-1860-4.jpg` (1,582 × 2,560): scan `Box 57 - Administrative Files, folder 1028-1052/oprb57f1044-04_Books Bought For D Boyd by Sherman 04.tif`, cropped as the old page
- `sherman-letter-1873-1.jpg` (1,573 × 2,560): scan `0890 - David French Boyd papers. William T. Sherman letters, 1859-1891/0890b01f03b18730520-01.tif`, cropped as the old page
- `sherman-letter-1873-2.jpg` (1,569 × 2,560): scan `0890 - David French Boyd papers. William T. Sherman letters, 1859-1891/0890b01f03b18730520-02.tif`, cropped as the old page
- `sherman-letter-1873-3.jpg` (1,574 × 2,560): scan `0890 - David French Boyd papers. William T. Sherman letters, 1859-1891/0890b01f03b18730520-02.tif`, cropped as the old page
- `sherman-letter-1873-4.jpg` (1,569 × 2,560): scan `0890 - David French Boyd papers. William T. Sherman letters, 1859-1891/0890b01f03b18730520-01.tif`, cropped as the old page
- `hill-memorial-library-1902.jpg` (2,560 × 1,846): scan `a50000032.tif`, cropped as the old page
- `hill-reading-room-1903.jpg` (2,560 × 2,023): scan `Interior Hill a50000034.tif`, cropped as the old page
- `library-and-lyceum-society-notice.jpg` (1,366 × 2,211): scan `Mss 1148 Public School Library and Lyceum Society Notice.tif`, cropped as the old page
- `alexandria-library-postcard-1910.jpg` (2,560 × 1,671): scan `3645b01alexandria032.tif`, cropped as the old page
- `camp-claiborne-library-1941.jpg` (2,560 × 2,064): scan `5007b01f16-003_Camp Claiborne Library.tif`, cropped as the old page
