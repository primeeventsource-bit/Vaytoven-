# `app/` — Web app prototype

React-based prototype of the Vaytoven web app. Demonstrates the full guest + host flow end-to-end with mock data.

## What's in here

```
app/
├── index.html      Source — JSX in a <script type="text/babel"> block
├── build.js        Compiles JSX and inlines React; produces prebuilt.html
├── prebuilt.html   Self-contained build artifact (no external deps)
└── package.json    Build dependencies
```

## Run the prebuilt artifact

```bash
open prebuilt.html
```

That's it. No server, no install, no internet.

## Build from source

```bash
npm install
npm run build
```

This compiles `index.html` (which uses Babel's standalone in-browser compiler at dev time) into `prebuilt.html` (which inlines React UMD bundles + pre-compiled JS). Use this when you've edited `index.html` and want to ship a new artifact.

## Architecture

Single-file React app. State is held in a single `AppProvider` context. No router library — there's a homemade `route` state with five values (`search`, `detail`, `trips`, `inbox`, `host`). All data is mocked in-memory.

### Pages

1. **Search** — listing grid + filter sidebar + map panel. Members banner at top.
2. **Detail** — property page with booking modal and 3-step booking flow.
3. **Trips** — confirmed/upcoming/past trips.
4. **Inbox** — message threads (mock).
5. **Host** — host dashboard with Overview/Listings/Calendar/Earnings/Reviews tabs.

### Members Modal

Both the Search page (traveler context) and the Host page show a contextual banner for the Managed Listing Program. Clicking it opens a modal at the App root that captures the same fields as the marketing site's modal. On submit, currently logs to console — wire to `POST /api/v1/members-enquiries` for real use.

## Wiring to the real backend

This is a prototype with mock data. To convert to a real-data app, replace the constants in the `// MOCK DATA` block (search for `INITIAL_TRIPS`, `INITIAL_THREADS`, `PROPERTIES`) with `fetch()` calls to the Laravel API:

```js
// Instead of:
const [trips, setTrips] = useState(INITIAL_TRIPS);

// Use:
const [trips, setTrips] = useState([]);
useEffect(() => {
  fetch('/api/v1/bookings', { credentials: 'include' })
    .then(r => r.json())
    .then(data => setTrips(data.data));
}, []);
```

The API contract is documented in `backend/README.md`.
