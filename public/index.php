<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ValidationException.php';
require_once __DIR__ . '/../src/StoryRepository.php';
require_once __DIR__ . '/../src/ApiController.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

$repository = new StoryRepository((new Database())->pdo());
$repository->seedIfEmpty();

if (str_starts_with($path, '/api/')) {
    (new ApiController($repository))->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
    exit;
}

?><!doctype html>
<html lang="et">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agile Tracker</title>
    <link rel="stylesheet" href="/assets/styles.css?v=12">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar" aria-label="Peamenüü">
            <div class="brand">
                <div class="brand-mark">A</div>
                <div>
                    <h1>Agile Tracker</h1>
                    <p>user stories · kanban · punktid</p>
                </div>
            </div>

            <div class="nav-label">Töölaud</div>
            <button class="nav-item active" data-nav-action="kanban" type="button"><span>Kanban</span><span class="nav-pill" id="navStoryCount">0</span></button>
            <button class="nav-item" data-nav-action="backlog" type="button"><span>Backlog</span><span class="nav-pill" id="navBacklogCount">0</span></button>
            <button class="nav-item" data-nav-action="comments" type="button"><span>Kommentaarid</span><span class="nav-pill" id="navCommentCount">0</span></button>

            <div class="nav-label">Projekt</div>
            <button class="nav-item" data-nav-action="criteria" type="button"><span>Vastuvõtutingimused</span></button>
            <button class="nav-item" data-nav-action="api" type="button"><span>REST API</span></button>
            <button class="nav-item" data-nav-action="sqlite" type="button"><span>SQLite</span></button>

            <div class="sidebar-card">
                <strong>PHP + SQLite</strong>
                <p>REST API salvestab story’d, kommentaarid ja backlogi järjekorra SQLite andmebaasi.</p>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div class="page-title">
                    <h2>Kanban-laud</h2>
                    <p>Kasutajalood, punktid, vastuvõtutingimused ja kommentaarid ühes töövaates.</p>
                </div>
                <div class="top-actions">
                    <button class="theme-button" id="themeToggle" type="button">Tume teema</button>
                    <button class="primary-button" id="newStoryButton" type="button">+ Uus story</button>
                </div>
            </header>

            <section class="toolbar" aria-label="Otsing ja filtrid">
                <label>
                    Otsing
                    <input id="searchInput" type="search" placeholder="Pealkiri või kirjeldus">
                </label>
                <label>
                    Staatus
                    <select id="statusFilter">
                        <option value="">Kõik</option>
                        <option value="todo">Todo / Backlog</option>
                        <option value="doing">Doing</option>
                        <option value="done">Done</option>
                    </select>
                </label>
                <label>
                    Punktid
                    <select id="pointsFilter">
                        <option value="">Kõik</option>
                        <option value="0-3">0-3</option>
                        <option value="4-8">4-8</option>
                        <option value="9+">9+</option>
                    </select>
                </label>
            </section>

            <section class="stats-grid" aria-label="Ülevaade">
                <article class="stat-card"><span>Storysid</span><strong id="statStories">0</strong></article>
                <article class="stat-card"><span>Punkte kokku</span><strong id="statPoints">0</strong></article>
                <article class="stat-card"><span>Tingimusi</span><strong id="statCriteria">0</strong></article>
                <article class="stat-card"><span>Kommentaare</span><strong id="statComments">0</strong></article>
            </section>

            <p class="message" id="message" role="status"></p>

            <section class="inline-view" id="inlineView" hidden>
                <div class="inline-view-header">
                    <div>
                        <h2 id="inlineViewTitle">Vaade</h2>
                        <p id="inlineViewDescription"></p>
                    </div>
                    <button class="icon-button" id="closeInlineViewButton" type="button">×</button>
                </div>
                <div class="inline-view-body" id="inlineViewBody"></div>
            </section>

            <section class="board" aria-label="Kanban-laud">
                <article class="column" data-status="todo">
                    <header>
                        <div class="column-title"><span class="dot todo"></span><h2>Todo / Backlog</h2></div>
                        <span id="todoPoints">0 p</span>
                    </header>
                    <div class="story-list" id="todoList" data-status="todo"></div>
                </article>
                <article class="column" data-status="doing">
                    <header>
                        <div class="column-title"><span class="dot doing"></span><h2>Doing</h2></div>
                        <span id="doingPoints">0 p</span>
                    </header>
                    <div class="story-list" id="doingList" data-status="doing"></div>
                </article>
                <article class="column" data-status="done">
                    <header>
                        <div class="column-title"><span class="dot done"></span><h2>Done</h2></div>
                        <span id="donePoints">0 p</span>
                    </header>
                    <div class="story-list" id="doneList" data-status="done"></div>
                </article>
            </section>
        </main>
    </div>

    <dialog id="storyDialog">
        <form id="storyForm" method="dialog">
            <div class="dialog-header">
                <h2 id="dialogTitle">Uus story</h2>
                <button class="icon-button" type="button" data-close-dialog>×</button>
            </div>
            <input id="storyId" type="hidden">
            <label>Pealkiri<input id="titleInput" required></label>
            <label>Kirjeldus<textarea id="descriptionInput" rows="4"></textarea></label>
            <div class="form-grid">
                <label>Punktid<input id="pointsInput" required min="0" step="1" type="number"></label>
                <label>Staatus
                    <select id="statusInput">
                        <option value="todo">Todo / Backlog</option>
                        <option value="doing">Doing</option>
                        <option value="done">Done</option>
                    </select>
                </label>
            </div>
            <label>Vastuvõtutingimused<textarea id="criteriaInput" rows="5" placeholder="Üks tingimus rea kohta" required></textarea></label>
            <menu>
                <button type="button" data-close-dialog>Tühista</button>
                <button class="primary-button" type="submit">Salvesta</button>
            </menu>
        </form>
    </dialog>

    <dialog id="infoDialog">
        <div class="info-dialog-content">
            <div class="dialog-header">
                <h2 id="infoDialogTitle">Info</h2>
                <button class="icon-button" type="button" id="closeInfoDialogButton">×</button>
            </div>
            <div class="info-body" id="infoDialogBody"></div>
        </div>
    </dialog>

    <dialog class="detail-panel" id="detailPanel" aria-live="polite">
        <div class="detail-dialog-content">
            <div class="detail-header">
                <h2 id="detailTitle"></h2>
                <button class="icon-button" id="closeDetailButton" type="button">×</button>
            </div>
            <p id="detailMeta"></p>
            <p id="detailDescription"></p>
            <h3>Vastuvõtutingimused</h3>
            <ul id="detailCriteria"></ul>
            <h3>Kommentaarid</h3>
            <div id="detailComments"></div>
            <form id="commentForm">
                <label>Uus kommentaar<textarea id="commentInput" rows="3"></textarea></label>
                <button class="primary-button" type="submit">Lisa kommentaar</button>
            </form>
            <div class="detail-actions">
                <button id="editStoryButton" type="button">Muuda</button>
                <button class="danger-button" id="deleteStoryButton" type="button">Kustuta</button>
            </div>
        </div>
    </dialog>

    <script src="/assets/app.js?v=12"></script>
</body>
</html>
