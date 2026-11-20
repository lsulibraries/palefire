# The Baton Rouge Bus Boycott: migration corrections

Exhibition: The Baton Rouge Bus Boycott, an online exhibition of the T. Harry Williams Center for Oral History, made in summer 1998 by students of McKinley High School (guided by LaVada U. Taylor) and the LSU graduate class EDCI 5880 (Petra Munro and Pamela Dean), revised in 2002 by Mary Price and Matt Mullenix.
Old site: the five Showcase pages /boycott/introduction and nodes 284–287 (Chronology, Background, The People, Thanks), which link to the 2002 static site at /sites/all/files/sc/exhibitions/e-exhibits/boycott for the clips (.wav), Rev. Jemison’s story and the three student interviews. The text is from the Showcase pages, checked against the static pages. A copy of both is in `_reference/Oralhistory Exhibitions/presentations/boycott/`.
New page: `/v2/oralhistory/exhibitions/boycott` (with a 301 from `/v2/special/exhibitions/boycott`), built by `scripts/palefire_exhibit_boycott.php` from `scripts/data/boycott.json`. It is an oral history exhibition, so it sits with KLSU, Do Not Tear Up My Earth and Integrating LSU.

## Changed on the page

- Four galleries, all walls with a lead picture: Chronology, 1950–1955; Background; The People; Our Thanks. The Introduction is the exhibition’s opening text, and the old cover (the “Free Ride” car) its cover.
- Chronology: one dated entry per event (19), hung as wall labels read in place; the drivers’ strike (June 15, 1953) shows the newspaper headline from Integrating LSU. Lead: that headline.
- Background: Mary Price’s essay as five text plates (Reserved Seats, The Drivers’ Strike, The Boycott Begins, Mass Meetings, The Compromise), each with a written Summary, hung between the pictures as the essay placed them: eleven free ride photographs after The Boycott Begins, three of Horatio Thompson’s gas stations after Mass Meetings. Lead: the officer writing in his notebook (webbus31).
- The People: eight narrators, each a portrait with their clip, its transcript, their role and the old page’s biography (opened beside the transcript from lg). Rev. Jemison’s entry takes in his separate story page and its two interview excerpts, as one clip. Johnnie A. Jones has no clip: his portrait and biography. The three student interviews (Eddie Johnson, Patricia A. Robinson, James Batiste), separate pages before, are text plates with their transcripts. Lead: two young men at a free ride pickup (webbus32).
- Our Thanks: the thanks as a text plate, the class and the McKinley High School project as their photographs with their text, LaVada U. Taylor’s statement with her photograph with Fred Matthews, and the 2002 edition as a plate. Lead: McKinley High School (from Integrating LSU).
- Every lead is under 720 px, so each is shown whole and framed (“Show whole image”), and the 600 px cover is framed (“Frame the cover”), rather than blown up to fill the screen.
- Clips: the eight .wav excerpts (11 kHz mono, 22–77 s) as AAC .m4a, mono, −16 LUFS, with read-along timings (`scripts/data/boycott-vtt/`; Whisper matched 71–94% of each transcript’s words, the transcript’s wording kept). Each transcript was checked against the recording.
- Removed: the copyright warning under the Background photographs (“Click on each for a larger view… protected by copyright and digital watermark”), the old site’s “Hear excerpt” links (the clips play in place), and the Jemison “Read Interview Abstract” link to /services/oralhistory, which no longer works.

## Pictures

The old pages’ pictures are all small: the free ride photographs 474–486 px wide, the gas stations 347–705 px, the portraits 159–264 px, the class snapshots 199–351 px. No larger copies were found:

- **R: drive:** `Completed Exhibitions/pre-2005/Bus Boycott` holds only the 2002 title boards of a different presentation (*Life behind the Veil*, with Elayne Butler Bryant and Avis Baker White), no images. `Design_projects/Moment or Movement Pres LSU Symposium/Boycott.tif` is a 600 × 600 crop of webbus08, slightly sharper but cropped, so not used. Nothing else matched.
- **Louisiana Digital Library:** the interviews are in the McKinley High School Oral History Project (Jemison, Anderson, Tansil, Robinson, Batiste) and the Louisiana Civil Rights Movement Oral History Project (Johnnie Jones), as audio and transcripts with no photographs. The Ritchie and Thompson photographs aren’t there. East Baton Rouge Parish Library’s “Free Ride Hub” is a different photograph.

So each picture is the old page’s image, as large as it was: the Showcase copies, which match the 2002 site’s full-size files. Each is cropped to the photograph: the white mats around the portraits and snapshots, and the white or black scan edges of webbus15, 23 and 24. Sources:

| Picture | Source |
| --- | --- |
| Cover, “Free Ride” | /sites/default/files/2022-06/busboycott.jpg (600 px), the old Introduction’s cover; the clean original of the faded banner webbus29, which is left out |
| Free ride photographs (webbus02, 03, 06, 08, 10, 11, 30, 31, 32, 33) | 2002 site images/webbus*.JPG, courtesy of Ernest Ritchie |
| Gas stations (webbus15, 23, 24) | 2002 site images |
| Portraits (Jemison, Anderson, Spooner, Reed, Freeman, Laborde, Tansil, Greene, Jones) | 2002 site images, mats cropped |
| Class snapshots (brbus12, brbus01, brbus02) | 2002 site images, mats cropped |
| Strike headline, McKinley High School | Integrating LSU’s masters (`RPOE-web/masters/strike.jpg`, `mckinley.jpg`) |

The audio is the 2002 site’s .wav files. The LDL has the full interviews for Jemison, Anderson and Tansil (MP3), which could give cleaner copies of the same excerpts; see To verify.

## Corrected

| Where | Old | New |
| --- | --- | --- |
| Introduction | an historical timeline | a historical timeline |
| Introduction | This exhibit includes | This exhibition includes |
| Chronology / Drivers Ordered to Comply | Bus driver “man handles” black woman rider | A bus driver manhandles a black woman rider |
| Chronology / Money for Gasoline | McKinley High School -- At the mass meeting, money was collected | At the mass meeting at McKinley High School, money was collected |
| Chronology / King Calls Jemison | Martin Luther King calls | Martin Luther King, Jr., calls |
| Chronology, Background | the Boycott, Bus Boycott *(in running text)* | the boycott, bus boycott |
| Background | On 19 June 1953 · On 24 June 1953 | On June 19, 1953 · On June 24, 1953 |
| Background | short lived | short-lived |
| Jemison (biography) | One day when while he was out driving | One day while he was out driving |
| Jemison (biography) | Reverend Jemison said told the policeman | Reverend Jemison told the policeman |
| Jemison (biography) | what became know as the Baton Rouge Buss Boycott | what became known as the Baton Rouge Bus Boycott |
| Anderson (biography) | 4:30-10:00 a.m · fair skinned · what ever | 4:30 to 10:00 a.m. · fair-skinned · whatever |
| Reed (transcript) | What lead me | What led me *(as he says it)* |
| Reed (transcript) | un-courteous | uncourteous |
| Reed (biography) | Jessie Webb · self gain | Jesse Webb · self-gain |
| Freeman (transcript) | Kean’s laundry · ask her, ‘Why are you sitting here?’ | Kean’s Laundry · asked her, “Why are you sitting here?” *(as in Integrating LSU, from the same recording)* |
| Freeman (biography) | the Principal at McKinley | the principal at McKinley |
| Laborde (transcript) | told us to gaurd the house | told us to guard the house |
| Laborde (role) | bodygaurd to Rev. Jemison | Bodyguard to Rev. Jemison |
| Laborde (biography) | the Reverened · he providing armed guard of the Reverend’s home with four or five other men · two policeman | the Reverend · he and four or five other men kept an armed guard on the Reverend’s home · two policemen |
| Tansil (biography) | married since 19100 | married since 1950 *(the 2002 page)* |
| Tansil (biography) | the Federal Post Office, who requested their workers | the federal Post Office, which requested its workers |
| Greene (transcript) | a footsoldier patroling | a foot soldier patrolling |
| Greene (role) | bodygaurd to Rev. Jemison | Bodyguard to Rev. Jemison |
| Jones (alt text, caption) | Mr. Jonnie A. Jones | Johnnie A. Jones |
| Johnson | rememberance · pulling there financial resources · the friends of mind · Mr Eddie Johnson … Sr, | remembrance · pooling their financial resources · the friends of mine · Mr. Eddie Johnson … Sr., |
| Robinson | Porch� *(mis-encoded)* · Rev. Jamison · the early 50’s · late 70’s · she use to · where ever | Porche · Rev. Jemison · the early 1950s · late 1970s · she used to · wherever |
| Robinson (introduction) | She shares with Historian Erin Porche, stories about … She shared her story with student Erin Porche. | She shared her stories of the 1953 Baton Rouge bus boycott movement with student Erin Porche. |
| Batiste | Scottlandville · Rugon · use to · Blacks people · Picadilly · McCory · set up stairs · sone · aaround · til · pickett · botcott · becauase · law suit · negger · a another · every where · to to · They only reason · you parents · go the the back · lead up · picketting · What other ways to you think | Scotlandville · Rougon · used to · Black people · Piccadilly · McCrory’s · sit upstairs · some · around · till · picket · boycott · because · lawsuit · nigger · another · everywhere · to · The only reason · your parents · go to the back · led up · picketing · What other ways do you think |
| Batiste (introduction) | Mr. Lawerence Batiste · a Custodian at McKinley Sr. High School | Mr. Lawrence Batiste · a custodian at McKinley Senior High School |
| Our Thanks | Erin Porch · Rahshada Jenkins *(two spellings on the page)* | Erin Porche · Rashada Jenkins *(as in the LDL records)* |
| Our Thanks | Courtney Zachery *(the caption)* | Courtney Zachary *(the text)* |
| Our Thanks | Numerous portions of this exhibit | Numerous portions of this exhibition |

Also: curly quotes and apostrophes, spaced ellipses (“. . .”) and dashes, speaker labels in bold capitals in the three student interviews, American spelling. The student transcripts keep their own capitals (“Black”, “White”); the rest of the text keeps the old lowercase.

## Links

- Each narrator with an interview in the Louisiana Digital Library links to it at the end of their biography: Jemison (lsu-thwcoh-mckinley:54, 1995), Anderson (mckinley:1, 1998, Dawn Wallace), Tansil (mckinley:14, his 1996 interview on Black businesses), Robinson (mckinley:5), Batiste (mckinley:29), and Johnnie Jones, Sr. (lsu-thwcoh-civilrights:23).
- The 2002 edition note links to /v2/oralhistory and /v2/special instead of /oralhistory and /special.

## To verify

Left as written:

- **The end of the boycott:** the Chronology has it called off on June 23 and Ordinance 251 passed June 24; the Background ends the protest on June 24; Integrating LSU dates “Boycott Ends” June 26, 1953.
- **Who went to WLCS:** the Chronology sends Raymond Scott alone on June 19; the Background sends Jemison and Scott; Willis Reed’s biography credits Reed and Scott, and Chester Laborde’s says he rode there with Jemison.
- **The photographer’s name:** Ernest Ritchie here; Integrating LSU’s caption for the same Esso station says “Ernest Richie”. And whether the gas station photographs are Ritchie’s or Horatio Thompson’s: the thanks names both, the Background credits Ritchie only for the free ride pictures. The captions credit no one for the stations.
- **Interview years:** Jemison’s excerpts are from 1995 and Anderson’s from 1998 (LDL). The others’ clips carry no year: the exhibition says its interviews were made in summer 1998, but Tansil’s LDL interview is from 1996 and may be the source.
- Laborde’s “Dr. Jemison [sic]”: Jemison was later known as Dr. Jemison, so the [sic] may not be needed.
- Robinson’s mother is “Mrs. Alamo Robinson” here and “Ella May Robinson” in the LDL; Batiste’s mother “Alberta” here and “Albertha Sims Batiste” in the LDL.
- Robinson’s interview: the question “If you rode a segregated bus, how did you feel about riding it?” is answered with the boycott’s leaders, so a question seems to be missing from the transcript.
- Batiste’s “Julius Bond” (perhaps Julian Bond) and “Romano’s”.
- Courtney Zachary or Zachery.
- The biographies are in the present tense of 1998 (“where he still lives”, “Currently, …”), and Freddie W. Greene’s and Willis Reed’s opinions are their own; left as written.
- The clips are the 2002 site’s 11 kHz recordings. Cutting the same excerpts from the LDL’s full interviews (Jemison, Anderson) would sound better.

## New wording to check

- The subtitle (“A Recaptured Past”, from the 2002 site’s title page) and the credit line.
- The 19 Chronology headings, the five Background plate titles and the titles of the 14 pictures, the plates’ Summaries (Background, the three student interviews, Our Thanks, The 2002 Edition), the clip titles and their one-line introductions, each narrator’s role (from the old page’s line under their name) and the gallery introductions for Background and The People.
- The captions of the free ride and gas station photographs and of the class snapshots, the four lead captions, and the alt text for every picture.
- The links to the Louisiana Digital Library at the end of six biographies.
