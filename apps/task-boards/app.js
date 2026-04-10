import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

const root = document.querySelector('[data-task-boards="true"]');

if (!(root instanceof HTMLElement) || root.dataset.taskBoardsReady === 'true') {
    // nothing to do
} else {
    root.dataset.taskBoardsReady = 'true';

    const apiUrl = String(root.dataset.apiUrl || '').trim();
    const shell = root.querySelector('[data-task-boards-shell]');
    const notice = root.querySelector('[data-task-boards-notice]');
    const stateNode = document.getElementById('task-boards-state');

    if (!(shell instanceof HTMLElement) || !(notice instanceof HTMLElement) || !(stateNode instanceof HTMLScriptElement) || apiUrl === '') {
        throw new Error('No fue posible iniciar Tableros de tareas.');
    }

    const initialState = normalizeBootstrap(parseJson(stateNode.textContent || '{}'));
    const state = {
        apiUrl,
        project: initialState.project,
        viewer: initialState.viewer,
        boards: initialState.boards,
        members: initialState.members,
        activeBoard: initialState.active_board,
        searchQuery: '',
        notice: null,
        boardModal: {
            open: false,
            mode: 'create',
            draft: createBoardDraft(),
        },
        structureModal: {
            open: false,
            mode: 'columns',
            draft: [],
        },
        cardPanel: {
            open: false,
            draft: createCardDraft(),
            dirty: false,
        },
        realtime: initialState.realtime,
    };

    const realtime = createRealtimeClient(state.realtime);
    let projectChannel = null;
    let boardChannel = null;
    let refreshTimeout = 0;
    let draggingCardId = '';

    root.addEventListener('click', handleClick);
    root.addEventListener('input', handleInput);
    root.addEventListener('change', handleChange);
    root.addEventListener('submit', handleSubmit);
    root.addEventListener('dragstart', handleDragStart);
    root.addEventListener('dragend', handleDragEnd);
    root.addEventListener('dragover', handleDragOver);
    root.addEventListener('drop', handleDrop);
    window.addEventListener('beforeunload', cleanupRealtime);

    render();
    subscribeRealtime();

    function handleClick(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const boardSelect = target.closest('[data-task-board-select]');

        if (boardSelect instanceof HTMLElement) {
            event.preventDefault();
            const boardId = String(boardSelect.dataset.taskBoardSelect || '');

            if (boardId !== '' && boardId !== String(state.activeBoard?.board?.id || '')) {
                void reloadBootstrap(boardId);
            }

            return;
        }

        if (target.closest('[data-task-board-create]')) {
            event.preventDefault();
            openBoardModal('create');
            return;
        }

        if (target.closest('[data-task-board-edit]')) {
            event.preventDefault();
            openBoardModal('edit');
            return;
        }

        if (target.closest('[data-task-board-delete]')) {
            event.preventDefault();
            void deleteBoard();
            return;
        }

        const structureOpen = target.closest('[data-task-structure-open]');

        if (structureOpen instanceof HTMLElement) {
            event.preventDefault();
            const mode = String(structureOpen.dataset.taskStructureOpen || 'columns');
            openStructureModal(mode);
            return;
        }

        const structureAction = target.closest('[data-task-structure-action]');

        if (structureAction instanceof HTMLElement) {
            event.preventDefault();
            const mode = String(state.structureModal.mode || 'columns');
            const action = String(structureAction.dataset.taskStructureAction || '');
            const index = Number.parseInt(String(structureAction.dataset.index || '-1'), 10);

            syncStructureDraftFromDom();

            if (action === 'add') {
                state.structureModal.draft.push(createStructureItem(mode));
                render();
                return;
            }

            if (!Number.isFinite(index) || index < 0) {
                return;
            }

            if (action === 'up' && index > 0) {
                [state.structureModal.draft[index - 1], state.structureModal.draft[index]] = [state.structureModal.draft[index], state.structureModal.draft[index - 1]];
            } else if (action === 'down' && index < state.structureModal.draft.length - 1) {
                [state.structureModal.draft[index + 1], state.structureModal.draft[index]] = [state.structureModal.draft[index], state.structureModal.draft[index + 1]];
            } else if (action === 'delete') {
                state.structureModal.draft.splice(index, 1);
            }

            render();
            return;
        }

        if (target.closest('[data-task-board-modal-close]')) {
            event.preventDefault();
            state.boardModal.open = false;
            render();
            return;
        }

        if (target.closest('[data-task-structure-close]')) {
            event.preventDefault();
            state.structureModal.open = false;
            render();
            return;
        }

        const cardOpen = target.closest('[data-task-card-open]');

        if (cardOpen instanceof HTMLElement) {
            event.preventDefault();
            const cardId = String(cardOpen.dataset.taskCardOpen || '');

            if (cardId !== '') {
                openExistingCard(cardId);
            }

            return;
        }

        const newCardTrigger = target.closest('[data-task-card-new]');

        if (newCardTrigger instanceof HTMLElement) {
            event.preventDefault();
            openNewCard(
                String(newCardTrigger.dataset.columnId || ''),
                String(newCardTrigger.dataset.swimlaneId || '')
            );
            return;
        }

        if (target.closest('[data-task-card-close]')) {
            event.preventDefault();
            state.cardPanel.open = false;
            state.cardPanel.dirty = false;
            render();
            return;
        }

        if (target.closest('[data-task-card-delete]')) {
            event.preventDefault();
            void deleteCard();
            return;
        }

        if (target.closest('[data-task-comment-submit]')) {
            event.preventDefault();
            void saveComment();
            return;
        }

        if (target.closest('[data-task-checklist-add]')) {
            event.preventDefault();
            syncCardDraftFromDom();
            state.cardPanel.draft.checklist.push(createChecklistItem());
            render();
            return;
        }

        const checklistAction = target.closest('[data-task-checklist-action]');

        if (checklistAction instanceof HTMLElement) {
            event.preventDefault();
            const action = String(checklistAction.dataset.taskChecklistAction || '');
            const index = Number.parseInt(String(checklistAction.dataset.index || '-1'), 10);

            if (!Number.isFinite(index) || index < 0) {
                return;
            }

            syncCardDraftFromDom();

            if (action === 'delete') {
                state.cardPanel.draft.checklist.splice(index, 1);
            } else if (action === 'toggle') {
                const item = state.cardPanel.draft.checklist[index];

                if (item) {
                    item.is_done = !item.is_done;
                }
            }

            state.cardPanel.dirty = true;
            render();
        }
    }

    function handleInput(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches('[data-task-board-search]')) {
            state.searchQuery = String(target.value || '').trim().toLowerCase();
            render();
            return;
        }

        if (target.matches('[data-task-card-description]')) {
            state.cardPanel.dirty = true;
            const preview = root.querySelector('[data-task-markdown-preview]');

            if (preview instanceof HTMLElement) {
                preview.innerHTML = renderMarkdown(String(target.value || ''));
            }

            return;
        }

        if (target.matches('[data-task-comment-input]')) {
            return;
        }

        if (target.closest('[data-task-card-form]')) {
            state.cardPanel.dirty = true;
            return;
        }
    }

    function handleChange(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches('[data-task-comment-input]')) {
            return;
        }

        if (target.closest('[data-task-card-form]')) {
            state.cardPanel.dirty = true;
        }
    }

    function handleSubmit(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.matches('[data-task-board-form]')) {
            event.preventDefault();
            void saveBoard(form);
            return;
        }

        if (form.matches('[data-task-structure-form]')) {
            event.preventDefault();
            void saveStructure();
            return;
        }

        if (form.matches('[data-task-card-form]')) {
            event.preventDefault();
            void saveCard();
            return;
        }
    }

    function handleDragStart(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const card = target.closest('.task-boards-card');

        if (!(card instanceof HTMLElement)) {
            return;
        }

        draggingCardId = String(card.dataset.taskCardId || '').trim();

        if (draggingCardId === '') {
            return;
        }

        card.classList.add('is-dragging');
        event.dataTransfer?.setData('text/plain', draggingCardId);
        event.dataTransfer?.setDragImage(card, 24, 24);
    }

    function handleDragEnd() {
        draggingCardId = '';
        root.querySelectorAll('.task-boards-card.is-dragging').forEach((card) => card.classList.remove('is-dragging'));
        root.querySelectorAll('.task-boards-card-list.is-drop-target').forEach((list) => list.classList.remove('is-drop-target'));
        root.querySelectorAll('.task-boards-cell.is-drop-target').forEach((cell) => cell.classList.remove('is-drop-target'));
    }

    function handleDragOver(event) {
        if (draggingCardId === '') {
            return;
        }

        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const list = target.closest('[data-task-cell-list]');

        if (!(list instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        root.querySelectorAll('.task-boards-card-list.is-drop-target').forEach((item) => item.classList.remove('is-drop-target'));
        root.querySelectorAll('.task-boards-cell.is-drop-target').forEach((item) => item.classList.remove('is-drop-target'));
        list.classList.add('is-drop-target');
        list.closest('.task-boards-cell')?.classList.add('is-drop-target');

        const draggingCard = root.querySelector(`.task-boards-card[data-task-card-id="${escapeAttribute(draggingCardId)}"]`);

        if (!(draggingCard instanceof HTMLElement)) {
            return;
        }

        const afterElement = getDragAfterElement(list, event.clientY);

        if (afterElement) {
            list.insertBefore(draggingCard, afterElement);
        } else {
            list.appendChild(draggingCard);
        }
    }

    async function handleDrop(event) {
        if (draggingCardId === '') {
            return;
        }

        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            handleDragEnd();
            return;
        }

        const list = target.closest('[data-task-cell-list]');

        if (!(list instanceof HTMLElement) || !state.activeBoard) {
            handleDragEnd();
            return;
        }

        event.preventDefault();

        const columnId = String(list.dataset.columnId || '').trim();
        const swimlaneId = String(list.dataset.swimlaneId || '').trim();

        if (columnId === '' || swimlaneId === '') {
            handleDragEnd();
            return;
        }

        const card = state.activeBoard.cards.find((item) => item.id === draggingCardId);

        if (!card) {
            handleDragEnd();
            return;
        }

        const previousColumnId = String(card.column_id || '');
        const previousSwimlaneId = String(card.swimlane_id || '');
        const previousSortOrder = Number(card.sort_order || 0);
        const sortOrder = resolveDroppedSortOrder(list, draggingCardId);

        card.column_id = columnId;
        card.swimlane_id = swimlaneId;
        card.sort_order = sortOrder;
        render();

        try {
            await sendJson('move-card', {
                board_id: String(state.activeBoard.board.id || ''),
                card_id: draggingCardId,
                column_id: columnId,
                swimlane_id: swimlaneId,
                sort_order: sortOrder,
            });
            scheduleRealtimeRefresh(true);
        } catch (error) {
            card.column_id = previousColumnId;
            card.swimlane_id = previousSwimlaneId;
            card.sort_order = previousSortOrder;
            showNotice('error', humanizeError(error));
            render();
        } finally {
            handleDragEnd();
        }
    }

    function render() {
        renderNotice();
        shell.innerHTML = renderShell();
    }

    function renderNotice() {
        notice.className = `task-boards-notice ${state.notice ? `task-boards-notice--${escapeToken(state.notice.type)}` : 'hidden'}`;
        notice.textContent = state.notice?.message ?? '';
    }

    function renderShell() {
        if (state.boards.length === 0) {
            return `
                <section class="task-boards-empty-state">
                    <span class="material-symbols-rounded">view_kanban</span>
                    <h2>Empieza con tu primer tablero</h2>
                    <p>Crea un tablero por proyecto, define columnas, carriles y comienza a mover trabajo en tiempo real con tu equipo.</p>
                    <button type="button" class="task-boards-primary" data-task-board-create>
                        <span class="material-symbols-rounded">add_circle</span>
                        <span>Crear tablero</span>
                    </button>
                </section>
                ${renderBoardModal()}
            `;
        }

        return `
            <section class="task-boards-board-switcher">
                <div class="task-boards-board-strip">
                    ${state.boards.map((board) => renderBoardTab(board)).join('')}
                    <button type="button" class="task-boards-board-tab task-boards-board-tab--create" data-task-board-create>
                        <span class="material-symbols-rounded">add</span>
                        <span>Nuevo</span>
                    </button>
                </div>
            </section>

            ${state.activeBoard ? renderActiveBoard() : ''}

            ${renderBoardModal()}
            ${renderStructureModal()}
            ${renderCardPanel()}
        `;
    }

    function renderBoardTab(board) {
        const activeBoardId = String(state.activeBoard?.board?.id || '');
        const isActive = String(board.id || '') === activeBoardId;

        return `
            <button type="button" class="task-boards-board-tab ${isActive ? 'is-active' : ''}" data-task-board-select="${escapeHtml(board.id)}">
                <strong>${escapeHtml(board.title || 'Tablero')}</strong>
                <span>${escapeHtml(board.description || 'Tablero listo para organizar tareas.')}</span>
            </button>
        `;
    }

    function renderActiveBoard() {
        const board = state.activeBoard?.board ?? null;

        if (!board) {
            return '';
        }

        return `
            <section class="task-boards-workbench">
                <header class="task-boards-toolbar">
                    <div class="task-boards-toolbar-copy">
                        <p class="task-boards-eyebrow">Proyecto activo</p>
                        <h2>${escapeHtml(board.title || 'Tablero')}</h2>
                        <p>${escapeHtml(board.description || 'Organiza tareas, limita trabajo en curso y da seguimiento a tu equipo sin salir del proyecto.')}</p>
                    </div>

                    <div class="task-boards-toolbar-actions">
                        <label class="task-boards-search-shell">
                            <span class="material-symbols-rounded">search</span>
                            <input type="search" value="${escapeHtml(state.searchQuery)}" placeholder="Buscar tareas, etiquetas o responsables" data-task-board-search>
                        </label>

                        <div class="task-boards-toolbar-buttons">
                            <button type="button" class="task-boards-secondary" data-task-board-edit>
                                <span class="material-symbols-rounded">edit</span>
                                <span>Tablero</span>
                            </button>
                            <button type="button" class="task-boards-secondary" data-task-structure-open="columns">
                                <span class="material-symbols-rounded">table_rows</span>
                                <span>Columnas</span>
                            </button>
                            <button type="button" class="task-boards-secondary" data-task-structure-open="swimlanes">
                                <span class="material-symbols-rounded">view_week</span>
                                <span>Carriles</span>
                            </button>
                            <button type="button" class="task-boards-secondary" data-task-structure-open="labels">
                                <span class="material-symbols-rounded">sell</span>
                                <span>Etiquetas</span>
                            </button>
                            <button type="button" class="task-boards-primary" data-task-card-new data-column-id="${escapeHtml(String(state.activeBoard.columns[0]?.id || ''))}" data-swimlane-id="${escapeHtml(String(state.activeBoard.swimlanes[0]?.id || ''))}">
                                <span class="material-symbols-rounded">add_task</span>
                                <span>Nueva tarea</span>
                            </button>
                        </div>
                    </div>
                </header>

                <section class="task-boards-canvas-shell">
                    ${renderBoardCanvas()}
                </section>
            </section>
        `;
    }

    function renderBoardCanvas() {
        const columns = state.activeBoard?.columns ?? [];
        const swimlanes = state.activeBoard?.swimlanes ?? [];
        const columnCounts = countCardsByColumn(state.activeBoard?.cards ?? []);

        return `
            <div class="task-boards-canvas-scroll">
                <div class="task-boards-grid" style="--task-board-column-count:${Math.max(columns.length, 1)};">
                    <div class="task-boards-grid-corner">
                        <strong>Carriles</strong>
                        <span>Separan trabajo especial o urgente.</span>
                    </div>
                    ${columns.map((column) => renderColumnHead(column, columnCounts[column.id] ?? 0)).join('')}
                    ${swimlanes.map((swimlane) => renderSwimlaneRow(swimlane, columns, columnCounts)).join('')}
                </div>
            </div>
        `;
    }

    function renderColumnHead(column, count) {
        const limit = normalizeNumber(column.wip_limit);
        const isOverLimit = limit !== null && count > limit;

        return `
            <div class="task-boards-column-head ${isOverLimit ? 'is-over-limit' : ''}">
                <div>
                    <h3>${escapeHtml(column.title || 'Columna')}</h3>
                    <p>${count} ${count === 1 ? 'tarea' : 'tareas'}${limit !== null ? ` / limite ${limit}` : ''}</p>
                </div>
                ${limit !== null ? `<span class="task-boards-column-limit ${isOverLimit ? 'is-over-limit' : ''}">WIP ${limit}</span>` : ''}
            </div>
        `;
    }

    function renderSwimlaneRow(swimlane, columns, columnCounts) {
        return `
            <div class="task-boards-lane-title">
                <strong>${escapeHtml(swimlane.title || 'Carril')}</strong>
                <span>${escapeHtml(swimlane.title === 'Expedite' ? 'Prioriza lo urgente sin perder el contexto del tablero.' : 'Organiza un flujo adicional de trabajo dentro del mismo tablero.')}</span>
            </div>
            ${columns.map((column) => renderCell(swimlane, column, columnCounts[column.id] ?? 0)).join('')}
        `;
    }

    function renderCell(swimlane, column, count) {
        const cards = getCardsForCell(String(column.id || ''), String(swimlane.id || ''));
        const limit = normalizeNumber(column.wip_limit);
        const isOverLimit = limit !== null && count > limit;

        return `
            <div class="task-boards-cell ${isOverLimit ? 'is-over-limit' : ''}">
                <div class="task-boards-cell-head">
                    <button type="button" class="task-boards-link-button" data-task-card-new data-column-id="${escapeHtml(String(column.id || ''))}" data-swimlane-id="${escapeHtml(String(swimlane.id || ''))}">
                        <span class="material-symbols-rounded">add</span>
                        <span>Agregar</span>
                    </button>
                </div>
                <div class="task-boards-card-list" data-task-cell-list="true" data-column-id="${escapeHtml(String(column.id || ''))}" data-swimlane-id="${escapeHtml(String(swimlane.id || ''))}">
                    ${cards.length > 0
                        ? cards.map((card) => renderCard(card)).join('')
                        : '<div class="task-boards-cell-empty">Arrastra o crea una tarea aqui.</div>'}
                </div>
            </div>
        `;
    }

    function renderCard(card) {
        const dueDate = String(card.due_date || '').trim();
        const overdue = isDateOverdue(dueDate);
        const commentsCount = getCommentsForCard(String(card.id || '')).length;
        const checklist = normalizeChecklist(card.checklist);
        const checklistDone = checklist.filter((item) => item.is_done).length;
        const assignedMembers = getAssignedMembers(card);
        const labels = getCardLabels(card);

        return `
            <article
                class="task-boards-card ${overdue ? 'is-overdue' : ''}"
                draggable="true"
                data-task-card-id="${escapeHtml(String(card.id || ''))}"
                data-task-card-open="${escapeHtml(String(card.id || ''))}"
                data-task-sort-order="${escapeHtml(String(card.sort_order || 0))}"
            >
                <div class="task-boards-card-top">
                    <strong>${escapeHtml(card.title || 'Tarea')}</strong>
                    <span class="task-boards-priority task-boards-priority--${escapeHtml(resolvePriority(card.priority))}">${escapeHtml(priorityLabel(card.priority))}</span>
                </div>

                ${labels.length > 0 ? `<div class="task-boards-card-labels">${labels.map(renderLabelChip).join('')}</div>` : ''}

                ${String(card.description_markdown || '').trim() !== ''
                    ? `<p class="task-boards-card-copy">${escapeHtml(String(card.description_markdown || '').trim())}</p>`
                    : ''}

                <div class="task-boards-card-meta">
                    ${dueDate !== '' ? `<span class="task-boards-card-badge ${overdue ? 'is-overdue' : ''}"><span class="material-symbols-rounded">event</span>${escapeHtml(formatDate(dueDate))}</span>` : ''}
                    ${checklist.length > 0 ? `<span class="task-boards-card-badge"><span class="material-symbols-rounded">checklist</span>${checklistDone}/${checklist.length}</span>` : ''}
                    ${commentsCount > 0 ? `<span class="task-boards-card-badge"><span class="material-symbols-rounded">forum</span>${commentsCount}</span>` : ''}
                </div>

                <div class="task-boards-card-bottom">
                    <div class="task-boards-assignee-stack">
                        ${assignedMembers.length > 0 ? assignedMembers.slice(0, 3).map(renderMemberBadge).join('') : '<span class="task-boards-assignee-placeholder">Sin asignar</span>'}
                    </div>
                </div>
            </article>
        `;
    }

    function renderLabelChip(label) {
        return `<span class="task-boards-label-chip" style="--task-label-color:${escapeHtml(String(label.color || '#2F7CEF'))};">${escapeHtml(label.title || 'Etiqueta')}</span>`;
    }

    function renderMemberBadge(member) {
        const label = String(member.label || 'Miembro');
        const initials = label.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('') || 'U';
        return `<span class="task-boards-assignee-badge" title="${escapeHtml(label)}">${escapeHtml(initials)}</span>`;
    }

    function renderBoardModal() {
        if (!state.boardModal.open) {
            return '';
        }

        const draft = state.boardModal.draft;

        return `
            <div class="task-boards-overlay" data-task-board-modal-close></div>
            <section class="task-boards-dialog" aria-modal="true" role="dialog">
                <form class="task-boards-dialog-card" data-task-board-form>
                    <div class="task-boards-dialog-head">
                        <div>
                            <p class="task-boards-eyebrow">${state.boardModal.mode === 'create' ? 'Nuevo' : 'Editar'}</p>
                            <h3>${state.boardModal.mode === 'create' ? 'Crear tablero' : 'Editar tablero'}</h3>
                        </div>
                        <button type="button" class="task-boards-icon-button" data-task-board-modal-close aria-label="Cerrar">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>

                    <input type="hidden" name="id" value="${escapeHtml(draft.id)}">

                    <label class="task-boards-field">
                        <span>Titulo</span>
                        <input type="text" name="title" value="${escapeHtml(draft.title)}" placeholder="Nombre del tablero" required>
                    </label>

                    <label class="task-boards-field">
                        <span>Descripcion</span>
                        <textarea name="description" rows="4" placeholder="Describe el flujo de este tablero">${escapeHtml(draft.description)}</textarea>
                    </label>

                    <div class="task-boards-dialog-footer">
                        ${state.boardModal.mode === 'edit' ? `
                            <button type="button" class="task-boards-danger" data-task-board-delete>
                                <span class="material-symbols-rounded">delete</span>
                                <span>Eliminar tablero</span>
                            </button>
                        ` : '<span></span>'}
                        <button type="submit" class="task-boards-primary">
                            <span class="material-symbols-rounded">save</span>
                            <span>${state.boardModal.mode === 'create' ? 'Crear tablero' : 'Guardar cambios'}</span>
                        </button>
                    </div>
                </form>
            </section>
        `;
    }

    function renderStructureModal() {
        if (!state.structureModal.open) {
            return '';
        }

        const mode = state.structureModal.mode;
        const titleMap = {
            columns: ['Columnas', 'Define el flujo, orden y limite WIP por columna.'],
            swimlanes: ['Carriles', 'Separa trabajos especiales o urgentes sin duplicar tableros.'],
            labels: ['Etiquetas', 'Crea categorias visuales para priorizar y filtrar tareas.'],
        };
        const [title, subtitle] = titleMap[mode] ?? titleMap.columns;

        return `
            <div class="task-boards-overlay" data-task-structure-close></div>
            <section class="task-boards-dialog task-boards-dialog--wide" aria-modal="true" role="dialog">
                <form class="task-boards-dialog-card" data-task-structure-form data-task-structure-mode="${escapeHtml(mode)}">
                    <div class="task-boards-dialog-head">
                        <div>
                            <p class="task-boards-eyebrow">Configuracion</p>
                            <h3>${title}</h3>
                            <p>${subtitle}</p>
                        </div>
                        <button type="button" class="task-boards-icon-button" data-task-structure-close aria-label="Cerrar">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>

                    <div class="task-boards-structure-list">
                        ${state.structureModal.draft.map((item, index) => renderStructureRow(mode, item, index)).join('')}
                    </div>

                    <div class="task-boards-structure-actions">
                        <button type="button" class="task-boards-secondary" data-task-structure-action="add">
                            <span class="material-symbols-rounded">add</span>
                            <span>Agregar ${mode === 'columns' ? 'columna' : mode === 'swimlanes' ? 'carril' : 'etiqueta'}</span>
                        </button>
                        <span class="task-boards-helper">${mode === 'swimlanes' ? 'El primer carril se toma como carril principal.' : 'Puedes reordenar usando las flechas.'}</span>
                    </div>

                    <div class="task-boards-dialog-footer">
                        <button type="button" class="task-boards-secondary" data-task-structure-close>Cancelar</button>
                        <button type="submit" class="task-boards-primary">
                            <span class="material-symbols-rounded">save</span>
                            <span>Guardar estructura</span>
                        </button>
                    </div>
                </form>
            </section>
        `;
    }

    function renderStructureRow(mode, item, index) {
        return `
            <div class="task-boards-structure-row">
                <input type="hidden" name="draft_id" value="${escapeHtml(item.id)}" data-task-structure-field="id" data-index="${index}">
                <label class="task-boards-field">
                    <span>Nombre</span>
                    <input type="text" value="${escapeHtml(item.title)}" data-task-structure-field="title" data-index="${index}" placeholder="${mode === 'columns' ? 'Nueva columna' : mode === 'swimlanes' ? 'Nuevo carril' : 'Nueva etiqueta'}">
                </label>

                <label class="task-boards-field task-boards-field--compact">
                    <span>Color</span>
                    <input type="color" value="${escapeHtml(item.color)}" data-task-structure-field="color" data-index="${index}">
                </label>

                ${mode === 'columns'
                    ? `
                        <label class="task-boards-field task-boards-field--compact">
                            <span>WIP</span>
                            <input type="number" min="0" value="${escapeHtml(item.wip_limit)}" data-task-structure-field="wip_limit" data-index="${index}" placeholder="Sin limite">
                        </label>
                    `
                    : ''}

                <div class="task-boards-structure-buttons">
                    <button type="button" class="task-boards-icon-button" data-task-structure-action="up" data-index="${index}" aria-label="Subir">
                        <span class="material-symbols-rounded">arrow_upward</span>
                    </button>
                    <button type="button" class="task-boards-icon-button" data-task-structure-action="down" data-index="${index}" aria-label="Bajar">
                        <span class="material-symbols-rounded">arrow_downward</span>
                    </button>
                    <button type="button" class="task-boards-icon-button task-boards-icon-button--danger" data-task-structure-action="delete" data-index="${index}" aria-label="Eliminar">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>
            </div>
        `;
    }

    function renderCardPanel() {
        if (!state.cardPanel.open) {
            return '';
        }

        const draft = state.cardPanel.draft;
        const comments = getCommentsForCard(String(draft.id || ''));
        const activity = getActivityForCard(String(draft.id || ''));

        return `
            <div class="task-boards-overlay task-boards-overlay--panel" data-task-card-close></div>
            <aside class="task-boards-panel" aria-modal="true" role="dialog">
                <form class="task-boards-panel-shell" data-task-card-form>
                    <div class="task-boards-panel-head">
                        <div>
                            <p class="task-boards-eyebrow">Tarea</p>
                            <h3>${draft.id ? 'Editar tarea' : 'Nueva tarea'}</h3>
                        </div>
                        <button type="button" class="task-boards-icon-button" data-task-card-close aria-label="Cerrar">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>

                    <input type="hidden" name="id" value="${escapeHtml(draft.id)}">
                    <input type="hidden" name="board_id" value="${escapeHtml(draft.board_id)}">

                    <div class="task-boards-panel-body">
                        <div class="task-boards-panel-grid">
                            <label class="task-boards-field task-boards-field--wide">
                                <span>Titulo</span>
                                <input type="text" name="title" value="${escapeHtml(draft.title)}" placeholder="Escribe una tarea clara" required>
                            </label>

                            <label class="task-boards-field">
                                <span>Columna</span>
                                <select name="column_id">
                                    ${(state.activeBoard?.columns ?? []).map((column) => `
                                        <option value="${escapeHtml(String(column.id || ''))}" ${String(column.id || '') === draft.column_id ? 'selected' : ''}>
                                            ${escapeHtml(String(column.title || ''))}
                                        </option>
                                    `).join('')}
                                </select>
                            </label>

                            <label class="task-boards-field">
                                <span>Carril</span>
                                <select name="swimlane_id">
                                    ${(state.activeBoard?.swimlanes ?? []).map((swimlane) => `
                                        <option value="${escapeHtml(String(swimlane.id || ''))}" ${String(swimlane.id || '') === draft.swimlane_id ? 'selected' : ''}>
                                            ${escapeHtml(String(swimlane.title || ''))}
                                        </option>
                                    `).join('')}
                                </select>
                            </label>

                            <label class="task-boards-field">
                                <span>Prioridad</span>
                                <select name="priority">
                                    ${['low', 'medium', 'high', 'urgent'].map((priority) => `
                                        <option value="${priority}" ${priority === resolvePriority(draft.priority) ? 'selected' : ''}>
                                            ${escapeHtml(priorityLabel(priority))}
                                        </option>
                                    `).join('')}
                                </select>
                            </label>

                            <label class="task-boards-field">
                                <span>Inicio</span>
                                <input type="date" name="start_date" value="${escapeHtml(draft.start_date)}">
                            </label>

                            <label class="task-boards-field">
                                <span>Vencimiento</span>
                                <input type="date" name="due_date" value="${escapeHtml(draft.due_date)}">
                                ${isDateOverdue(draft.due_date) ? '<small class="task-boards-inline-alert">Esta tarea esta atrasada.</small>' : ''}
                            </label>

                            <label class="task-boards-field task-boards-field--wide">
                                <span>Descripcion (Markdown)</span>
                                <textarea name="description_markdown" rows="6" placeholder="Describe el trabajo, criterios de aceptacion o contexto" data-task-card-description>${escapeHtml(draft.description_markdown)}</textarea>
                            </label>
                        </div>

                        <section class="task-boards-preview-card">
                            <div class="task-boards-preview-head">
                                <strong>Vista previa Markdown</strong>
                                <span>Soporta listas, negritas, enlaces y codigo.</span>
                            </div>
                            <div class="task-boards-markdown-preview" data-task-markdown-preview>${renderMarkdown(draft.description_markdown)}</div>
                        </section>

                        <section class="task-boards-meta-grid">
                            <div class="task-boards-meta-card">
                                <div class="task-boards-meta-head">
                                    <strong>Responsables</strong>
                                    <span>Asigna uno o varios miembros.</span>
                                </div>
                                <div class="task-boards-checkbox-list">
                                    ${state.members.map((member) => `
                                        <label class="task-boards-checkbox-item">
                                            <input type="checkbox" name="assigned_member_ids[]" value="${escapeHtml(member.id)}" ${draft.assigned_member_ids.includes(member.id) ? 'checked' : ''}>
                                            <span>${escapeHtml(member.label)}</span>
                                        </label>
                                    `).join('')}
                                </div>
                            </div>

                            <div class="task-boards-meta-card">
                                <div class="task-boards-meta-head">
                                    <strong>Etiquetas</strong>
                                    <span>Marca categoria o contexto.</span>
                                </div>
                                <div class="task-boards-checkbox-list">
                                    ${(state.activeBoard?.labels ?? []).map((label) => `
                                        <label class="task-boards-checkbox-item">
                                            <input type="checkbox" name="label_ids[]" value="${escapeHtml(String(label.id || ''))}" ${draft.label_ids.includes(String(label.id || '')) ? 'checked' : ''}>
                                            <span class="task-boards-label-inline">
                                                <i style="background:${escapeHtml(String(label.color || '#2F7CEF'))};"></i>
                                                ${escapeHtml(String(label.title || 'Etiqueta'))}
                                            </span>
                                        </label>
                                    `).join('')}
                                </div>
                            </div>
                        </section>

                        <section class="task-boards-panel-card">
                            <div class="task-boards-meta-head">
                                <strong>Subtareas</strong>
                                <span>Divide el trabajo en pasos pequeños.</span>
                            </div>
                            <div class="task-boards-checklist-list">
                                ${draft.checklist.length > 0
                                    ? draft.checklist.map((item, index) => `
                                        <div class="task-boards-checklist-row">
                                            <input type="hidden" name="checklist_ids[]" value="${escapeHtml(item.id)}">
                                            <label class="task-boards-checklist-toggle">
                                                <input type="checkbox" name="checklist_done[]" value="${index}" ${item.is_done ? 'checked' : ''}>
                                                <span></span>
                                            </label>
                                            <input type="text" name="checklist_titles[]" value="${escapeHtml(item.title)}" placeholder="Describe la subtarea">
                                            <button type="button" class="task-boards-icon-button task-boards-icon-button--danger" data-task-checklist-action="delete" data-index="${index}" aria-label="Eliminar subtarea">
                                                <span class="material-symbols-rounded">delete</span>
                                            </button>
                                        </div>
                                    `).join('')
                                    : '<div class="task-boards-muted-box">Aun no hay subtareas. Agrega una para desglosar el trabajo.</div>'}
                            </div>
                            <button type="button" class="task-boards-secondary" data-task-checklist-add>
                                <span class="material-symbols-rounded">add</span>
                                <span>Agregar subtarea</span>
                            </button>
                        </section>

                        <section class="task-boards-panel-card">
                            <div class="task-boards-meta-head">
                                <strong>Comentarios</strong>
                                <span>Coordina al equipo dentro de la misma tarea.</span>
                            </div>
                            <div class="task-boards-comment-list">
                                ${comments.length > 0 ? comments.map(renderComment).join('') : '<div class="task-boards-muted-box">Todavia no hay comentarios.</div>'}
                            </div>

                            ${draft.id ? `
                                <div
                                    class="task-boards-comment-form"
                                    data-task-comment-form="true"
                                    data-board-id="${escapeHtml(draft.board_id)}"
                                    data-card-id="${escapeHtml(draft.id)}"
                                >
                                    <textarea rows="3" placeholder="Escribe un comentario para el equipo" data-task-comment-input></textarea>
                                    <div class="task-boards-comment-actions">
                                        <button type="button" class="task-boards-primary" data-task-comment-submit>
                                            <span class="material-symbols-rounded">send</span>
                                            <span>Comentar</span>
                                        </button>
                                    </div>
                                </div>
                            ` : '<div class="task-boards-muted-box">Guarda la tarea para habilitar comentarios e historial.</div>'}
                        </section>

                        <section class="task-boards-panel-card">
                            <div class="task-boards-meta-head">
                                <strong>Historial</strong>
                                <span>Registro de cambios y movimientos.</span>
                            </div>
                            <div class="task-boards-activity-list">
                                ${activity.length > 0 ? activity.map(renderActivity).join('') : '<div class="task-boards-muted-box">No hay actividad registrada todavia.</div>'}
                            </div>
                        </section>
                    </div>

                    <div class="task-boards-panel-footer">
                        ${draft.id ? `
                            <button type="button" class="task-boards-danger" data-task-card-delete>
                                <span class="material-symbols-rounded">delete</span>
                                <span>Eliminar tarea</span>
                            </button>
                        ` : '<span></span>'}
                        <div class="task-boards-panel-footer-actions">
                            <button type="button" class="task-boards-secondary" data-task-card-close>Cancelar</button>
                            <button type="submit" class="task-boards-primary">
                                <span class="material-symbols-rounded">save</span>
                                <span>${draft.id ? 'Guardar cambios' : 'Crear tarea'}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </aside>
        `;
    }

    function renderComment(comment) {
        return `
            <article class="task-boards-comment">
                <div class="task-boards-comment-head">
                    <strong>${escapeHtml(comment.author_email || 'Usuario')}</strong>
                    <span>${escapeHtml(formatDateTime(comment.created_at))}</span>
                </div>
                <div class="task-boards-comment-body">${renderMarkdown(String(comment.body_markdown || ''))}</div>
            </article>
        `;
    }

    function renderActivity(activity) {
        return `
            <article class="task-boards-activity-item">
                <strong>${escapeHtml(activity.actor_email || 'Sistema')}</strong>
                <p>${escapeHtml(activity.description || 'Actualizacion del tablero.')}</p>
                <span>${escapeHtml(formatDateTime(activity.created_at))}</span>
            </article>
        `;
    }

    function openBoardModal(mode) {
        const board = state.activeBoard?.board ?? null;
        state.boardModal.mode = mode;
        state.boardModal.open = true;
        state.boardModal.draft = mode === 'edit' && board
            ? createBoardDraft(board)
            : createBoardDraft();
        render();
    }

    function openStructureModal(mode) {
        if (!state.activeBoard) {
            return;
        }

        state.structureModal.open = true;
        state.structureModal.mode = mode;
        state.structureModal.draft = mode === 'columns'
            ? state.activeBoard.columns.map((column) => normalizeStructureItem(column, mode))
            : mode === 'swimlanes'
            ? state.activeBoard.swimlanes.map((swimlane) => normalizeStructureItem(swimlane, mode))
            : state.activeBoard.labels.map((label) => normalizeStructureItem(label, mode));
        render();
    }

    function openNewCard(columnId = '', swimlaneId = '') {
        if (!state.activeBoard) {
            return;
        }

        state.cardPanel.open = true;
        state.cardPanel.dirty = false;
        state.cardPanel.draft = createCardDraft({
            board_id: String(state.activeBoard.board.id || ''),
            column_id: columnId || String(state.activeBoard.columns[0]?.id || ''),
            swimlane_id: swimlaneId || String(state.activeBoard.swimlanes[0]?.id || ''),
        });
        render();
    }

    function openExistingCard(cardId) {
        if (!state.activeBoard) {
            return;
        }

        const card = state.activeBoard.cards.find((item) => String(item.id || '') === cardId);

        if (!card) {
            return;
        }

        state.cardPanel.open = true;
        state.cardPanel.dirty = false;
        state.cardPanel.draft = createCardDraft(card);
        render();
    }

    function syncStructureDraftFromDom() {
        const form = root.querySelector('[data-task-structure-form]');

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const rows = [...form.querySelectorAll('.task-boards-structure-row')];
        state.structureModal.draft = rows.map((row, index) => {
            const id = row.querySelector('[data-task-structure-field="id"]');
            const title = row.querySelector('[data-task-structure-field="title"]');
            const color = row.querySelector('[data-task-structure-field="color"]');
            const wipLimit = row.querySelector('[data-task-structure-field="wip_limit"]');

            return {
                id: id instanceof HTMLInputElement ? id.value : ensureId(`structure_${index}`),
                title: title instanceof HTMLInputElement ? String(title.value || '').trim() : '',
                color: color instanceof HTMLInputElement ? String(color.value || '#2F7CEF') : '#2F7CEF',
                wip_limit: wipLimit instanceof HTMLInputElement ? String(wipLimit.value || '').trim() : '',
            };
        });
    }

    function syncCardDraftFromDom() {
        const form = root.querySelector('[data-task-card-form]');

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const formData = new FormData(form);
        const checklistTitles = formData.getAll('checklist_titles[]').map((value) => String(value || '').trim());
        const checklistIds = formData.getAll('checklist_ids[]').map((value) => String(value || '').trim());
        const checklistDone = new Set(formData.getAll('checklist_done[]').map((value) => String(value || '').trim()));

        state.cardPanel.draft = {
            id: String(formData.get('id') || ''),
            board_id: String(formData.get('board_id') || String(state.activeBoard?.board?.id || '')),
            title: String(formData.get('title') || '').trim(),
            description_markdown: String(formData.get('description_markdown') || ''),
            column_id: String(formData.get('column_id') || ''),
            swimlane_id: String(formData.get('swimlane_id') || ''),
            priority: resolvePriority(String(formData.get('priority') || 'medium')),
            start_date: String(formData.get('start_date') || ''),
            due_date: String(formData.get('due_date') || ''),
            assigned_member_ids: formData.getAll('assigned_member_ids[]').map((value) => String(value || '')),
            label_ids: formData.getAll('label_ids[]').map((value) => String(value || '')),
            checklist: checklistTitles.map((title, index) => ({
                id: checklistIds[index] || ensureId(`checklist_${index}`),
                title,
                is_done: checklistDone.has(String(index)),
            })).filter((item) => item.title !== ''),
            sort_order: Number.parseFloat(String(state.cardPanel.draft.sort_order || 0)) || 0,
        };
    }

    async function saveBoard(form) {
        const formData = new FormData(form);
        const payload = {
            id: String(formData.get('id') || '').trim(),
            title: String(formData.get('title') || '').trim(),
            description: String(formData.get('description') || '').trim(),
        };

        try {
            const response = await sendJson('save-board', payload);
            applyBootstrap(response);
            state.boardModal.open = false;
            showNotice('success', payload.id ? 'Tablero actualizado correctamente.' : 'Tablero creado correctamente.');
            render();
        } catch (error) {
            showNotice('error', humanizeError(error));
        }
    }

    async function deleteBoard() {
        const boardId = String(state.activeBoard?.board?.id || '').trim();

        if (boardId === '') {
            return;
        }

        if (!window.confirm('Este tablero se eliminara por completo. ¿Deseas continuar?')) {
            return;
        }

        try {
            const response = await sendJson('delete-board', { board_id: boardId });
            applyBootstrap(response);
            state.boardModal.open = false;
            state.structureModal.open = false;
            state.cardPanel.open = false;
            showNotice('success', 'Tablero eliminado correctamente.');
            render();
        } catch (error) {
            showNotice('error', humanizeError(error));
        }
    }

    async function saveStructure() {
        if (!state.activeBoard) {
            return;
        }

        syncStructureDraftFromDom();

        const columns = state.structureModal.mode === 'columns'
            ? state.structureModal.draft
            : state.activeBoard.columns.map((column) => normalizeStructureItem(column, 'columns'));
        const swimlanes = state.structureModal.mode === 'swimlanes'
            ? state.structureModal.draft
            : state.activeBoard.swimlanes.map((swimlane) => normalizeStructureItem(swimlane, 'swimlanes'));
        const labels = state.structureModal.mode === 'labels'
            ? state.structureModal.draft
            : state.activeBoard.labels.map((label) => normalizeStructureItem(label, 'labels'));

        try {
            const response = await sendJson('save-structure', {
                board_id: String(state.activeBoard.board.id || ''),
                columns,
                swimlanes,
                labels,
            });
            applyBoardPayload(response);
            state.structureModal.open = false;
            showNotice('success', 'Estructura del tablero actualizada.');
            render();
        } catch (error) {
            showNotice('error', humanizeError(error));
        }
    }

    async function saveCard() {
        if (!state.activeBoard) {
            return;
        }

        syncCardDraftFromDom();

        try {
            const response = await sendJson('save-card', state.cardPanel.draft);
            applyBoardPayload(response);
            const savedCardId = String(response?.saved_card_id || state.cardPanel.draft.id || '');
            state.cardPanel.dirty = false;

            if (savedCardId !== '') {
                openExistingCard(savedCardId);
            } else {
                state.cardPanel.open = false;
                render();
            }

            showNotice('success', savedCardId ? 'Tarea actualizada correctamente.' : 'Tarea creada correctamente.');
        } catch (error) {
            showNotice('error', humanizeError(error));
        }
    }

    async function deleteCard() {
        if (!state.activeBoard || !state.cardPanel.draft.id) {
            return;
        }

        if (!window.confirm('Esta tarea se eliminara del tablero. ¿Deseas continuar?')) {
            return;
        }

        try {
            const response = await sendJson('delete-card', {
                board_id: String(state.activeBoard.board.id || ''),
                card_id: String(state.cardPanel.draft.id || ''),
            });
            applyBoardPayload(response);
            state.cardPanel.open = false;
            state.cardPanel.dirty = false;
            showNotice('success', 'Tarea eliminada correctamente.');
            render();
        } catch (error) {
            showNotice('error', humanizeError(error));
        }
    }

    async function saveComment() {
        if (!state.activeBoard) {
            return;
        }

        const form = root.querySelector('[data-task-comment-form]');

        if (!(form instanceof HTMLElement)) {
            return;
        }

        const textarea = form.querySelector('[data-task-comment-input]');
        const bodyMarkdown = textarea instanceof HTMLTextAreaElement
            ? String(textarea.value || '').trim()
            : '';
        const payload = {
            board_id: String(form.dataset.boardId || ''),
            card_id: String(form.dataset.cardId || ''),
            body_markdown: bodyMarkdown,
        };

        if (payload.body_markdown === '') {
            showNotice('error', 'Escribe un comentario antes de enviarlo.');
            return;
        }

        try {
            const response = await sendJson('add-comment', payload);
            applyBoardPayload(response);
            const currentCardId = state.cardPanel.draft.id;
            if (currentCardId) {
                openExistingCard(currentCardId);
            }
            showNotice('success', 'Comentario guardado.');
        } catch (error) {
            showNotice('error', humanizeError(error));
        }
    }

    function applyBootstrap(response) {
        const rawPayload = isObject(response?.data) ? response.data : (isObject(response) ? response : {});
        const payload = normalizeBootstrap({
            project: isObject(rawPayload.project) ? rawPayload.project : state.project,
            viewer: isObject(rawPayload.viewer) ? rawPayload.viewer : state.viewer,
            boards: Array.isArray(rawPayload.boards) ? rawPayload.boards : state.boards,
            members: Array.isArray(rawPayload.members) ? rawPayload.members : state.members,
            active_board: rawPayload.active_board ?? null,
            realtime: isObject(rawPayload.realtime) ? rawPayload.realtime : state.realtime,
        });
        state.boards = payload.boards;
        state.members = payload.members;
        state.project = payload.project;
        state.viewer = payload.viewer;
        state.realtime = payload.realtime;
        state.activeBoard = payload.active_board;
        state.searchQuery = '';
        state.cardPanel.dirty = false;
        subscribeRealtime();
    }

    function applyBoardPayload(response) {
        const payload = normalizeBoardPayload(response?.data ?? response ?? {});
        state.activeBoard = payload;

        if (state.cardPanel.open && state.cardPanel.draft.id) {
            const cardId = String(state.cardPanel.draft.id || '');
            const refreshedCard = state.activeBoard.cards.find((item) => String(item.id || '') === cardId);

            if (refreshedCard) {
                state.cardPanel.draft = createCardDraft(refreshedCard);
            } else {
                state.cardPanel.open = false;
            }
        }

        subscribeRealtime();
        render();
    }

    async function reloadBootstrap(boardId = String(state.activeBoard?.board?.id || '')) {
        try {
            const response = await fetch(`${state.apiUrl}&action=bootstrap&board_id=${encodeURIComponent(boardId)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json().catch(() => ({ success: false, message: 'No fue posible interpretar la respuesta del servidor.' }));

            if (!response.ok || payload.success !== true) {
                throw new Error(String(payload.message || 'No fue posible recargar el tablero.'));
            }

            applyBootstrap(payload);
            render();
        } catch (error) {
            showNotice('error', humanizeError(error));
        }
    }

    async function sendJson(action, payload) {
        const response = await fetch(`${state.apiUrl}&action=${encodeURIComponent(action)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload ?? {}),
        });

        const parsed = await response.json().catch(() => ({ success: false, message: 'No fue posible interpretar la respuesta del servidor.' }));

        if (!response.ok || parsed.success !== true) {
            throw new Error(String(parsed.message || 'No fue posible completar la operacion.'));
        }

        return parsed;
    }

    function showNotice(type, message) {
        state.notice = {
            type,
            message: String(message || ''),
        };
        renderNotice();
    }

    function getCardsForCell(columnId, swimlaneId) {
        const query = state.searchQuery;

        return (state.activeBoard?.cards ?? [])
            .filter((card) => String(card.column_id || '') === columnId && String(card.swimlane_id || '') === swimlaneId)
            .filter((card) => matchesCardSearch(card, query))
            .sort((left, right) => Number(left.sort_order || 0) - Number(right.sort_order || 0));
    }

    function getCommentsForCard(cardId) {
        return (state.activeBoard?.comments ?? [])
            .filter((comment) => String(comment.card_id || '') === cardId)
            .sort((left, right) => String(left.created_at || '').localeCompare(String(right.created_at || '')));
    }

    function getActivityForCard(cardId) {
        return (state.activeBoard?.activity ?? [])
            .filter((item) => String(item.card_id || '') === cardId)
            .slice(0, 12);
    }

    function getAssignedMembers(card) {
        const assignedIds = Array.isArray(card.assigned_member_ids) ? card.assigned_member_ids.map((value) => String(value || '')) : [];

        return state.members.filter((member) => assignedIds.includes(member.id));
    }

    function getCardLabels(card) {
        const labelIds = Array.isArray(card.label_ids) ? card.label_ids.map((value) => String(value || '')) : [];
        return (state.activeBoard?.labels ?? []).filter((label) => labelIds.includes(String(label.id || '')));
    }

    function matchesCardSearch(card, query) {
        if (!query) {
            return true;
        }

        const base = [
            String(card.title || ''),
            String(card.description_markdown || ''),
            ...getCardLabels(card).map((label) => String(label.title || '')),
            ...getAssignedMembers(card).map((member) => String(member.label || '')),
        ].join(' ').toLowerCase();

        return base.includes(query);
    }

    function subscribeRealtime() {
        if (!realtime || !state.project.id) {
            return;
        }

        if (projectChannel) {
            realtime.removeChannel(projectChannel);
            projectChannel = null;
        }

        if (boardChannel) {
            realtime.removeChannel(boardChannel);
            boardChannel = null;
        }

        projectChannel = realtime
            .channel(`task-boards-project:${state.project.id}`)
            .on('postgres_changes', {
                event: '*',
                schema: 'public',
                table: 'task_boards',
                filter: `project_id=eq.${state.project.id}`,
            }, () => scheduleRealtimeRefresh())
            .subscribe();

        const activeBoardId = String(state.activeBoard?.board?.id || '').trim();

        if (activeBoardId === '') {
            return;
        }

        boardChannel = realtime
            .channel(`task-boards-board:${activeBoardId}`)
            .on('postgres_changes', { event: '*', schema: 'public', table: 'task_board_columns', filter: `board_id=eq.${activeBoardId}` }, () => scheduleRealtimeRefresh(true))
            .on('postgres_changes', { event: '*', schema: 'public', table: 'task_board_swimlanes', filter: `board_id=eq.${activeBoardId}` }, () => scheduleRealtimeRefresh(true))
            .on('postgres_changes', { event: '*', schema: 'public', table: 'task_board_labels', filter: `board_id=eq.${activeBoardId}` }, () => scheduleRealtimeRefresh(true))
            .on('postgres_changes', { event: '*', schema: 'public', table: 'task_board_cards', filter: `board_id=eq.${activeBoardId}` }, () => scheduleRealtimeRefresh(true))
            .on('postgres_changes', { event: '*', schema: 'public', table: 'task_board_comments', filter: `board_id=eq.${activeBoardId}` }, () => scheduleRealtimeRefresh(true))
            .on('postgres_changes', { event: '*', schema: 'public', table: 'task_board_activity', filter: `board_id=eq.${activeBoardId}` }, () => scheduleRealtimeRefresh(true))
            .subscribe();
    }

    function scheduleRealtimeRefresh(preserveBoard = false) {
        if (state.cardPanel.open && state.cardPanel.dirty) {
            showNotice('info', 'Hay cambios nuevos en el tablero. Guarda o cierra la tarea para recargar la vista.');
            return;
        }

        window.clearTimeout(refreshTimeout);
        refreshTimeout = window.setTimeout(() => {
            void reloadBootstrap(preserveBoard ? String(state.activeBoard?.board?.id || '') : '');
        }, 350);
    }

    function cleanupRealtime() {
        if (!realtime) {
            return;
        }

        if (projectChannel) {
            realtime.removeChannel(projectChannel);
        }

        if (boardChannel) {
            realtime.removeChannel(boardChannel);
        }
    }
}

function createRealtimeClient(config) {
    const url = String(config?.supabase_url || '').trim();
    const key = String(config?.supabase_key || '').trim();
    const accessToken = String(config?.access_token || '').trim();

    if (url === '' || key === '' || accessToken === '') {
        return null;
    }

    const client = createClient(url, key, {
        auth: {
            persistSession: false,
            autoRefreshToken: false,
        },
    });

    try {
        client.realtime.setAuth(accessToken);
    } catch (error) {
        console.error(error);
    }

    return client;
}

function normalizeBootstrap(raw) {
    const project = isObject(raw.project) ? raw.project : {};
    const viewer = isObject(raw.viewer) ? raw.viewer : {};
    const realtime = isObject(raw.realtime) ? raw.realtime : {};
    const boards = Array.isArray(raw.boards) ? raw.boards.map(normalizeBoardMeta).filter(Boolean) : [];
    const members = Array.isArray(raw.members) ? raw.members.map(normalizeMember).filter(Boolean) : [];
    const activeBoard = raw.active_board ? normalizeBoardPayload(raw.active_board) : null;

    return {
        project: {
            id: String(project.id || ''),
            name: String(project.name || 'Proyecto'),
            logo_url: String(project.logo_url || ''),
        },
        viewer: {
            user_id: String(viewer.user_id || ''),
            email: String(viewer.email || ''),
        },
        boards,
        members,
        active_board: activeBoard,
        realtime: {
            supabase_url: String(realtime.supabase_url || ''),
            supabase_key: String(realtime.supabase_key || ''),
            access_token: String(realtime.access_token || ''),
        },
    };
}

function normalizeBoardMeta(raw) {
    if (!isObject(raw)) {
        return null;
    }

    return {
        id: String(raw.id || ''),
        title: String(raw.title || ''),
        description: String(raw.description || ''),
        sort_order: Number(raw.sort_order || 0),
        updated_at: String(raw.updated_at || ''),
    };
}

function normalizeMember(raw) {
    if (!isObject(raw)) {
        return null;
    }

    return {
        id: String(raw.id || ''),
        user_id: String(raw.user_id || ''),
        email: String(raw.email || ''),
        role: String(raw.role || 'member'),
        label: String(raw.label || raw.email || 'Miembro'),
        is_current_user: Boolean(raw.is_current_user),
    };
}

function normalizeBoardPayload(raw) {
    const board = isObject(raw.board) ? raw.board : {};

    return {
        board: {
            id: String(board.id || ''),
            title: String(board.title || ''),
            description: String(board.description || ''),
            updated_at: String(board.updated_at || ''),
        },
        columns: Array.isArray(raw.columns) ? raw.columns.map((column) => ({
            id: String(column.id || ''),
            title: String(column.title || ''),
            accent_color: String(column.accent_color || '#1A73E8'),
            wip_limit: column.wip_limit ?? '',
            sort_order: Number(column.sort_order || 0),
        })).filter((column) => column.id !== '') : [],
        swimlanes: Array.isArray(raw.swimlanes) ? raw.swimlanes.map((swimlane) => ({
            id: String(swimlane.id || ''),
            title: String(swimlane.title || ''),
            accent_color: String(swimlane.accent_color || '#EEF3FB'),
            sort_order: Number(swimlane.sort_order || 0),
            is_default: Boolean(swimlane.is_default),
        })).filter((swimlane) => swimlane.id !== '') : [],
        labels: Array.isArray(raw.labels) ? raw.labels.map((label) => ({
            id: String(label.id || ''),
            title: String(label.title || ''),
            color: String(label.color || '#2F7CEF'),
            sort_order: Number(label.sort_order || 0),
        })).filter((label) => label.id !== '') : [],
        cards: Array.isArray(raw.cards) ? raw.cards.map((card) => ({
            id: String(card.id || ''),
            board_id: String(card.board_id || ''),
            column_id: String(card.column_id || ''),
            swimlane_id: String(card.swimlane_id || ''),
            title: String(card.title || ''),
            description_markdown: String(card.description_markdown || ''),
            priority: resolvePriority(String(card.priority || 'medium')),
            start_date: String(card.start_date || ''),
            due_date: String(card.due_date || ''),
            assigned_member_ids: Array.isArray(card.assigned_member_ids) ? card.assigned_member_ids.map((value) => String(value || '')) : [],
            label_ids: Array.isArray(card.label_ids) ? card.label_ids.map((value) => String(value || '')) : [],
            checklist: normalizeChecklist(card.checklist),
            sort_order: Number(card.sort_order || 0),
            created_by_email: String(card.created_by_email || ''),
            created_at: String(card.created_at || ''),
        })).filter((card) => card.id !== '') : [],
        comments: Array.isArray(raw.comments) ? raw.comments.map((comment) => ({
            id: String(comment.id || ''),
            board_id: String(comment.board_id || ''),
            card_id: String(comment.card_id || ''),
            body_markdown: String(comment.body_markdown || ''),
            author_email: String(comment.author_email || ''),
            created_at: String(comment.created_at || ''),
        })).filter((comment) => comment.id !== '') : [],
        activity: Array.isArray(raw.activity) ? raw.activity.map((activity) => ({
            id: String(activity.id || ''),
            board_id: String(activity.board_id || ''),
            card_id: String(activity.card_id || ''),
            description: String(activity.description || ''),
            actor_email: String(activity.actor_email || ''),
            created_at: String(activity.created_at || ''),
        })).filter((activity) => activity.id !== '') : [],
    };
}

function normalizeChecklist(rawChecklist) {
    if (!Array.isArray(rawChecklist)) {
        return [];
    }

    return rawChecklist.map((item, index) => ({
        id: String(item?.id || ensureId(`check_${index}`)),
        title: String(item?.title || '').trim(),
        is_done: Boolean(item?.is_done),
    })).filter((item) => item.title !== '');
}

function createBoardDraft(board = {}) {
    return {
        id: String(board.id || ''),
        title: String(board.title || ''),
        description: String(board.description || ''),
    };
}

function createCardDraft(card = {}) {
    return {
        id: String(card.id || ''),
        board_id: String(card.board_id || ''),
        title: String(card.title || ''),
        description_markdown: String(card.description_markdown || ''),
        column_id: String(card.column_id || ''),
        swimlane_id: String(card.swimlane_id || ''),
        priority: resolvePriority(String(card.priority || 'medium')),
        start_date: String(card.start_date || ''),
        due_date: String(card.due_date || ''),
        assigned_member_ids: Array.isArray(card.assigned_member_ids) ? card.assigned_member_ids.map((value) => String(value || '')) : [],
        label_ids: Array.isArray(card.label_ids) ? card.label_ids.map((value) => String(value || '')) : [],
        checklist: normalizeChecklist(card.checklist),
        sort_order: Number(card.sort_order || 0),
    };
}

function createStructureItem(mode) {
    return {
        id: ensureId(mode),
        title: '',
        color: mode === 'columns' ? '#1A73E8' : mode === 'swimlanes' ? '#EEF3FB' : '#2F7CEF',
        wip_limit: '',
    };
}

function createChecklistItem() {
    return {
        id: ensureId('check'),
        title: '',
        is_done: false,
    };
}

function normalizeStructureItem(item, mode) {
    return {
        id: String(item.id || ensureId(mode)),
        title: String(item.title || ''),
        color: String(item.accent_color || item.color || (mode === 'swimlanes' ? '#EEF3FB' : '#2F7CEF')),
        wip_limit: item.wip_limit === null || item.wip_limit === undefined ? '' : String(item.wip_limit),
    };
}

function countCardsByColumn(cards) {
    return cards.reduce((counts, card) => {
        const columnId = String(card.column_id || '');

        if (columnId !== '') {
            counts[columnId] = (counts[columnId] || 0) + 1;
        }

        return counts;
    }, {});
}

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.task-boards-card:not(.is-dragging)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;

        if (offset < 0 && offset > closest.offset) {
            return { offset, element: child };
        }

        return closest;
    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
}

function resolveDroppedSortOrder(list, cardId) {
    const cards = [...list.querySelectorAll('.task-boards-card')].map((card, index) => ({
        id: String(card.dataset.taskCardId || ''),
        sortOrder: Number.parseFloat(String(card.dataset.taskSortOrder || 0)) || ((index + 1) * 1024),
    }));
    const droppedIndex = cards.findIndex((card) => card.id === cardId);

    if (droppedIndex === -1) {
        return 0;
    }

    const before = cards[droppedIndex - 1];
    const after = cards[droppedIndex + 1];

    if (!before && !after) {
        return 0;
    }

    if (!before) {
        return (after.sortOrder || 0) - 1024;
    }

    if (!after) {
        return (before.sortOrder || 0) + 1024;
    }

    return ((before.sortOrder || 0) + (after.sortOrder || 0)) / 2;
}

function renderMarkdown(markdown) {
    const source = String(markdown || '').trim();

    if (source === '') {
        return '<p class="task-boards-markdown-empty">La descripcion aparecera aqui en cuanto escribas algo.</p>';
    }

    const lines = source.split('\n');
    const parts = [];
    let listItems = [];

    const flushList = () => {
        if (listItems.length === 0) {
            return;
        }

        parts.push(`<ul>${listItems.map((item) => `<li>${applyInlineMarkdown(item)}</li>`).join('')}</ul>`);
        listItems = [];
    };

    lines.forEach((line) => {
        const trimmed = line.trim();

        if (trimmed.startsWith('- ')) {
            listItems.push(trimmed.slice(2));
            return;
        }

        flushList();

        if (trimmed === '') {
            return;
        }

        if (trimmed.startsWith('### ')) {
            parts.push(`<h4>${applyInlineMarkdown(trimmed.slice(4))}</h4>`);
            return;
        }

        if (trimmed.startsWith('## ')) {
            parts.push(`<h3>${applyInlineMarkdown(trimmed.slice(3))}</h3>`);
            return;
        }

        if (trimmed.startsWith('# ')) {
            parts.push(`<h2>${applyInlineMarkdown(trimmed.slice(2))}</h2>`);
            return;
        }

        parts.push(`<p>${applyInlineMarkdown(trimmed)}</p>`);
    });

    flushList();
    return parts.join('');
}

function applyInlineMarkdown(text) {
    let html = escapeHtml(String(text || ''));

    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
    html = html.replace(/`(.+?)`/g, '<code>$1</code>');
    html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noreferrer noopener">$1</a>');

    return html;
}

function resolvePriority(priority) {
    const normalized = String(priority || '').toLowerCase().trim();
    return ['low', 'medium', 'high', 'urgent'].includes(normalized) ? normalized : 'medium';
}

function priorityLabel(priority) {
    return {
        low: 'Baja',
        medium: 'Media',
        high: 'Alta',
        urgent: 'Urgente',
    }[resolvePriority(priority)] || 'Media';
}

function isDateOverdue(value) {
    const date = String(value || '').trim();

    if (date === '') {
        return false;
    }

    const today = new Date();
    const current = new Date(`${date}T00:00:00`);
    return current.getTime() < new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime();
}

function formatDate(value) {
    const date = String(value || '').trim();

    if (date === '') {
        return '';
    }

    const formatter = new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

    return formatter.format(new Date(`${date}T00:00:00`));
}

function formatDateTime(value) {
    const normalized = String(value || '').trim();

    if (normalized === '') {
        return 'Ahora';
    }

    const formatter = new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

    return formatter.format(new Date(normalized));
}

function normalizeNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : null;
}

function humanizeError(error) {
    if (error instanceof Error && error.message) {
        return error.message;
    }

    return 'No fue posible completar la operacion.';
}

function parseJson(raw) {
    try {
        return JSON.parse(raw);
    } catch (error) {
        return {};
    }
}

function isObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function ensureId(prefix = 'id') {
    if (globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function') {
        return globalThis.crypto.randomUUID();
    }

    return `${prefix}_${Math.random().toString(16).slice(2)}${Date.now().toString(16)}`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeAttribute(value) {
    const raw = String(value ?? '');

    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(raw);
    }

    return raw.replaceAll('"', '\\"');
}

function escapeToken(value) {
    return String(value ?? '').replace(/[^a-z0-9_-]/gi, '') || 'info';
}
