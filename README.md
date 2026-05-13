# Agile Tracker

Agile Tracker on PHP ja SQLite veebirakendus kasutajalugude haldamiseks Kanban-laual. Rakenduses on kolm veergu: `Todo / Backlog`, `Doing` ja `Done`.

## Tehnoloogiad

- PHP 8.4
- SQLite
- Vanilla JavaScript
- HTML ja CSS
- PHP sisseehitatud arendusserver

## Käivitamine

```bash
php -S localhost:8000 -t public public/index.php
```

Seejärel ava brauseris:

```text
http://localhost:8000
```

Andmebaas luuakse automaatselt faili `data/agile_tracker.sqlite`. Kui andmebaas on tühi, lisatakse neli näidisstoryt.

## Valmis funktsionaalsused

- Story’de kuvamine kolmes Kanban-veerus.
- Story lisamine, muutmine ja kustutamine.
- Story detailvaade.
- Staatused `todo`, `doing` ja `done`.
- Punktide sisestamine ja valideerimine.
- Vähemalt ühe vastuvõtutingimuse nõue.
- Kommentaaride lisamine koos lisamise ajaga.
- Kommentaaride kustutamine.
- Backlogi hiirega ümber järjestamine.
- Story lohistamine kõigi veergude vahel.
- Backlogi järjestuse ja staatuste säilimine pärast lehe uuendamist.
- Otsing pealkirja ja kirjelduse järgi.
- Filtreerimine staatuse ja punktide järgi.
- Punktide summa iga veeru juures.
- REST API story’de haldamiseks.
- Lihtsad REST API automaattestid.
- Sobivad HTTP staatuskoodid API vigade korral.

## Pooleli jäänud funktsionaalsused

- Kasutajakontod ja õigused ei kuulu ülesande skoopi.
- Eraldi production deploy seadistus puudub.

## Kõige keerulisemad kohad

- Backlogi järjekorra salvestamine nii, et refresh ei muudaks kaartide järjekorda.
- Story lohistamine veergude vahel nii, et sama tegevus muudaks ka staatust.
- Punktide ja vastuvõtutingimuste valideerimine nii frontend’is kui REST API-s.

## Valideerimise reeglid

- Pealkiri on kohustuslik.
- Punktid on kohustuslikud, peavad olema täisarvud ja ei tohi olla negatiivsed.
- Staatus peab olema `todo`, `doing` või `done`.
- Igal story’l peab olema vähemalt üks vastuvõtutingimus.
- Kommentaari tekst ei tohi olla tühi.

## Drag-and-drop käitumine

- Kaarti saab lohistada `todo`, `doing` ja `done` veergude vahel.
- `Todo / Backlog` veeru järjekord salvestatakse `priority` väärtusena.
- Pärast lohistamist saadab frontend kogu laua uue järjestuse endpoint’i `PATCH /api/stories/reorder`.
- Lehe uuendamisel loetakse järjekord SQLite andmebaasist tagasi.

## REST API endpoint’id

| Meetod | Endpoint | Kirjeldus |
| --- | --- | --- |
| GET | `/api/stories` | Tagastab kõik story’d. |
| GET | `/api/stories/:id` | Tagastab ühe story. |
| POST | `/api/stories` | Loob uue story. |
| PUT | `/api/stories/:id` | Muudab olemasolevat storyt. |
| DELETE | `/api/stories/:id` | Kustutab story. |
| PATCH | `/api/stories/:id/status` | Muudab story staatust. |
| PATCH | `/api/stories/reorder` | Salvestab uue järjestuse ja vajadusel staatuse. |
| POST | `/api/stories/:id/comments` | Lisab story juurde kommentaari. |
| DELETE | `/api/stories/:id/comments/:commentId` | Kustutab kommentaari. |

## API näidispäring

```bash
curl -X POST http://localhost:8000/api/stories \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Lisa story loomine",
    "description": "Kasutaja saab luua uue story.",
    "status": "todo",
    "points": 5,
    "acceptanceCriteria": [
      "Kasutaja saab sisestada pealkirja.",
      "Story ilmub backlogi."
    ]
  }'
```

## Testimine

```bash
php tests/api_tests.php
```

Testid käivitavad ajutise PHP serveri ja kasutavad ajutist SQLite andmebaasi.

## Ekraanipilt

Lisa töötava Kanban-laua ekraanipilt kausta `screenshots/` ja viita sellele siin enne töö esitamist.
