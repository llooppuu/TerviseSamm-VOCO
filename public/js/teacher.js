const user = JSON.parse(sessionStorage.getItem('user') || '{}');
if (user.role && user.role !== 'TEACHER' && user.role !== 'ADMIN_TEACHER') {
  window.location.href = user.role === 'STUDENT' ? 'student.html' : 'login.html';
}

const GROUP_STORAGE_KEY = 'teacherSelectedGroup';
let activitySearchDebounce = null;
let activitiesCache = [];
let studentsCache = [];
let studentActivitySearchDebounce = null;


document.addEventListener('DOMContentLoaded', async () => {
  if (user.role === 'ADMIN_TEACHER') {
    const adminLink = document.getElementById('adminLink');
    if (adminLink) adminLink.style.display = '';
  }

  bindModalHelpers();

  const groupSelect = document.getElementById('groupSelect');
  const studentActivitySearchInput = document.getElementById('studentActivitySearchInput');
  const studentsActivityHeader = document.getElementById('studentsActivityHeader');
  const groupsData = await apiFetch('/api/teacher/groups');
  const groups = Array.isArray(groupsData.groups) ? groupsData.groups : [];

  groupSelect.innerHTML = '<option value="">-- Vali rühm --</option>';
  groups.forEach((group) => {
    const option = document.createElement('option');
    option.value = group.code;
    option.textContent = group.name || group.code;
    groupSelect.appendChild(option);
  });

  const storedGroup = sessionStorage.getItem(GROUP_STORAGE_KEY);
  if (storedGroup && groups.some((group) => group.code === storedGroup)) {
    groupSelect.value = storedGroup;
    await loadGroupOverview(storedGroup);
  }

  groupSelect.addEventListener('change', async () => {
    const code = groupSelect.value;
    sessionStorage.setItem(GROUP_STORAGE_KEY, code);
    await loadGroupOverview(code);
  });

  studentActivitySearchInput?.addEventListener('input', () => {
    window.clearTimeout(studentActivitySearchDebounce);
    studentActivitySearchDebounce = window.setTimeout(() => {
      renderStudentsTable();
    }, 180);
  });

  studentsActivityHeader?.addEventListener('click', () => {
    studentActivitySearchInput?.focus();
    studentActivitySearchInput?.select();
  });

  try {
    await initActivitySection();
  } catch (err) {
    const errorEl = document.getElementById('activityFormError');
    if (errorEl) {
      errorEl.textContent = err?.message || 'Tegevuste laadimine ebaõnnestus.';
      errorEl.hidden = false;
    }
  }
});

async function initActivitySection() {
  const form = document.getElementById('activityForm');
  const errorEl = document.getElementById('activityFormError');
  const searchInput = document.getElementById('activitySearchInput');
  const nameInput = document.getElementById('activityName');
  const teacherActivitiesHeader = document.getElementById('teacherActivitiesHeader');

  await fetchActivities();
  renderActivities(searchInput.value.trim());

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorEl.hidden = true;

    const name = (nameInput?.value || '').trim();
    if (!name) {
      errorEl.textContent = 'Tegevuse nimi on kohustuslik.';
      errorEl.hidden = false;
      return;
    }

    try {
      await apiFetch('/api/activities', {
        method: 'POST',
        body: JSON.stringify({ name }),
      });
      form.reset();
      await fetchActivities();
      renderActivities(searchInput.value.trim());
    } catch (err) {
      errorEl.textContent = err.message || 'Tegevuse lisamine ebaõnnestus.';
      errorEl.hidden = false;
    }
  });

  searchInput.addEventListener('input', () => {
    window.clearTimeout(activitySearchDebounce);
    activitySearchDebounce = window.setTimeout(() => {
      renderActivities(searchInput.value.trim());
    }, 220);
  });

  teacherActivitiesHeader?.addEventListener('click', () => {
    searchInput?.focus();
    searchInput?.select();
  });
}

async function fetchActivities() {
  const data = await apiFetch('/api/activities');
  activitiesCache = Array.isArray(data.activities) ? data.activities : [];
}

function renderActivities(search) {
  const query = (search || '').trim().toLowerCase();
  const activities = activitiesCache.filter((activity) => !query || String(activity.name || '').toLowerCase().includes(query));
  const tbody = document.querySelector('#activitiesTable tbody');
  tbody.innerHTML = '';

  if (!activities.length) {
    tbody.innerHTML = '<tr><td colspan="3" class="muted">Tegevusi ei leitud.</td></tr>';
    return;
  }

  activities.forEach((activity) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${activity.id}</td>
      <td>${escapeHtml(activity.name)}</td>
      <td>
        <button type="button" class="btn btn-small btn-danger" data-activity-id="${activity.id}" data-activity-name="${escapeHtml(activity.name)}">
          Kustuta
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  tbody.querySelectorAll('button[data-activity-id]').forEach((button) => {
    button.addEventListener('click', async () => {
      const id = Number(button.dataset.activityId || 0);
      const name = button.dataset.activityName || 'tegevus';
      if (id < 1) return;
      const ok = window.confirm(`Kustuta tegevus "${name}"?`);
      if (!ok) return;

      try {
        await apiFetch(`/api/activities/${id}`, { method: 'DELETE' });
        const currentSearch = (document.getElementById('activitySearchInput')?.value || '').trim();
        await fetchActivities();
        renderActivities(currentSearch);
      } catch (err) {
        const errorEl = document.getElementById('activityFormError');
        if (errorEl) {
          errorEl.textContent = err.message || 'Tegevuse kustutamine ebaõnnestus.';
          errorEl.hidden = false;
        }
      }
    });
  });
}

async function loadGroupOverview(code) {
  await Promise.all([
    loadSummary(code),
    loadStudents(code),
  ]);
}

async function loadSummary(code) {
  const kpiParticipation = document.getElementById('kpiParticipation');
  const kpiAvgLast = document.getElementById('kpiAvgLast');
  const kpiDelta = document.getElementById('kpiDelta');

  if (!code) {
    kpiParticipation.textContent = '–';
    kpiAvgLast.textContent = '–';
    kpiDelta.textContent = '–';
    return;
  }

  const data = await apiFetch(`/api/teacher/groups/${code}/summary?days=14`);
  kpiParticipation.textContent = `${data.participation_count} / ${data.student_count}`;
  kpiAvgLast.textContent = data.avg_pushups_last !== null && data.avg_pushups_last !== undefined
    ? Number(data.avg_pushups_last).toFixed(1)
    : '–';
  kpiDelta.textContent = data.avg_delta_pushups !== null && data.avg_delta_pushups !== undefined
    ? `${Number(data.avg_delta_pushups) >= 0 ? '+' : ''}${Number(data.avg_delta_pushups).toFixed(1)}`
    : '–';
}

async function loadStudents(code) {
  if (!code) {
    studentsCache = [];
    renderStudentsTable();
    return;
  }

  const data = await apiFetch(`/api/teacher/groups/${code}/students`);
  studentsCache = Array.isArray(data.students) ? data.students : [];
  renderStudentsTable();
}

function renderStudentsTable() {
  const tbody = document.querySelector('#studentsTable tbody');
  tbody.innerHTML = '';
  const query = (document.getElementById('studentActivitySearchInput')?.value || '').trim().toLowerCase();

  if (!studentsCache.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="muted">Selles rühmas ei ole õpilasi.</td></tr>';
    return;
  }

  const filtered = studentsCache.filter((student) => !query || String(student.last_activity_name || '').toLowerCase().includes(query));
  if (!filtered.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="muted">Selle tegevuse järgi vasteid ei leitud.</td></tr>';
    return;
  }

  filtered.forEach((student) => {
    const tr = document.createElement('tr');
    const badgeClass = student.trend_status === 'missing' ? 'red' : (student.trend_status || 'yellow');
    tr.innerHTML = `
      <td>${escapeHtml(student.name)}</td>
      <td>${student.last_entry_date ? escapeHtml(formatDate(student.last_entry_date)) : '–'}</td>
      <td class="activity-cell">${escapeHtml(student.last_activity_name || '–')}</td>
      <td><span class="badge ${badgeClass}">${escapeHtml(statusLabel(student.trend_status))}</span></td>
      <td>
        <button type="button" class="btn btn-small" data-student-id="${student.student_id}" data-student-name="${escapeHtml(student.name)}">
          Vaata 5 viimast
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  tbody.querySelectorAll('button[data-student-id]').forEach((button) => {
    button.addEventListener('click', async () => {
      await openStudentHistory(button.dataset.studentId, button.dataset.studentName);
    });
  });

  tbody.querySelectorAll('td.activity-cell').forEach((cell) => {
    cell.addEventListener('click', () => {
      const value = (cell.textContent || '').trim();
      const input = document.getElementById('studentActivitySearchInput');
      if (!input || value === '–') return;
      input.value = value;
      renderStudentsTable();
    });
  });
}

async function openStudentHistory(studentId, studentName) {
  const data = await apiFetch(`/api/teacher/students/${studentId}/recent-entries?limit=5`);
  const entries = Array.isArray(data.entries) ? data.entries : [];
  const tbody = document.querySelector('#studentHistoryTable tbody');
  tbody.innerHTML = '';

  document.getElementById('studentHistoryTitle').textContent = `${studentName} – lähiajalugu`;

  if (!entries.length) {
    tbody.innerHTML = '<tr><td colspan="4" class="muted">Sissekanded puuduvad.</td></tr>';
  } else {
    entries.forEach((entry) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(formatDate(entry.entry_date))}</td>
        <td>${escapeHtml(entry.activity_name || '–')}</td>
        <td>${entry.pushups ?? '–'}</td>
        <td>${entry.weight_kg ?? '–'}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  openModal('studentHistoryModal');
}

function statusLabel(status) {
  const labels = {
    green: 'Paraneb',
    yellow: 'Stabiilne',
    red: 'Langeb',
    missing: 'Puudub > 14p',
  };
  return labels[status] || 'Stabiilne';
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

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value;
  return div.innerHTML;
}
