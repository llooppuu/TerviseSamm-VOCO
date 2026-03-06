const user = JSON.parse(sessionStorage.getItem('user') || '{}');
if (user.role && user.role !== 'STUDENT') {
  window.location.href = user.role === 'ADMIN_TEACHER' ? 'admin.html' : user.role === 'TEACHER' ? 'teacher.html' : 'login.html';
}

let pushupsChart = null;
let weightChart = null;
let entriesCache = [];

document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('entryForm');
  const editForm = document.getElementById('entryEditForm');
  const entryError = document.getElementById('entryError');
  const entryEditError = document.getElementById('entryEditError');

  bindModalHelpers();
  setDefaultEntryDate();

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    entryError.hidden = true;

    const payload = readEntryForm(form, true);
    if (!hasAnyMetric(payload)) {
      entryError.textContent = 'Vähemalt üks mõõdik peab olema täidetud.';
      entryError.hidden = false;
      return;
    }

    try {
      const data = await apiFetch('/api/student/entries', {
        method: 'POST',
        body: JSON.stringify(payload),
      });

      updateFeedbackPanel(data.feedback?.text || 'Tagasiside puudub');
      form.reset();
      setDefaultEntryDate();
      await refreshStudentView();
    } catch (err) {
      entryError.textContent = err.message || 'Sissekande salvestamine ebaõnnestus.';
      entryError.hidden = false;
    }
  });

  editForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    entryEditError.hidden = true;

    const entryId = Number(editForm.entry_id.value || 0);
    const payload = readEntryForm(editForm, false);
    if (!hasAnyMetric(payload)) {
      entryEditError.textContent = 'Vähemalt üks mõõdik peab olema täidetud.';
      entryEditError.hidden = false;
      return;
    }

    try {
      const data = await apiFetch(`/api/student/entries/${entryId}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
      });

      closeModal('entryModal');
      updateFeedbackPanel(data.feedback?.text || 'Tagasiside puudub');
      await refreshStudentView();
    } catch (err) {
      entryEditError.textContent = err.message || 'Sissekande muutmine ebaõnnestus.';
      entryEditError.hidden = false;
    }
  });

  await refreshStudentView();
});

async function refreshStudentView() {
  await loadEntries();
  await initCharts();
}

function readEntryForm(form, includeDate) {
  const dateValue = includeDate ? form.querySelector('[name="entry_date"]').value : null;
  const weightInput = form.querySelector('[name="weight_kg"]');
  const pushupsInput = form.querySelector('[name="pushups"]');
  const noteInput = form.querySelector('[name="note"]');

  return {
    ...(includeDate ? { entry_date: dateValue } : {}),
    weight_kg: weightInput.value === '' ? null : parseFloat(weightInput.value),
    pushups: pushupsInput.value === '' ? null : parseInt(pushupsInput.value, 10),
    note: noteInput.value.trim() || null,
  };
}

function hasAnyMetric(payload) {
  return payload.weight_kg !== null || payload.pushups !== null || Boolean(payload.note);
}

function setDefaultEntryDate() {
  const input = document.querySelector('#entryForm [name="entry_date"]');
  if (input) input.value = new Date().toISOString().slice(0, 10);
}

async function loadEntries() {
  const to = new Date();
  const from = new Date();
  from.setDate(from.getDate() - 90);
  const fromStr = from.toISOString().slice(0, 10);
  const toStr = to.toISOString().slice(0, 10);

  const data = await apiFetch(`/api/student/entries?from=${fromStr}&to=${toStr}`);
  entriesCache = Array.isArray(data.entries) ? data.entries : [];

  const tbody = document.querySelector('#entriesTable tbody');
  tbody.innerHTML = '';

  if (!entriesCache.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="muted">Sissekandeid veel ei ole.</td></tr>';
    return;
  }

  for (const entry of entriesCache) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${escapeHtml(formatDate(entry.entry_date))}</td>
      <td>${escapeHtml(formatValue(entry.weight_kg))}</td>
      <td>${escapeHtml(formatValue(entry.pushups))}</td>
      <td>${escapeHtml(truncate(entry.note || '–', 56))}</td>
      <td>
        <div class="actions">
          <button type="button" class="btn btn-small" data-action="edit" data-id="${entry.id}">Muuda</button>
          <button type="button" class="btn btn-small" data-action="feedback" data-id="${entry.id}">Tagasiside</button>
          <button type="button" class="btn btn-small btn-danger" data-action="delete" data-id="${entry.id}">Kustuta</button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  }

  tbody.querySelectorAll('button[data-action]').forEach((button) => {
    button.addEventListener('click', async () => {
      const entryId = Number(button.dataset.id || 0);
      const action = button.dataset.action;
      if (action === 'edit') {
        openEditModal(entryId);
      } else if (action === 'feedback') {
        await openFeedbackModal(entryId);
      } else if (action === 'delete') {
        await deleteEntry(entryId);
      }
    });
  });
}

function openEditModal(entryId) {
  const entry = entriesCache.find((item) => Number(item.id) === Number(entryId));
  if (!entry) return;

  const form = document.getElementById('entryEditForm');
  const error = document.getElementById('entryEditError');
  if (error) error.hidden = true;

  form.entry_id.value = entry.id;
  form.weight_kg.value = entry.weight_kg ?? '';
  form.pushups.value = entry.pushups ?? '';
  form.note.value = entry.note ?? '';
  document.getElementById('entryModalDate').textContent = formatDate(entry.entry_date);
  openModal('entryModal');
}

async function openFeedbackModal(entryId) {
  const entry = entriesCache.find((item) => Number(item.id) === Number(entryId));
  if (!entry) return;

  let feedbackText = entry.feedback?.text || '';
  if (!feedbackText) {
    const data = await apiFetch(`/api/student/entries/${entryId}/feedback`);
    feedbackText = data.feedback?.text || '';
  }

  const finalText = feedbackText || 'Tagasiside puudub.';
  document.getElementById('feedbackModalDate').textContent = formatDate(entry.entry_date);
  document.getElementById('feedbackModalText').textContent = finalText;
  updateFeedbackPanel(finalText);
  openModal('feedbackModal');
}

async function deleteEntry(entryId) {
  const entry = entriesCache.find((item) => Number(item.id) === Number(entryId));
  const label = entry ? `Kustuta ${formatDate(entry.entry_date)} sissekanne?` : 'Kustuta sissekanne?';
  if (!window.confirm(label)) return;

  try {
    await apiFetch(`/api/student/entries/${entryId}`, { method: 'DELETE' });
    await refreshStudentView();
  } catch (err) {
    window.alert(err.message || 'Sissekande kustutamine ebaõnnestus.');
  }
}

function updateFeedbackPanel(text) {
  const feedbackBox = document.getElementById('feedbackBox');
  feedbackBox.textContent = text;
  feedbackBox.classList.toggle('muted', !text || text === 'Tagasiside puudub');
}

function bindModalHelpers() {
  document.querySelectorAll('[data-close]').forEach((button) => {
    button.addEventListener('click', () => closeModal(button.dataset.close));
  });

  document.querySelectorAll('.modal').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) modal.hidden = true;
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.modal').forEach((modal) => {
      if (!modal.hidden) modal.hidden = true;
    });
  });
}

function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.hidden = false;
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.hidden = true;
}

function formatDate(value) {
  if (!value) return '–';
  const [year, month, day] = value.split('-');
  return `${day}.${month}.${year}`;
}

function formatValue(value) {
  return value === null || value === undefined || value === '' ? '–' : String(value);
}

function truncate(value, limit) {
  return value.length > limit ? `${value.slice(0, limit - 1)}…` : value;
}

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value;
  return div.innerHTML;
}

async function initCharts() {
  const pushupsCtx = document.getElementById('pushupsChart')?.getContext('2d');
  const weightCtx = document.getElementById('weightChart')?.getContext('2d');

  const pushupsEntries = entriesCache
    .filter((entry) => entry.pushups !== null)
    .slice()
    .sort((a, b) => a.entry_date.localeCompare(b.entry_date));

  const weightEntries = entriesCache
    .filter((entry) => entry.weight_kg !== null)
    .slice()
    .sort((a, b) => a.entry_date.localeCompare(b.entry_date));

  if (pushupsCtx) {
    if (pushupsChart) pushupsChart.destroy();
    pushupsChart = createLineChart(pushupsCtx, {
      labels: pushupsEntries.map((entry) => formatDate(entry.entry_date)),
      values: pushupsEntries.map((entry) => entry.pushups),
      label: 'Kätekõverdused',
      color: '#20C4F4',
    });
  }

  if (weightCtx) {
    if (weightChart) weightChart.destroy();
    weightChart = createLineChart(weightCtx, {
      labels: weightEntries.map((entry) => formatDate(entry.entry_date)),
      values: weightEntries.map((entry) => entry.weight_kg),
      label: 'Kaal (kg)',
      color: '#0DB14B',
    });
  }
}
