# New Orleans Jazz & Heritage Festival (jf): migration corrections

Old site: `/sites/all/files/oralhistory/presentations/jf/` (entry `audio.html`; listed on the old
exhibitions page as "Jazz Fest: 2014 Student Exhibit"). Note: this exhibition is about the Jazz &
Heritage Festival, not jazz funerals.
New page: `/v2/special/exhibitions/jazzfest`, built by `scripts/palefire_exhibit_jazzfest.php` from
`scripts/data/jazzfest.json`.

The page has three galleries: **Voices of the Festival** (7 clip entries), **Memory Maps** (3
drawings) and **In the Field** (3 text blocks).

## 1. Corrected

### Exhibition title
- Page `<title>` on the old About, Home, Pictures and Acknowledgements pages: "TEMPESTS: Storms in the
  Archives" → "New Orleans Jazz & Heritage Festival". Those four pages were copies of the Tempests
  exhibition's pages and are **not carried over**.
- Old clip pages C14–C41 (Tempests clips left in the folder, not linked from the jf menu) and the orphan
  page `I1.html` (broken image link `../photos/Memory1.jpg`) were **not carried over**.

### Voices of the Festival: speaker labels
In every clip, speaker labels are now bold, with the narrator's full name on first use and the surname
after that. Interviewers keep the surname only, because only surnames are known.
- Geraldine Vaucresson: "VAUCRESSON:" (first) → "GERALDINE VAUCRESSON:"
- Patrick Gallagher: "GALLAGHER:" (first) → "PATRICK GALLAGHER:". The second "GALLAGHER:" label, in the
  very next paragraph with no one speaking in between, was removed.
- Joseph "Cool" Davis: "DAVIS:" → "JOSEPH “COOL” DAVIS:"
- Tanya Huang: "HUANG:" → "TANYA HUANG:"
- Philip Giroir: "GIROIR:" (first) → "PHILIP GIROIR:"
- Terrence Osborne: "OSBORNE:" → "TERRENCE OSBORNE:"
- Wynoka Boudreaux: "BOUDREAX:" (misspelled) → "WYNOKA BOUDREAUX:"

### Voices of the Festival: transcript text
- Geraldine Vaucresson: "my great grandmother" → "my great-grandmother"
- Patrick Gallagher: "Guillain-Barre syndrome" → "Guillain-Barré syndrome"
- Joseph "Cool" Davis: "come down south. . . to see" → "come down south . . . to see"
- Terrence Osborne: missing closing quote added: "…so, can I get two weekends? [laughs]" →
  "…so, can I get two weekends?” [laughs]"
- Terrence Osborne: "I had to - even if I did the poster - I had to" → "I had to—even if I did the
  poster—I had to"
- All clips: straight quotes → typographic (curly) quotes.

### Voices of the Festival: captions
- Gallagher: "Patrick Gallagher." → "Patrick Gallagher, Tape No. 4298." Every caption now follows the
  pattern "Name, Tape No. ####." (see section 3).

### In the Field: Field Notes (old C12.html)
- Heading: "Reflections on Oral History with Jo "Cool" Davis" → "Field Notes: Reflections on Oral
  History with Jo “Cool” Davis" (the "Field Notes" label came from the old menu)
- "it struck both Charlotte and I" → "it struck both Charlotte and me"
- "shook both my and Charlotte's hands, motioned for us" → "…hands, and motioned for us"
- "t-shirt" / "t-shirts" → "T-shirt" / "T-shirts"
- "Jo "Cool" Davis" (nested double quotes) → "“Jo ‘Cool’ Davis”"
- "tell his tale - all of it" → "tell his tale—all of it"
- "Jazzfest" (twice) → "Jazz Fest"
- "Potential follow-up questions:" with typed numbers "1. 2. 3." → a bold label followed by a numbered list

### In the Field: Questions for Dwayne Breashears (old C11.html)
- "childhood/adolescence" → "childhood or adolescence"
- "Program Director" (twice) → "program director"
- "What kind of genres" → "What kinds of genres"
- "OZ's place" → "WWOZ’s place"
- The question "Did you have any influential family members… radio and music? Did you have a mentor
  figure…" was split into two list items.

### In the Field: review of *Number Our Days* (old C13.html)
- Heading: "Review of Number Our Days" → "Interview Reflections: A Review of *Number Our Days*" (label
  from the old menu; book title in italics throughout)
- "not only to the strength of the Jewish people, but as a testament also to the enduring nature" →
  "…but also to the enduring nature"
- "As Myerhoff takes one of her main informant, Schmuel's, advice, "Let people surprise you," (41)" →
  "As Myerhoff takes the advice of one of her main informants, Schmuel, “Let people surprise you” (41),"
- "experience. She nimbly weaves" → "experience. Myerhoff nimbly weaves"
- "Each other the elder's individual personalities are allowed to shine" → "Each of the elders’
  individual personalities is allowed to shine"
- "not merely 'for color,'" → "not merely “for color,”"
- "these people lives" → "these people’s lives"
- "would have lack authenticity" → "would have lacked authenticity"
- "the loving eye in which she gazed as her subjects" → "the loving eye with which she gazed at her subjects"
- "the elder's monologues" → "the elders’ monologues"
- "the elder's community" → "the elders’ community"
- "veritable goldmines" → "veritable gold mines"
- "the context of the lives' struggles - from poverty" → "the context of their lives’ struggles—from poverty"

### Restored to the speaker’s words

These transcript edits changed what the speaker said, so the old wording is back (2026-11-19). Spelling and punctuation fixes stay.

- Joseph “Cool” Davis: “People come and look at to you”, as the speaker said it. The first build had changed it (look at you).

## 2. To verify

- **Interviewers' full names.** Only surnames appear in the transcripts: Nguyen (Vaucresson), Bauer
  (Gallagher), Donner (Huang), Pontiff (Giroir) and Roux (Osborne). The Davis interviewers appear only
  as "Charlotte" and the unnamed author of the field notes. The Boudreaux clip has no interviewer at all.
  Because the names are incomplete, clip credits read "Name, Tape No. ####" instead of "interviewed by…".
- **Authors of the fieldwork texts.** The questions are credited in the old menu only as "Wilcox and
  Breashears", which reads as Wilcox (student) and Breashears (narrator). The field notes and the review
  have no named author.
- **Dwayne Breashears / WWOZ.** I inferred from the questions that he was WWOZ's program director;
  confirm the name spelling and title.
- **Interview year.** The exhibition is dated 2014 from the old exhibitions list, which is also the
  page's year tile. The interviews look like 2013 (the memory maps are dated 2013), but I couldn't
  confirm it, so no clip shows a year.
- **"Joseph" vs. "Jo" Davis.** The old menu says Joseph "Cool" Davis, but the field notes, his van and
  his shirt say Jo "Cool" Davis. Both are kept as written.
- **"Mark Courbet"** (Gallagher clip): spelling unconfirmed.
- **"Folks Village"** (Boudreaux clip): the festival's area is officially the Folklife Village. Left as
  spoken.
- **Left as spoken but possibly mistranscribed:** "we did my colors some pine cones with glitter" and
  "things I bought he would take it all loose" (Boudreaux); "They had a lot of New Orleans artists got
  used that would not come down south" (Davis). Worth checking against the audio.
- **"Schmuel"** (review): the book spells the name Shmuel. Kept as the student wrote it.
- **Giroir photo.** It is an old snapshot from a photo album (a bearded man in a hat with a child), not a
  photo of the interview. The old caption just said "Philip Giroir". Confirm who is pictured.
- **Who is pictured with the narrators.** The young women in the Vaucresson, Gallagher, Davis and Huang
  photos are probably the student interviewers, and the man in the Boudreaux photo is perhaps her father.
  None of them is named.
- **Memory Map 2** has no author written on it. Memory Map 1 is signed only with an illegible signature.

## 3. New wording to check

### Title page
- Subtitle: "Doing oral history at Jazz Fest" (adapted from the old page title "Doing Oral History at New
  Orleans Jazz & Heritage Festival")
- Credit: "A student exhibition from the T. Harry Williams Center for Oral History, LSU Libraries, 2014."
- Intro (entirely new, because the old exhibition had none of its own):
  > For more than four decades, the New Orleans Jazz & Heritage Festival has drawn musicians, artists,
  > cooks, and music lovers to the city each spring. In this 2014 student exhibition, students working
  > with the T. Harry Williams Center for Oral History recorded the people who make the festival what it
  > is: crew members who work it year after year, a poster artist, a street musician, a second line
  > culture bearer, and New Orleanians who remember what the festival meant for a segregated city.
  >
  > Alongside their excerpts are memory maps that festivalgoers drew of their own Jazz Fest, and pages
  > from the students’ fieldwork: interview questions, field notes, and a reflection on oral history
  > method.
- Year tile: 2014. Cover image: Memory Map 3.
- Gallery names (new): "Voices of the Festival", "Memory Maps" (from the old menu), "In the Field".

### Captions (lead captions repeat the first entry's caption)
- "Geraldine Vaucresson, Tape No. 4300." (also the Voices of the Festival lead caption)
- "Patrick Gallagher, Tape No. 4298."
- "Joseph “Cool” Davis, Tape No. 4303."
- "Tanya Huang, Tape No. 4307."
- "Philip Giroir, Tape No. 4311."
- "Terrence Osborne, Tape No. 4305."
- "Wynoka Boudreaux, Tape No. 4308."
- "Memory Map 1: Studio Inferno, Jazz Fest vendor, 1991–2013." (also the Memory Maps lead caption)
- "Memory Map 2: Old friends getting older." (a phrase written on the drawing)
- "Memory Map 3: Mary Ann Caffery, a day in her 2013 Jazz Fest."

### Clip credit lines
- "<Name>, Tape No. ####" for all seven clips (same names and numbers as the captions).

### In the Field
- New line under "Questions for Dwayne Breashears": "Prepared by Wilcox for an interview with Dwayne
  Breashears of WWOZ, New Orleans’ community radio station."

### Alt text (all new)
- vauc.jpg: Geraldine Vaucresson, in a striped top, stands arm in arm with a young woman in the doorway
  of a home.
- gallagher.jpg: Patrick Gallagher, long-haired and bearded, points to an aerial photograph of the
  festival grounds on a dining table as a young woman looks on; a 1979 Jazz Fest poster hangs behind them.
- davis.jpg: Joseph “Cool” Davis, seated in a wheelchair in a shirt printed “Jo Cool,” beside a young
  woman holding an orange folder.
- huang.jpg: Tanya Huang laughs with another young woman on a green couch, a black and white dog sitting
  between them.
- giroir.jpg: An older snapshot in a photo album: a bearded man in a dark hat and sunglasses with a
  smiling young child.
- osborne.jpg: Terrence Osborne in his studio points to a large painting in progress, sketched in colored
  lines on a shaped wooden panel; a finished painting of a blue shotgun house hangs above it.
- boudreaux.jpg: Wynoka Boudreaux, smiling, stands with an older man in a white bucket hat who has his
  arm around her shoulder, in front of a house and a parked car.
- memory1.jpg: Hand-drawn marker map of the festival grounds titled “Studio Inferno, 1991–2013, Jazz Fest
  Vendor,” tracing a route from home and Bayou Sauvage to the vendor’s booth locations, with food stops, a
  red pepper, and a small figure holding a pitchfork.
- memory2.jpg: Hand-drawn map of the festival grounds crowded with stick figures, stages, VIP areas, food
  booths, bikes, and notes such as “Jazz,” “Lagniappe Stage,” “Food Exhibits,” and “Old friends getting
  older.”
- memory3.jpg: Colored-pencil map titled “Mary Ann Caffery, a Day in My 2013 Jazzfest, My Path,” a
  looping arrowed route past music tents, food stands, and crafts booths, with notes like “Tasty!” and
  “Yum Yum.”
