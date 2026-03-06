const user = JSON.parse(sessionStorage.getItem('user') || '{}');
if (user.role && user.role !== 'TEACHER' && user.role !== 'ADMIN_TEACHER') {
  window.location.href = user.role === 'STUDENT' ? 'student.html' : 'login.html';
}

const GROUP_STORAGE_KEY = 'teacherSelectedGroup';

document.addEventListener('DOMContentLoaded', async () => {
  if (user.role === 'ADMIN_TEACHER') {
    const adminLink = document.getElementById('adminLink');
    if (adminLink) adminLink.style.display = '';
  }

  bindModalHelpers();

  const groupSelect = document.getElementById('groupSelect');
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
});

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
  const tbody = document.querySelector('#studentsTable tbody');
  tbody.innerHTML = '';

  if (!code) {
    tbody.innerHTML = '<tr><td colspan="5" class="muted">Vali rühm, et näha õpilasi.</td></tr>';
    return;
  }

  const data = await apiFetch(`/api/teacher/groups/${code}/students`);
  const students = Array.isArray(data.students) ? data.students : [];

  if (!students.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="muted">Selles rühmas ei ole õpilasi.</td></tr>';
    return;
  }

  students.forEach((student) => {
    const tr = document.createElement('tr');
    const badgeClass = student.trend_status === 'missing' ? 'red' : (student.trend_status || 'yellow');
    tr.innerHTML = `
      <td>${escapeHtml(student.name)}</td>
      <td>${student.last_entry_date ? escapeHtml(formatDate(student.last_entry_date)) : '–'}</td>
      <td>${student.last_pushups ?? '–'}</td>
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
}

async function openStudentHistory(studentId, studentName) {
  const data = await apiFetch(`/api/teacher/students/${studentId}/recent-entries?limit=5`);
  const entries = Array.isArray(data.entries) ? data.entries : [];
  const tbody = document.querySelector('#studentHistoryTable tbody');
  tbody.innerHTML = '';

  document.getElementById('studentHistoryTitle').textContent = `${studentName} – lähiajalugu`;

  if (!entries.length) {
    tbody.innerHTML = '<tr><td colspan="3" class="muted">Sissekanded puuduvad.</td></tr>';
  } else {
    entries.forEach((entry) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(formatDate(entry.entry_date))}</td>
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
