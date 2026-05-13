const statuses = ['todo', 'doing', 'done'];
const state = {
    stories: [],
    selectedId: null,
    draggedId: null,
};

const elements = {
    message: document.querySelector('#message'),
    search: document.querySelector('#searchInput'),
    statusFilter: document.querySelector('#statusFilter'),
    pointsFilter: document.querySelector('#pointsFilter'),
    storyDialog: document.querySelector('#storyDialog'),
    storyForm: document.querySelector('#storyForm'),
    dialogTitle: document.querySelector('#dialogTitle'),
    storyId: document.querySelector('#storyId'),
    title: document.querySelector('#titleInput'),
    description: document.querySelector('#descriptionInput'),
    points: document.querySelector('#pointsInput'),
    status: document.querySelector('#statusInput'),
    criteria: document.querySelector('#criteriaInput'),
    detailPanel: document.querySelector('#detailPanel'),
    detailTitle: document.querySelector('#detailTitle'),
    detailMeta: document.querySelector('#detailMeta'),
    detailDescription: document.querySelector('#detailDescription'),
    detailCriteria: document.querySelector('#detailCriteria'),
    detailComments: document.querySelector('#detailComments'),
    commentForm: document.querySelector('#commentForm'),
    commentInput: document.querySelector('#commentInput'),
};

document.querySelector('#newStoryButton').addEventListener('click', () => openForm());
document.querySelector('#closeDetailButton').addEventListener('click', closeDetail);
document.querySelector('#editStoryButton').addEventListener('click', () => {
    const story = selectedStory();
    if (story) {
        openForm(story);
    }
});
document.querySelector('#deleteStoryButton').addEventListener('click', deleteSelectedStory);
document.querySelectorAll('[data-close-dialog]').forEach((button) => {
    button.addEventListener('click', () => elements.storyDialog.close());
});

[elements.search, elements.statusFilter, elements.pointsFilter].forEach((input) => {
    input.addEventListener('input', render);
});

statuses.forEach((status) => {
    const list = document.querySelector(`#${status}List`);
    list.addEventListener('dragover', handleDragOver);
    list.addEventListener('drop', handleDrop);
    list.addEventListener('dragleave', () => list.classList.remove('drag-over'));
});

elements.storyForm.addEventListener('submit', saveStory);
elements.commentForm.addEventListener('submit', addComment);

loadStories();

async function loadStories() {
    try {
        state.stories = await request('/api/stories');
        render();
    } catch (error) {
        showMessage(error.message);
    }
}

function render() {
    const filtered = filteredStories();

    statuses.forEach((status) => {
        const list = document.querySelector(`#${status}List`);
        list.innerHTML = '';

        filtered
            .filter((story) => story.status === status)
            .forEach((story) => list.appendChild(storyCard(story)));

        const points = filtered
            .filter((story) => story.status === status)
            .reduce((sum, story) => sum + story.points, 0);
        document.querySelector(`#${status}Points`).textContent = `${points} p`;
    });

    if (state.selectedId) {
        renderDetail();
    }
}

function filteredStories() {
    const query = elements.search.value.trim().toLowerCase();
    const status = elements.statusFilter.value;
    const points = elements.pointsFilter.value;

    return state.stories.filter((story) => {
        const matchesQuery = !query
            || story.title.toLowerCase().includes(query)
            || story.description.toLowerCase().includes(query);
        const matchesStatus = !status || story.status === status;
        const matchesPoints = !points
            || (points === '0-3' && story.points <= 3)
            || (points === '4-8' && story.points >= 4 && story.points <= 8)
            || (points === '9+' && story.points >= 9);

        return matchesQuery && matchesStatus && matchesPoints;
    });
}

function storyCard(story) {
    const card = document.createElement('article');
    card.className = 'story-card';
    card.draggable = true;
    card.dataset.id = story.id;
    card.dataset.status = story.status;
    card.innerHTML = `
        <h3></h3>
        <p></p>
        <div class="card-meta">
            <span class="badge">${story.points} p</span>
            <span class="badge">${story.acceptanceCriteria.length} ting.</span>
            <span class="badge">${story.comments.length} komm.</span>
        </div>
    `;
    card.querySelector('h3').textContent = story.title;
    card.querySelector('p').textContent = story.description || 'Kirjeldus puudub.';
    card.addEventListener('click', () => openDetail(story.id));
    card.addEventListener('dragstart', (event) => {
        state.draggedId = story.id;
        card.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(story.id));
    });
    card.addEventListener('dragend', () => {
        state.draggedId = null;
        card.classList.remove('dragging');
        document.querySelectorAll('.story-list').forEach((list) => list.classList.remove('drag-over'));
    });
    return card;
}

function handleDragOver(event) {
    event.preventDefault();
    const list = event.currentTarget;
    list.classList.add('drag-over');
    const after = elementAfterPointer(list, event.clientY);
    const dragging = document.querySelector('.story-card.dragging');
    if (!dragging) {
        return;
    }

    if (after) {
        list.insertBefore(dragging, after);
    } else {
        list.appendChild(dragging);
    }
}

async function handleDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
    const id = Number(event.dataTransfer.getData('text/plain') || state.draggedId);
    if (!id) {
        return;
    }

    try {
        await request('/api/stories/reorder', {
            method: 'PATCH',
            body: JSON.stringify({ stories: boardOrder() }),
        });
        await loadStories();
        showMessage('');
    } catch (error) {
        showMessage(error.message);
        await loadStories();
    }
}

function elementAfterPointer(list, y) {
    const cards = [...list.querySelectorAll('.story-card:not(.dragging)')];
    return cards.reduce((closest, card) => {
        const box = card.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset, element: card };
        }
        return closest;
    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
}

function boardOrder() {
    return statuses.flatMap((status) => [...document.querySelectorAll(`#${status}List .story-card`)].map((card, index) => ({
        id: Number(card.dataset.id),
        status,
        priority: index + 1,
    })));
}

function openForm(story = null) {
    elements.dialogTitle.textContent = story ? 'Muuda storyt' : 'Uus story';
    elements.storyId.value = story?.id ?? '';
    elements.title.value = story?.title ?? '';
    elements.description.value = story?.description ?? '';
    elements.points.value = story?.points ?? '';
    elements.status.value = story?.status ?? 'todo';
    elements.criteria.value = story?.acceptanceCriteria.join('\n') ?? '';
    showMessage('');
    elements.storyDialog.showModal();
}

async function saveStory(event) {
    event.preventDefault();
    const id = elements.storyId.value;
    const pointsValue = elements.points.value;

    if (pointsValue === '' || !Number.isInteger(Number(pointsValue)) || Number(pointsValue) < 0) {
        showMessage('Punktid peavad olema täidetud mittenegatiivse täisarvuna.');
        return;
    }

    const payload = {
        title: elements.title.value,
        description: elements.description.value,
        status: elements.status.value,
        points: Number(pointsValue),
        acceptanceCriteria: elements.criteria.value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean),
    };

    if (payload.acceptanceCriteria.length === 0) {
        showMessage('Lisa vähemalt üks vastuvõtutingimus.');
        return;
    }

    try {
        await request(id ? `/api/stories/${id}` : '/api/stories', {
            method: id ? 'PUT' : 'POST',
            body: JSON.stringify(payload),
        });
        elements.storyDialog.close();
        await loadStories();
        if (id) {
            state.selectedId = Number(id);
            renderDetail();
        }
    } catch (error) {
        showMessage(error.message);
    }
}

function openDetail(id) {
    state.selectedId = id;
    renderDetail();
    elements.detailPanel.hidden = false;
}

function closeDetail() {
    state.selectedId = null;
    elements.detailPanel.hidden = true;
}

function selectedStory() {
    return state.stories.find((story) => story.id === state.selectedId);
}

function renderDetail() {
    const story = selectedStory();
    if (!story) {
        closeDetail();
        return;
    }

    elements.detailTitle.textContent = story.title;
    elements.detailMeta.textContent = `${story.status} | ${story.points} punkti | loodud ${story.createdAt} | muudetud ${story.updatedAt}`;
    elements.detailDescription.textContent = story.description || 'Kirjeldus puudub.';
    elements.detailCriteria.innerHTML = '';
    story.acceptanceCriteria.forEach((criterion) => {
        const li = document.createElement('li');
        li.textContent = criterion;
        elements.detailCriteria.appendChild(li);
    });

    elements.detailComments.innerHTML = '';
    story.comments.forEach((comment) => {
        const item = document.createElement('div');
        item.className = 'comment';
        item.innerHTML = `
            <p></p>
            <small></small>
            <div class="comment-actions">
                <button class="danger-button" type="button">Kustuta</button>
            </div>
        `;
        item.querySelector('p').textContent = comment.text;
        item.querySelector('small').textContent = comment.createdAt;
        item.querySelector('button').addEventListener('click', () => deleteComment(story.id, comment.id));
        elements.detailComments.appendChild(item);
    });
}

async function addComment(event) {
    event.preventDefault();
    const story = selectedStory();
    if (!story) {
        return;
    }

    const text = elements.commentInput.value.trim();
    if (!text) {
        showMessage('Kommentaar ei tohi olla tühi.');
        return;
    }

    try {
        await request(`/api/stories/${story.id}/comments`, {
            method: 'POST',
            body: JSON.stringify({ text }),
        });
        elements.commentInput.value = '';
        await loadStories();
    } catch (error) {
        showMessage(error.message);
    }
}

async function deleteComment(storyId, commentId) {
    try {
        await request(`/api/stories/${storyId}/comments/${commentId}`, { method: 'DELETE' });
        await loadStories();
    } catch (error) {
        showMessage(error.message);
    }
}

async function deleteSelectedStory() {
    const story = selectedStory();
    if (!story || !confirm('Kas kustutada see story?')) {
        return;
    }

    try {
        await request(`/api/stories/${story.id}`, { method: 'DELETE' });
        closeDetail();
        await loadStories();
    } catch (error) {
        showMessage(error.message);
    }
}

async function request(url, options = {}) {
    const response = await fetch(url, {
        headers: { 'Content-Type': 'application/json' },
        ...options,
    });

    if (response.status === 204) {
        return null;
    }

    const payload = await response.json();
    if (!response.ok) {
        throw new Error(payload.error || 'Päring ebaõnnestus.');
    }

    return payload;
}

function showMessage(text) {
    elements.message.textContent = text;
}

