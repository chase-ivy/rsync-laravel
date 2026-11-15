# Boarding-house verification documents (RSYNC)

> **Summary only, not legal advice.** This list was put together for the RSYNC capstone project
> from general knowledge of how boarding houses are permitted in the Philippines. Requirements,
> names of offices and fees change. **Confirm the current list with Baguio City Hall — the
> Business Permits and Licensing Office (BPLO) — before relying on it.**

Landlords and sublessors upload these on their **Account** page ("Verification documents").
The files are private: only the owner and RSYNC admins can open them. An admin reviews them in
**Admin Dashboard → Verify Users** and approves or rejects the account.

## Documents RSYNC asks for

| Document | Required in RSYNC | Who issues it | What it shows |
|---|---|---|---|
| Business permit (Mayor's Permit) | Yes | Baguio City Hall – Business Permits and Licensing Office (BPLO) | The city allows the boarding house to operate as a business for the year. Renewed every year (usually in January). |
| Fire Safety Inspection Certificate (FSIC) | Yes | Bureau of Fire Protection (BFP), Baguio City Fire Station | The building passed a fire safety inspection (exits, extinguishers, alarms, wiring). Usually needed before the Mayor's Permit is released or renewed. |
| Sanitary Permit | Yes | City Health Services Office (City Health Office) | The place meets sanitation rules (water, toilets, garbage, pest control). Staff handling food may also need health certificates. |
| Barangay Business Clearance | Optional in RSYNC | The barangay hall where the boarding house is located | The barangay has no objection to the business. In practice it is normally needed **before** applying for the Mayor's Permit, so most permitted owners already have it. |

## Other documents an owner may have (not uploaded in RSYNC)

- **DTI Business Name Registration** (sole proprietors) or **SEC registration** (corporations/partnerships)
  — registers the business name. Issued by the Department of Trade and Industry or the Securities and Exchange Commission.
- **BIR Certificate of Registration (Form 2303)** — the business is registered for taxes.
- **Building Permit / Certificate of Occupancy** — issued by the City Building Official
  (City Buildings and Architecture Office); shows the building was built and may be used as a dwelling.
- **Locational / Zoning Clearance** — from the City Planning and Development Office; the lot may be used for this business.

## How RSYNC uses the documents

- The account status shown to the landlord (`User::permitStatus()`) is still based on the business
  permit and the admin's decision: *Not uploaded → Waiting for review → Approved / Not accepted*.
- A landlord appears in **Verify Users** once the business permit is uploaded (same as before).
  The admin sees a link for every uploaded document and a note listing required documents that are missing.
- Uploading any new document sends the account back for review, because the admin has not seen it yet.
- The list of documents lives in one place in the code: `App\Models\User::OWNER_DOCUMENTS`.
