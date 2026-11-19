# The Relentless Pursuit of “Equal”: Integrating LSU: migration corrections

Old presentation: https://lib.lsu.edu/sites/all/files/oralhistory/presentations/RPOE/index.html (the 2014 listening station: TimelineJS timelines in `main/*.jsonp` and an About page; the copy is in `_reference/Oralhistory Exhibitions/presentations/RPOE/`).
New page: built by `scripts/palefire_exhibit_integrating_lsu.php` from `scripts/data/integrating-lsu.json`, which `RPOE-web/gen.js` writes from the old timelines.

How the old station maps to the new page:
- The overview timeline (`main`) became **The Nation** (its national events) and the opening **Timeline** gallery (every dated event in one list).
- The LSU, 1953 bus boycott and East Baton Rouge timelines became the walls **Integrating LSU**, **The 1953 Bus Boycott** and **East Baton Rouge**, each split into periods.
- The About page became **About the Exhibition**.
- The separate 1953 timeline (`1953_jsonp`) was not used. It retells the bus boycott timeline with different headlines, and the bus timeline has the clips.

## 1. Corrected

### Typos and spelling (in the event text, unless noted)

| Where | Old | New |
| --- | --- | --- |
| Integrating LSU / LSU Stands on Segregation (headline) | LSU Stands on Segretation | LSU Stands on Segregation |
| Integrating LSU / First Black Graduate | a Master’s Degree in Education | a master’s degree in education |
| Integrating LSU / Tureaud Jr. Sues LSU | A. P. Tureaud, Junior successfully sues | A. P. Tureaud, Jr., successfully sues |
| Integrating LSU / Tureaud Jr. Sues LSU (transcript) | We were playing racquet ball | We were playing racquetball |
| Integrating LSU / Wilson Sues LSU Law | Here, Tureaud, Jr. recalls his father’s relationships | Here, A. P. Tureaud, Jr., recalls his father’s relationships |
| Integrating LSU / Undergraduates Sue to Integrate LSU (transcript) | about two weeks we after we had been in school | about two weeks after we had been in school |
| Integrating LSU / Undergraduates Sue to Integrate LSU (transcript) | who’s only choice might be LSU | whose only choice might be LSU |
| Integrating LSU / Kerry Pourciau Elected SGA President (transcript) | well over twenty-years ago | well over twenty years ago |
| Integrating LSU / Kerry Pourciau Elected SGA President | In 1972, Kerry Louis Pourciau was the first… | Kerry Louis Pourciau was the first… *(the year is now the entry’s date)* |
| The 1953 Bus Boycott / Compliance Ordered | A bus driver ’man handled’ a black woman | A bus driver manhandled a black woman |
| The 1953 Bus Boycott / Boycott Announced | the United Defense Leage (UDL) | the United Defense League (UDL) |
| The 1953 Bus Boycott / Strike Ends, Boycott Begins (transcript label) | Alemenia Freeman | Almenia Freeman |
| The 1953 Bus Boycott / Struggle Continues (credit) | Almenia Freeman, activitst | Almenia Freeman, activist |
| The 1953 Bus Boycott / Mass Meetings | June 20th: … June 21st: … June 22nd: | June 20: … June 21: … June 22: |
| The 1953 Bus Boycott / King Learns from Baton Rouge (transcript) | always, everytime, anytime in violation | always, every time, anytime in violation |
| The 1953 Bus Boycott / King Learns from Baton Rouge (transcript) | saw that difference That the church house | saw that difference. That the church house |
| East Baton Rouge / Integrating Recreation (transcript) | rather than have us integrate that pool, they closed it | …they closed it. *(period added)* |
| East Baton Rouge / White-Only Golf Courses | in a predominately black neighborhood | in a predominantly black neighborhood |
| East Baton Rouge / Kress Sit-In | nine more students sat-in at various locations | nine more students sat in at various locations |
| East Baton Rouge / CORE Sit-Ins | In Decemeber, members | In December, members |
| East Baton Rouge / Cox v. Louisiana | National CORE represntative | National CORE representative |
| East Baton Rouge / Backlashes Against Activists (transcript) | There was a hole it the picture glass window | There was a hole in the picture glass window |
| East Baton Rouge / Backlashes Against Activists (transcript) | Every time he was involved in , like when | Every time he was involved in, like when |
| East Baton Rouge / Police Shoot Youth | Their deaths lead to marches | Their deaths led to marches |
| The Nation / Vietnam War | the first fully-integrated deployment | the first fully integrated deployment |
| The Nation / World War II, Korean War, Vietnam War | (1939 – 1945), (1950 –1953), (1956 – 1975) | (1939–1945), (1950–1953), (1956–1975) |

### Headlines (entry titles)

| Old | New |
| --- | --- |
| SU Law School Opens | Southern University Law School Opens |
| LSU Law Admits Collins, Morial | LSU Law Admits Collins and Morial |
| Payne Sues LSU Grad School | Payne Sues LSU Graduate School |
| Foister Sues LSU Med School | Foister Sues LSU Medical School |
| Alpha Kappa Alpha established at LSU / Harambe established at LSU / Kerry Pourciau elected SGA President | …Established at LSU / …Elected SGA President *(title case like the others)* |
| LSU Names Building for A.P. Tureaud, Sr. | LSU Names a Building for A. P. Tureaud, Sr. |
| MLK Learns from Baton Rouge | King Learns from Baton Rouge |
| Discrimination in WWII | Discrimination in World War II |
| MLK Assassination | King Assassinated |
| Garner v. Louisiana, Cox v. Louisiana, Plessy v. Ferguson | The case names in italics |

### Dates

Every event now has its own date, shown under its heading and used for the Timeline gallery’s order.

| Where | Old | New |
| --- | --- | --- |
| East Baton Rouge / SU Students Killed | November 17, 1972 | November 16, 1972 *(the day of the shooting; the newspaper front page shown is from the 17th)* |
| The Nation / Plessy v. Ferguson | April 13 – May 18, 1896 | May 18, 1896 *(the decision)* |
| East Baton Rouge / Kress Sit-In | March 28–29, 1960 | March 28, 1960 |
| East Baton Rouge / Capitol March | March 29–30, 1960 | March 29, 1960 |
| East Baton Rouge / 25 CORE Arrests | December 13–14, 1961 | December 13, 1961 |
| East Baton Rouge / Garner v. Louisiana | October 18 – December 11, 1961 | October–December 1961 |
| East Baton Rouge / SU Students Expelled | January 16–18, 1962 | January 1962 |
| East Baton Rouge / Backlashes Against Activists | August 1, 1963 | August 1963 |
| Integrating LSU / Undergraduates Sue to Integrate LSU | 1964–1968 | 1964 |
| The Nation / Vietnam War | December 1956 – April 30, 1975 | 1956–1975 |
| Integrating LSU / Harambe Established at LSU | 1964–1968 | no date *(see To verify)* |
| The 1953 Bus Boycott / King Learns from Baton Rouge | July 1953 | no date *(it describes a comparison, not an event)* |

### Events merged or reordered

- **Kept once:** these events were on both the overview timeline and a local one. Each now appears once, on the local wall. The texts were the same unless noted.
  - Wilson Sues LSU Law
  - Capitol March (the East Baton Rouge text is kept; it is longer and names Felton Clark)
  - 1953 Bus Boycott (the same as Strike Ends, Boycott Begins)
  - Department Store Integration
  - MLK Assassination
- **Civil Rights Act Becomes Law** (East Baton Rouge) and **Civil Rights Act** (overview) had the same text. It is kept once, in The Nation.
- **Date order:** each wall now runs in date order. The old timelines listed these events out of order:
  - Law Tested (June 13) now comes before Drivers Suspended (June 14) and Drivers Strike (June 15).
  - Ordinance 251 (June 24) now comes before Boycott Ends (June 26).
  - Undergraduates Sue to Integrate LSU now comes before First African American Dorm Resident.
  - Kerry Pourciau (1972) now comes before Alpha Kappa Alpha (1972–1973).

### Speaker labels and transcripts

- In a transcript with more than one speaker, each label is in bold. A speaker gets their full name the first time, then only their surname:
  - "A.P. Tureaud, Jr.:" → "**A. P. TUREAUD, JR.:**", then "**TUREAUD:**"
  - Freya Anderson Rivers’ clip: "RIVERS:" / "CRUMP:" → "**FREYA ANDERSON RIVERS:**" / "**MAXINE CRUMP:**" the first time
- In a transcript with only one speaker, the label is dropped, because the credit names them (for example "Dupuy Anderson: The night before the boycott…").
- A picture placed inside Maxine Crump’s transcript was taken out. It is the entry’s figure instead.
- **Initials:** "A.P.", "T.J.", "W.K." → "A. P.", "T. J.", "W. K."
- **Quotation marks:** doubled single quotes ('' '') and straight quotes became curly double quotes, and apostrophes became ’. A quotation left open at the end of a paragraph is closed there.
- **Dashes:** a spaced hyphen (" - " or " -- ") became an em dash.
- **Ellipses:** ". . .It" → ". . . It". In Tureaud’s transcript, "?'. . ." → "?” . . .".
- "[sic]" after "A. P. Tureaud, Jr." in Freya Anderson Rivers’ transcript is kept as it was.

### Credits

| Old | New |
| --- | --- |
| A. P. Tureaud Jr., LSU alumni | A. P. Tureaud, Jr., LSU alumnus |
| Ernest 'Dutch' Morial, LSU alumni | Ernest “Dutch” Morial, LSU alumnus, interviewed by Nina Pugh |
| Joanne Lamotte, LSU Alumna / Demetris 'Dee' Howard, LSU Alumna | …LSU alumna |
| Reverend T.J. Jemison / Reverend Lionel Lee | Rev. T. J. Jemison / Rev. Lionel Lee |
| Two names in one credit (e.g. "Reverend T.J. Jemison and Olivia Huey") | One credit per clip, each naming its own speaker |
| Pearl Payne / Freya Anderson Rivers / Johnnie Jones (King clip) / A. P. Tureaud, Jr. (two clips) | The interviewer added where the transcript names one: "interviewed by Jennifer Abraham", "by Maxine Crump", "by Beth Smith", "by Rachel Emanuel-Wallace" |
| "Esso photo courtesy of Ernest Richie" (in the clip credit) | Moved to the Esso photo’s caption |

## 2. To verify

- **Harambe Established at LSU:** the old timeline dated it 1964–1968, the same span it gave the dorm and the undergraduate suit, so it looked copied. It now has no date and stays off the Timeline. What year was Harambe founded?
- **Struggle Continues:** dated July 1953 on the bus timeline, but October 1953 on the separate 1953 timeline. July is kept.
- **SU Students Killed:** moved from November 17 to November 16, 1972, the day Denver Smith and Leonard Brown were shot. Please confirm.
- **Undergraduates Sue to Integrate LSU:** 1964 is kept, the year of the suit. The old range, 1964–1968, may have been meant for something else.
- **First African American Dorm Resident:** the old 1964–1968 range is kept. Should it be a single year?
- **Kerry Pourciau’s text:** it says Lora Hinton was the first African American football player recruited for the LSU Tigers. This is left as written.
- **Charles J. Hatfield, III:** Hatfield Sues LSU Law has no clip or picture. It is kept as written.
- **Newspaper names and dates in captions:** some were read off the clippings or taken from the event’s date, and need a check against the originals:
  - *The Louisiana Weekly* on the 1958 LSU Board clipping
  - *The Daily Reveille*, 1990, on the building article
  - "Newspaper photo by Bob Wall, March 30, 1960" on the march photo
  - The dates on the strike headline (June 15, 1953), the pool clipping (August 2, 1946), the tear gas front page (December 15, 1961) and the City Park pool clipping (July 24, 1963)
- **Tureaud photo, 1953:** the caption calls it a newspaper photo of A. P. Tureaud, Jr., and his father waiting in the LSU registrar’s office.
- **Burns newsletter:** the School of Library and Information Science newsletter is dated fall 2002 from its masthead.

## 3. New wording to check

### The page
- **Subtitle:** "Oral histories of civil rights at LSU and in Baton Rouge"
- **Credit:** "T. Harry Williams Center for Oral History and LSU Libraries Special Collections. Curated by Leah Wood Jewett, Barry Cowan and Jennifer A. Cramer."
- **Intro**, two paragraphs. The first quotes the old About page. The second is new: "Oral history clips from those who integrated LSU sit beside timelines of the 1953 Baton Rouge bus boycott and of civil rights activities in East Baton Rouge from the 1920s through the 1970s, in the voices of those who lived them."
- **Exhibition note:** "Shown in the Hill Memorial Library Lecture Hall from January 21 to March 29, 2014, for the 50th anniversary of the Civil Rights Act of 1964."
- **Timeline gallery intro:** "The exhibition’s four galleries side by side, from *Plessy v. Ferguson* in 1896 to the building LSU named for A. P. Tureaud, Sr., in 1990: the suits and students who integrated LSU, the 1953 bus boycott, civil rights in East Baton Rouge, and the nation."
- **Integrating LSU intro:** "LSU was integrated one school at a time, through separate suits for the law school, the graduate school and the undergraduate college. Many of the cases were argued by A. P. Tureaud, Sr."
- **Period headings on the walls:**
  - Integrating LSU: The lawsuits, 1946–1953; Segregation reaffirmed, 1958; Undergraduates on campus, 1964–1972; Recognition, 1990
  - The 1953 Bus Boycott: February: Ordinance 222; June: The strike and the boycott; The compromise and after
  - East Baton Rouge: Before 1960; Sit-ins and marches, 1960–1962; Pools, stores and backlash, 1963–1964; Grief and violence, 1968–1972
- **About the Exhibition:** reworded from the old About page, with the headings "Old Ways No More" and "Acknowledgments". It also has a new paragraph inviting donations of materials from the era.

### Event text written new (the old timeline had none, or had only a transcript)
- **First African American Dorm Resident:** "Maxine Crump becomes the first African American to live in an LSU dormitory." *(The old text was the clip’s transcript, which is now under the clip.)*
- **Harambe Established at LSU:** "Black students at LSU establish Harambe, a support group with a room of its own on campus."
- **Alpha Kappa Alpha Established at LSU:** "With help from the Gamma Eta Omega chapter of Alpha Kappa Alpha Sorority, Black women at LSU establish a chapter of their own."
- **LSU Names a Building for A. P. Tureaud, Sr.:** "The former Classroom Building, at South Stadium Drive and Forestry Lane, is named for civil rights lawyer A. P. Tureaud, Sr.: the first building on LSU’s campus named for a Black person." *(Taken from the clipping; please check the building’s old name, its location and the "first building" claim.)*

### Clip titles (all new; the old station had none)
- **Integrating LSU:**
  - Leander Perez at the deposition
  - More than just their lawyer
  - The first Black law students
  - The first credit from the graduate school
  - It was like I didn’t exist
  - We just kept saying equal
  - Who was comfortable being where with me
  - A bonding group
  - A new era
  - Founding the chapter
- **The 1953 Bus Boycott:**
  - Standing over empty seats
  - It was humiliating
  - First come, first served
  - The lady on the bus
  - The night before the boycott
  - Driving the bus route
  - They rode free in the day
  - A show of unity
  - Separate but equal was wrong
  - My daughter sat in the front
  - The church house and the court house
- **East Baton Rouge:**
  - The hub of education
  - Ryan Army Air Field
  - Home from the war
  - Tax time
  - A black golf course
  - At the lunch counters
  - We were willing
  - The swimming pools were not integrated
  - They shot in our house
  - The summer of 1969
  - The deputies lined up

### Credits written new (no credit in the old station)
- Maxine Crump, interviewed by Pamela Dean
- Kerry Pourciau, interviewed by Maxine Crump

### Captions (all new except the Plessy marker’s, which is shortened from the old photo credit)
- **Plessy marker:** "The *Plessy v. Ferguson* marker in New Orleans, written by historian Keith Weldon Medley for the Crescent City Peace Alliance and placed at Press Street and the railroad yards on February 12, 2009." *(Also the lead caption for The Nation.)*
- **Tureaud:** "A. P. Tureaud, Jr., and his father, his attorney in the suit, waiting in the LSU registrar’s office. Newspaper photo, 1953." *(Also the lead caption for Integrating LSU.)*
- **Esso station:** "An Esso station in Baton Rouge, 1953. Photo courtesy of Ernest Richie." *(Also the lead caption for The 1953 Bus Boycott.)*
- **March, second photo:** "Marchers on their way to the State Capitol, April 5, 1968." *(Also the lead caption for East Baton Rouge.)*
- **March, first photo:** "The march to the State Capitol, April 5, 1968."
- **Portraits:**
  - Reverend T. J. Jemison.
  - Willis Reed.
  - Martin Luther King, Jr.
  - Maxine Crump.
  - Kerry Pourciau.
  - Ernest “Dutch” Morial, later mayor of New Orleans.
- **Groups and places:**
  - Members of Alpha Kappa Alpha at LSU.
  - Members of Harambe at LSU.
  - McKinley High School.
- **Telegram:** "Telegram from L. A. Payne to Pearl Payne, June 14, 1951: “Registration completed all is well.”"
- **Foister:** "Newspaper report of Daryle E. Foister’s admission."
- **Burns:** "Ollie H. Burns, profiled in the School of Library and Information Science newsletter, fall 2002."
- **LSU Board:** "*The Louisiana Weekly* reports the LSU Board of Supervisors’ vote, 1958."
- **Building:** "*The Daily Reveille* reports the building’s dedication, 1990."
- **Newspaper front pages and clippings:** each is captioned with its paper and date (see To verify):
  - March 30, 1960
  - Bob Wall’s photo, March 30, 1960
  - *Morning Advocate*, April 5, 1968
  - June 15, 1953
  - August 2, 1946
  - December 15, 1961
  - July 24, 1963
  - *Morning Advocate*, January 11, 1972
  - *Morning Advocate*, November 17, 1972

### Alt text (all new; 28 images)
Each describes what the picture shows and, for a clipping, gives its headline word for word (for example "A Morning Advocate front page headlined “King Shot to Death in Memphis,” with a portrait of Martin Luther King, Jr."). The full list is under `alt` in `scripts/data/integrating-lsu.json`. Please check the headlines read off the clippings:
- Nurse Admitted to LSU School of Nursing Here
- LSU Board Says ‘No’ to Negro Applicants; Vote to Deny Admission to 12
- Building renamed after black lawyer, by Molly Thibodeaux
- City Planners to Determine Site for Negro Pool
- Tear Gas Is Used As Negro Groups March Downtown
- Negro Group Tries to Mix Pool in Park, by Jim Crain
- Violence Leaves 4 Dead Here
- Clash Leaves 2 Dead; SU Campus Shut Down
- Negroes March Downtown; Grand Jury Begins Inquiry
- Bus Drivers Strike Over Race Issue
