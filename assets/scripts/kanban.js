/**
 * Renders the public Nextcloud Deck tasks as a little kanban board.
 *
 * Data comes from /api/kanbantasks.php, which already filters server-side
 * for the "Public [👁️]" tag. Everything here renders via textContent, so
 * task content is never interpreted as HTML.
 */
(function () {
  'use strict';

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (text !== undefined && text !== null) {
      node.textContent = text;
    }
    return node;
  }

  function renderLabels(card) {
    if (!Array.isArray(card.labels) || card.labels.length === 0) {
      return null;
    }
    const container = el('div', 'kanban-labels');
    for (const label of card.labels) {
      if (!label || typeof label !== 'object') {
        continue;
      }
      const chip = el('span', 'kanban-label', label.title || '');
      if (typeof label.color === 'string' && /^[0-9a-f]{6}$/i.test(label.color)) {
        chip.style.backgroundColor = '#' + label.color;
        chip.classList.add('kanban-label-filled');
      }
      container.appendChild(chip);
    }
    return container;
  }

  function renderCard(card) {
    const article = el('article', 'kanban-card');
    article.appendChild(el('h4', 'kanban-card-title', card.title || '(untitled)'));

    if (card.description) {
      article.appendChild(el('p', 'kanban-card-desc', card.description));
    }

    const labels = renderLabels(card);
    if (labels) {
      article.appendChild(labels);
    }

    if (card.duedate) {
      const due = new Date(card.duedate);
      if (!isNaN(due.getTime())) {
        const span = el('span', 'kanban-due', 'Due: ' + due.toLocaleDateString());
        if (due.getTime() < Date.now()) {
          span.classList.add('kanban-due-overdue');
        }
        article.appendChild(span);
      }
    }

    return article;
  }

  function renderColumn(stack) {
    const column = el('div', 'kanban-column');
    column.appendChild(el('h3', 'kanban-column-title', stack.title || '(untitled)'));

    const cards = Array.isArray(stack.cards) ? stack.cards : [];
    if (cards.length === 0) {
      column.appendChild(el('p', 'kanban-empty', 'No tasks'));
    }
    for (const card of cards) {
      column.appendChild(renderCard(card));
    }
    return column;
  }

  async function loadKanban() {
    const board = document.getElementById('kanban-board');
    const status = document.getElementById('kanban-status');

    try {
      const response = await fetch('/api/kanbantasks.php');
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }

      const data = await response.json();
      if (!data || typeof data !== 'object' || !Array.isArray(data.stacks)) {
        throw new Error('Unexpected response shape');
      }

      if (status) {
        status.remove();
      }

      if (data.generated_at && !isNaN(new Date(data.generated_at).getTime())) {
        board.appendChild(el('p', 'kanban-meta', 'Last updated: ' + new Date(data.generated_at).toLocaleString()));
      }

      if (data.stacks.length === 0) {
        board.appendChild(el('p', 'kanban-empty', 'No tasks yet.'));
        return;
      }

      for (const stack of data.stacks) {
        board.appendChild(renderColumn(stack));
      }
    } catch (err) {
      if (status) {
        status.textContent = 'Could not load tasks (' + err.message + ').';
        status.classList.add('kanban-error');
      }
    }
  }

  document.addEventListener('DOMContentLoaded', loadKanban);
})();
