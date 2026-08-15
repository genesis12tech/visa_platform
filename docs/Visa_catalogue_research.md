# Visa Catalogue Research — Working Draft

| Field | Value |
|---|---|
| **Purpose** | Raw material for Stage 2 `visa_types` / `visa_fees` / `visa_type_document_requirements` / `form_templates` configuration (Backend_schema.md §4.3–§4.4) |
| **Status** | **Draft, not policy.** Gathered by web research against public Indian government and Indian Mission sources, 2026-08-15. Fee figures in particular drift and vary by nationality/mission — confirm against the live official source before entering any amount into `visa_fees`. |
| **Scope** | The 9 visa types named in `DataCredential.txt`: Tourist, Student, Business, Employment, Entry, Journalist, Conference, Medical, Research |

This is intake material, not a finished spec. Where a figure is uncertain, it's marked ⚠ rather than presented as fact — per this project's own standard (Backend Schema, PRD) of never letting an assumption pass as a confirmed value.

---

## 1. Two application routes

India runs two parallel tracks, and which one a visa type uses changes what "apply" looks like in the product:

| Route | Visa types on this route | How it works |
|---|---|---|
| **e-Visa** (`indianvisaonline.gov.in/evisa`) | e-Tourist, e-Business, e-Medical, e-Medical Attendant, e-Conference (and some missions now also process e-Student) | Fully online: form + document upload + online payment, no physical mission visit for most nationalities. Government-stated processing target ~72 hours standard, with paid expedited tiers (24h "urgent", and 4h "super-urgent" on some categories). |
| **Regular / mission visa** | Employment, Student (where e-Student isn't offered), Entry (X), Journalist, Research, and Conference where MHA event-clearance applies | Online form submission (`indianvisaonline.gov.in/visa`) generates an Application ID, but the applicant must then submit physical documents at a Mission or an outsourced center (VFS Global / BLS International, depending on country), often with an in-person appointment. Processing commonly quoted as 1–3 weeks, sometimes longer where a central-government clearance (MEA/MHA/MHRD) is required. |

**Product implication:** the PRD's "Core lifecycle" (upload → submit → pay → review → decide, all in-portal) matches the **e-Visa route** cleanly. The regular/mission route's requirement for physical document submission and in-person appointments is a real deviation from the PRD's fully-digital vertical slice for the categories that use it (Employment, Student, Entry, Journalist, Research) — worth flagging as a scope question: does this system replicate the mission's own in-person step, or treat those categories as "digital intake, physical follow-up elsewhere"? Not something to guess at; flag for a product decision before building the Employment/Student/Entry/Journalist/Research form flows.

---

## 2. Common application form structure

Across both routes, Indian visa application forms consistently collect the same base sections — this is a reasonable starting point for the shared, cross-visa-type portion of the `form_templates` schema, before category-specific sections are layered on:

1. **Personal particulars** — surname, given name(s), date of birth, place of birth, nationality (present and previous, if changed), sex
2. **Passport details** — number, type, date/place of issue, date of expiry
3. **Family details** — parents' names/nationalities, spouse details
4. **Visa sought** — category, number of entries, duration, ports of entry/exit
5. **Previous visits to India** — yes/no, and if yes: dates, places visited, address(es) stayed at, Indian visa/reference number if known
6. **Profession/occupation** — current employer, designation, past employment where relevant
7. **Contact details** — current residential address, phone, email
8. **Reference details** — a contact/reference in India (varies by category — sometimes an inviting organization, sometimes a personal reference) and a reference in the applicant's home country

Sources: VFS Global sample application form structure; India Particulars/Additional Particulars forms used across missions.

---

## 3. Per-visa-type detail

### 3.1 Tourist Visa

| | |
|---|---|
| Route | e-Visa (most nationalities) or regular, depending on nationality |
| Fee (e-Visa) | ⚠ Tiered by season and validity: 30-day e-Tourist ~US$10 (Apr–Jun off-peak) / ~US$25 (Jul–Mar); 1-year e-Tourist ~US$40 for many nationalities; 5-year e-Tourist ranges roughly US$25–US$484 depending on the applicant's nationality — India's fee schedule is nationality-tiered, not flat. **Confirm the current tiered table against the official fee PDF before configuring `visa_fees`; do not use a single flat figure.** |
| Processing | ~72 hours standard; expedited paid tiers exist (24h, and 4h on some categories) |
| Validity/entries | 30-day: single window, multiple entries within it. 1-year and 5-year: multiple entries, but capped at 180 days' total stay per calendar year |
| Documents | Passport bio page scan, digital photo (JPEG, white background, spec below). No extra document for most applicants; short-course or volunteer travel needs an institutional/organizational letter |

### 3.2 Business Visa

| | |
|---|---|
| Route | e-Business (most nationalities) or regular |
| Fee | ⚠ ~US$80 (e-Business), nationality-tiered — confirm exact table |
| Processing | ~72 hours standard (e-Visa track) |
| Validity/entries | 365 days from grant, multiple entries |
| Documents | Passport scan, photo, **business card or invitation letter from the Indian company** being visited |

### 3.3 Medical Visa

| | |
|---|---|
| Route | e-Medical (+ e-Medical Attendant for up to two accompanying persons per patient) |
| Fee | ⚠ ~US$80, nationality-tiered — confirm exact table |
| Processing | ~72 hours standard |
| Validity/entries | 60 days from first arrival (both Medical and Medical Attendant), multiple entries. Max 2 Medical Attendant visas per 1 Medical visa. |
| Documents | Passport scan, photo, **letter from the treating hospital in India on official letterhead** confirming admission/treatment dates |

### 3.4 Conference Visa

| | |
|---|---|
| Route | e-Conference for straightforward cases; regular route (with MHA event clearance) when delegates include nationals of specific countries or the event touches Protected/Restricted areas |
| Fee | ⚠ Not confirmed from an official table this session — third-party sources suggest ~US$350 region for the regular-route conference visa; verify against the official schedule, don't use this figure as-is |
| Processing | MEA political clearance is the long pole — MEA/MHA clearance "may be granted within four weeks" per MHA's own FAQ; visas cannot issue before political clearance lands |
| Validity | Not to exceed 6 months, under Tourist-visa-equivalent conditions; single entry is commonly cited but conflicts with one official source stating "multiple" for e-Conference — **flag as unconfirmed, don't hardcode** |
| Documents | Photo, passport bio page, **formal invitation from the Indian organizer** (event nature, dates, venue, applicant's role), evidence that the organizer has lodged **political clearance with MEA** |

### 3.5 Employment Visa

| | |
|---|---|
| Route | Regular only |
| Fee | ⚠ Not confirmed from an official table |
| Processing | Not confirmed from an official source — third-party estimates only |
| Validity | Typically tied to the employment contract term |
| Eligibility | "Highly skilled and/or qualified professional" engaged by an Indian company/organization; **minimum gross salary ₹16.25 lakh/year** (with named exemptions: ethnic cooks, language teachers, sports coaches, performers; PIOs and dependents of Indian citizens have a lower ₹3.60 lakh/year threshold) |
| Documents | Formal request/sponsorship letter from the Indian employer, copy of the employment contract, the Indian company's registration details, an undertaking from the employer, proof of the applicant's qualifications/expertise |

### 3.6 Student Visa

| | |
|---|---|
| Route | e-Student at missions that support it, else regular |
| Fee | ⚠ Not confirmed from an official table; third-party estimate ~US$240 for the regular multi-entry visa — verify |
| Processing | No official fixed SLA found; commonly quoted as up to ~2 weeks once complete, longer in peak intake periods |
| Validity | Up to 6 months for an "admission-seeking" visit (exploring options / entrance tests); full-duration student visa otherwise tied to the course |
| Documents | **Original admission letter** from a UGC/AICTE-recognized Indian institution (must state program, start date, duration); for medical/paramedical or graduate/postgraduate admission specifically, an additional **No Objection / approval letter from the relevant ministry**; financial evidence (bank statements, loan sanction letter, scholarship letter, or sponsor affidavit + sponsor's income documents); certified academic transcripts (English or certified translation) |

### 3.7 Entry Visa (X)

| | |
|---|---|
| Route | Regular only |
| Fee | ⚠ Not confirmed from an official table; third-party estimate ~US$300–390 range depending on sub-category/validity |
| Processing | ~1–3 weeks per third-party sources; not confirmed officially |
| Sub-categories (real distinctions, not one flat "Entry Visa") | **X-1**: Persons of Indian Origin (PIO lineage) without an OCI card — up to 5 years, multiple entry. **X-2**: foreign spouse/children of an Indian citizen, PIO, or OCI holder (not themselves OCI-registered) — up to 5 years, multiple entry. **X-4**: foreign nationals who own property in India. **X-5 / X-5D**: diplomats/officials on personal visits, and their dependents. **X-SP**: international sports event participants and accompanying officials. |
| Documents | Proof of Indian origin (own or parent's/grandparent's Indian passport or birth certificate) or the qualifying relationship (marriage certificate, birth certificate), old passport if applicable, proof of current and former address |

**Note for `visa_types` modeling:** "Entry Visa" is not really one product — it's a family of sub-categories with materially different eligibility and evidence. Worth a product decision on whether these become distinct `visa_types` rows or one type with a conditional document/eligibility branch.

### 3.8 Journalist Visa

| | |
|---|---|
| Route | Regular only |
| Fee | ⚠ Third-party estimate: single-entry ~US$155, multiple-entry ~US$140–390 depending on validity — not confirmed officially |
| Processing | Not confirmed officially |
| Validity | Up to 3 months' stay for professional journalists/photographers on assignment |
| Documents | Copy of **media accreditation card**, and/or a letter from the applicant's organization describing the nature of the work in India. Intern journalists and those on Employment-track journalism roles apply under the **Employment visa** instead — the two categories aren't interchangeable and the distinction (assignment/coverage trip vs. working for an Indian media organization) needs to be reflected wherever the form routes applicants to one or the other. |

### 3.9 Research Visa

| | |
|---|---|
| Route | Regular only |
| Fee | ⚠ Not confirmed officially |
| Processing | Not confirmed officially; involves referral to MHA/MEA/the relevant ministry if the host institution isn't UGC/AIU-recognized or documentation is in doubt |
| Eligibility | Scholars/teachers/professors invited by a **Central Educational Institution or a publicly funded State University** — invitations from non-recognized institutions get escalated for extra scrutiny |
| Validity | Tied to the research project's duration, as approved |
| Documents | Research synopsis/subject/project details, list of places to be visited in India, letter of recommendation/support from the applicant's home institution, original invitation letter from the Indian host institution on letterhead, evidence of funding source |

---

## 4. Photo and passport specifications (common to all categories)

| Requirement | Value |
|---|---|
| Passport validity | At least 6 months remaining at time of application |
| Passport blank pages | At least 2 |
| Photo format | JPEG, plain white/light background, no shadows, no borders, full frontal face, eyes open, no spectacles |
| Photo size (physical, for paper submissions) | 2×2 inch (51×51mm), square |
| Photo size (digital upload) | Roughly 350×350 to 1000×1000 px, 1:1 ratio; file size limits vary by source consulted (10KB–1MB range cited across different official pages — **confirm current limit per portal before hardcoding into `document_types.max_size_bytes`**) |

---

## 5. What still needs a real decision, not a web search

- **Whether this system builds the regular/mission-route categories (Employment, Student where no e-Student, Entry, Journalist, Research, some Conference cases) as fully digital, or as digital-intake-plus-physical-followup.** This is a scope question for the product owner, not something resolvable by more research.
- **Exact current fee amounts for every category.** Several are marked ⚠ above because I could not get a machine-readable version of the official fee PDF in this session (it's a compressed/binary PDF and the page-rendering tool this session needed — `poppler`/`pdftoppm` — isn't installed; a retry with that tool available, or a manual check against `indianvisaonline.gov.in/visa/visa-fee.html` and the linked e-Visa fee PDF, would settle it). Fee amounts are admin-configurable per `visa_fees` (Backend_schema.md §4.3) regardless, so this doesn't block schema/code work — it blocks entering real numbers.
- **Whether "Entry Visa" is one `visa_types` row or several** (X-1/X-2/X-4/X-5/X-5D/X-SP have materially different eligibility) — flagged above.
- **The Journalist vs. Employment(-journalism) boundary** — needs an explicit rule in the visa-type-selection flow, not left to the applicant to guess.

## Sources

- [High Commission of India, Ottawa — e-Visa](https://hciottawa.gov.in/pages?id=12&subid=70)
- [India e-Visa Requirements, Fees, and Processing Time — LegalClarity](https://legalclarity.org/india-e-visa-requirements-fees-and-processing-time/)
- [Indian e-Visa official portal — e-Visa category page](https://indianvisaonline.gov.in/evisa/tvoa.html)
- [Indian e-Visa official portal — Registration/requirements](https://indianvisaonline.gov.in/evisa/Registration)
- [Indian e-Visa official fee PDF (country/territory-wise)](https://indianvisaonline.gov.in/evisa/images/eTV_revised_fee_final.pdf)
- [Regular visa instructions — indianvisaonline.gov.in](https://indianvisaonline.gov.in/visa/instruction.html)
- [Regular visa fee page — indianvisaonline.gov.in](https://indianvisaonline.gov.in/visa/visa-fee.html)
- [MHA — Details of Visas Granted by India (official classification PDF)](https://www.mha.gov.in/PDF_Other/AnnexIII_01022018.pdf) *(fetch blocked — 403; worth a direct manual check)*
- [High Commission of India, London — Journalist Visa](https://www.hcilondon.gov.in/page/journalist-visa/)
- [High Commission of India, London — Research Visa](https://www.hcilondon.gov.in/page/research-visa/)
- [Consulate General of India, San Francisco — Employment Visa](https://www.cgisf.gov.in/page/employment-visa/)
- [Consulate General of India, San Francisco — Journalist Visas/Documentary Filming](https://www.cgisf.gov.in/page/journalist-visas-documentary-filming-in-india/)
- [MHA — Research Visa overview (2014, still cited as current by missions)](https://www.mha.gov.in/PDF_Other/OverviewReserchVisa2014.pdf)
- [MHA — Conference Visa FAQs](https://www.mha.gov.in/sites/default/files/2022-07/ForeigD-FAQs-on-ConferenceVisa%20(1).pdf)
- [Embassy of India, Paris — Conference Visa document checklist](https://www.eoiparis.gov.in/content/CONFERENCE%20VISA.pdf)
- [Embassy of India, Paris — Student Visa document checklist](https://www.eoiparis.gov.in/content/STUDENT%20VISA.pdf)
- [VFS Global — sample Indian visa application form](https://www.vfsglobal.com/india/uk/pdf/sample_copy_application_form_150116.pdf)
- [VFS Global UK — Document Checklist, general](https://www.vfsglobal.com/india/uk/pdf/DocumentCheckList_300315.pdf)
- [High Commission of India, Dhaka — Documents Required for Visa](https://hcidhaka.gov.in/pdf/Documents_Required_for_Visa%20_for_website.pdf)
