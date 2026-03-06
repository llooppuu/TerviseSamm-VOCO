const user = JSON.parse(sessionStorage.getItem('user') || '{}');
if (user.role && user.role !== 'ADMIN_TEACHER') {
  window.location.href = user.role === 'STUDENT' ? 'student.html' : user.role === 'TEACHER' ? 'teacher.html' : 'login.html';
}

let teachers = [];
let groups = [];
let students = [];
let currentTeacher = null;
let currentGroup = null;

document.addEventListener('DOMContentLoaded', async () => {
  bindModalHelpers();
  bindActions();
  await refreshAdminData();
});

function bindActions() {
  document.getElementById('createTeacherBtn').addEventListener('click', () => {
    document.getElementById('teacherForm').reset();
    openModal('teacherModal');
  });

  document.getElementById('createGroupBtn').addEventListener('click', () => {
    resetGroupForm();
    openModal('groupModal');
  });

  document.getElementById('teacherForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;

    try {
      const data = await apiFetch('/api/admin/teachers', {
        method: 'POST',
        body: JSON.stringify({
          name: form.name.value.trim(),
          username: form.username.value.trim(),
          temp_password: form.temp_password.value,
        }),
      });

      closeModal('teacherModal');
      form.reset();
      showNotice({
        title: 'Õpetaja loodud',
        body: `${data.teacher.name} konto loodi. Ajutine parool: ${data.teacher.temp_password}`,
      });
      await refreshAdminData();
    } catch (err) {
      window.alert(err.message || 'Õpetaja loomine ebaõnnestus.');
    }
  });

  document.getElementById('groupForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const groupId = Number(form.group_id.value || 0);
    const payload = {
      code: form.code.value.trim().toUpperCase(),
      name: form.name.value.trim() || undefined,
      is_active: form.is_active.checked,
    };

    try {
      if (groupId > 0) {
        await apiFetch(`/api/admin/groups/${groupId}`, {
          method: 'PATCH',
          body: JSON.stringify({
            name: payload.name || null,
            is_active: payload.is_active,
          }),
        });
        showNotice({
          title: 'Rühm uuendatud',
          body: `Rühma ${payload.code} andmed salvestati.`,
        });
      } else {
        const data = await apiFetch('/api/admin/groups', {
          method: 'POST',
          body: JSON.stringify({
            code: payload.code,
            name: payload.name,
          }),
        });
        if (!payload.is_active) {
          await apiFetch(`/api/admin/groups/${data.group.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ is_active: false }),
          });
        }
        showNotice({
          title: 'Rühm loodud',
          body: `Rühm ${data.group.code} lisati haldusesse.`,
        });
      }

      closeModal('groupModal');
      resetGroupForm();
      await refreshAdminData();
    } catch (err) {
      window.alert(err.message || 'Rühma salvestamine ebaõnnestus.');
    }
  });

  document.getElementById('saveGroupStudentsBtn').addEventListener('click', async () => {
    if (!currentGroup) return;

    const checkboxes = [...document.querySelectorAll('#groupStudentsCheckboxes input[type="checkbox"]')];
    const selectedIds = new Set(checkboxes.filter((checkbox) => checkbox.checked).map((checkbox) => Number(checkbox.dataset.studentId)));
    const existingIds = new Set((currentGroup.students || []).map((student) => Number(student.id)));

    const toAdd = [...selectedIds].filter((id) => !existingIds.has(id));
    const toRemove = [...existingIds].filter((id) => !selectedIds.has(id));

    try {
      for (const studentId of toAdd) {
        await apiFetch(`/api/admin/groups/${currentGroup.id}/students/add`, {
          method: 'POST',
          body: JSON.stringify({ student_user_id: studentId }),
        });
      }
      for (const studentId of toRemove) {
        await apiFetch(`/api/admin/groups/${currentGroup.id}/students/remove`, {
          method: 'POST',
          body: JSON.stringify({ student_user_id: studentId }),
        });
      }

      closeModal('groupStudentsModal');
      showNotice({
        title: 'Õpilased salvestatud',
        body: `Rühma ${currentGroup.code} koosseis uuendati.`,
      });
      await refreshAdminData();
    } catch (err) {
      window.alert(err.message || 'Õpilaste salvestamine ebaõnnestus.');
    }
  });
}

async function refreshAdminData() {
  const [teachersData, groupsData, studentsData] = await Promise.all([
    apiFetch('/api/admin/teachers'),
    apiFetch('/api/admin/groups'),
    apiFetch('/api/admin/students'),
  ]);

  teachers = Array.isArray(teachersData.teachers) ? teachersData.teachers : [];
  groups = Array.isArray(groupsData.groups) ? groupsData.groups : [];
  students = Array.isArray(studentsData.students) ? studentsData.students : [];

  renderTeachers();
  renderGroups();
}

function renderTeachers() {
  const tbody = document.querySelector('#teachersTable tbody');
  tbody.innerHTML = '';

  teachers.forEach((teacher) => {
    const tr = document.createElement('tr');
    const accessChips = teacher.role === 'ADMIN_TEACHER'
      ? '<span class="chip"><strong>Kõik rühmad</strong></span>'
      : (teacher.access?.length
          ? teacher.access.map((group) => `<span class="chip">${escapeHtml(group.code)}</span>`).join('')
          : '<span class="muted">Ligipääse pole</span>');

    const roleBadgeClass = teacher.role === 'ADMIN_TEACHER' ? 'blue' : 'gray';
    const roleLabel = teacher.role === 'ADMIN_TEACHER' ? 'Boss-õpetaja' : 'Õpetaja';
    const statusBadgeClass = teacher.is_active ? 'green' : 'red';
    const statusLabel = teacher.is_active ? 'Aktiivne' : 'Mitteaktiivne';
    const actions = teacher.role === 'ADMIN_TEACHER'
      ? '<span class="muted">Põhikonto</span>'
      : `
        <div class="actions">
          <button type="button" class="btn btn-small" data-action="access" data-teacher-id="${teacher.id}">Ligipääsud</button>
          <button type="button" class="btn btn-small" data-action="toggle" data-teacher-id="${teacher.id}">${teacher.is_active ? 'Deaktiveeri' : 'Aktiveeri'}</button>
          <button type="button" class="btn btn-small btn-secondary" data-action="reset-password" data-teacher-id="${teacher.id}">Taasta parool</button>
        </div>
      `;

    tr.innerHTML = `
      <td>${escapeHtml(teacher.name)}</td>
      <td>${escapeHtml(teacher.username)}</td>
      <td><span class="badge ${roleBadgeClass}">${roleLabel}</span></td>
      <td><span class="badge ${statusBadgeClass}">${statusLabel}</span></td>
      <td><div class="chip-list">${accessChips}</div></td>
      <td>${actions}</td>
    `;

    tbody.appendChild(tr);
  });

  tbody.querySelectorAll('button[data-action]').forEach((button) => {
    button.addEventListener('click', async () => {
      const teacherId = Number(button.dataset.teacherId || 0);
      const action = button.dataset.action;
      if (action === 'access') {
        await openAccessModal(teacherId);
      } else if (action === 'toggle') {
        await toggleTeacherStatus(teacherId);
      } else if (action === 'reset-password') {
        await resetTeacherPassword(teacherId);
      }
    });
  });
}

function renderGroups() {
  const tbody = document.querySelector('#groupsTable tbody');
  tbody.innerHTML = '';

  groups.forEach((group) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${escapeHtml(group.code)}</td>
      <td>${escapeHtml(group.name || group.code)}</td>
      <td><span class="badge ${group.is_active ? 'green' : 'red'}">${group.is_active ? 'Aktiivne' : 'Mitteaktiivne'}</span></td>
      <td>${Array.isArray(group.students) ? group.students.length : 0}</td>
      <td>
        <div class="actions">
          <button type="button" class="btn btn-small" data-action="edit-group" data-group-id="${group.id}">Muuda</button>
          <button type="button" class="btn btn-small btn-secondary" data-action="manage-students" data-group-id="${group.id}">Õpilased</button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });

  tbody.querySelectorAll('button[data-action]').forEach((button) => {
    button.addEventListener('click', async () => {
      const groupId = Number(button.dataset.groupId || 0);
      const action = button.dataset.action;
      if (action === 'edit-group') {
        openGroupModal(groupId);
      } else if (action === 'manage-students') {
        openGroupStudentsModal(groupId);
      }
    });
  });
}

async function openAccessModal(teacherId) {
  currentTeacher = teachers.find((teacher) => teacher.id === teacherId) || null;
  if (!currentTeacher) return;

  const accessData = await apiFetch(`/api/admin/access?teacher_id=${teacherId}`);
  const selectedIds = new Set((accessData.access || []).map((group) => Number(group.id)));
  const container = document.getElementById('accessCheckboxes');

  document.getElementById('accessTeacherName').textContent = `${currentTeacher.name} (${currentTeacher.username})`;
  container.innerHTML = '';

  groups.forEach((group) => {
    const label = document.createElement('label');
    label.className = 'checkbox-card';
    label.innerHTML = `
      <input type="checkbox" data-group-id="${group.id}" ${selectedIds.has(Number(group.id)) ? 'checked' : ''}>
      <div>
        <strong>${escapeHtml(group.code)}</strong>
        <span>${escapeHtml(group.name || group.code)}</span>
      </div>
    `;

    const checkbox = label.querySelector('input');
    checkbox.addEventListener('change', async () => {
      try {
        await apiFetch(`/api/admin/access/${checkbox.checked ? 'grant' : 'revoke'}`, {
          method: 'POST',
          body: JSON.stringify({
            teacher_id: currentTeacher.id,
            group_id: Number(checkbox.dataset.groupId),
          }),
        });
        await refreshAdminData();
      } catch (err) {
        checkbox.checked = !checkbox.checked;
        window.alert(err.message || 'Ligipääsu muutmine ebaõnnestus.');
      }
    });

    container.appendChild(label);
  });

  openModal('accessModal');
}

async function toggleTeacherStatus(teacherId) {
  const teacher = teachers.find((item) => item.id === teacherId);
  if (!teacher) return;

  try {
    await apiFetch(`/api/admin/teachers/${teacherId}`, {
      method: 'PATCH',
      body: JSON.stringify({ is_active: !teacher.is_active }),
    });
    showNotice({
      title: teacher.is_active ? 'Õpetaja deaktiveeritud' : 'Õpetaja aktiveeritud',
      body: `${teacher.name} staatus muudeti.`,
    });
    await refreshAdminData();
  } catch (err) {
    window.alert(err.message || 'Õpetaja staatuse muutmine ebaõnnestus.');
  }
}

async function resetTeacherPassword(teacherId) {
  const teacher = teachers.find((item) => item.id === teacherId);
  if (!teacher) return;

  if (!window.confirm(`Genereeri ${teacher.name} jaoks uus ajutine parool?`)) return;

  try {
    const data = await apiFetch(`/api/admin/teachers/${teacherId}/reset-password`, {
      method: 'POST',
    });
    showNotice({
      title: 'Ajutine parool loodud',
      body: `${teacher.name} uus ajutine parool: ${data.temp_password}`,
    });
  } catch (err) {
    window.alert(err.message || 'Parooli taastamine ebaõnnestus.');
  }
}

function openGroupModal(groupId) {
  const group = groups.find((item) => item.id === groupId);
  if (!group) return;

  const form = document.getElementById('groupForm');
  form.group_id.value = group.id;
  form.code.value = group.code;
  form.code.disabled = true;
  form.name.value = group.name || '';
  form.is_active.checked = Boolean(group.is_active);
  document.getElementById('groupModalTitle').textContent = `Muuda rühma ${group.code}`;
  openModal('groupModal');
}

function resetGroupForm() {
  const form = document.getElementById('groupForm');
  form.reset();
  form.group_id.value = '';
  form.code.disabled = false;
  form.is_active.checked = true;
  document.getElementById('groupModalTitle').textContent = 'Lisa rühm';
}

function openGroupStudentsModal(groupId) {
  currentGroup = groups.find((group) => group.id === groupId) || null;
  if (!currentGroup) return;

  const selectedContainer = document.getElementById('groupStudentsSelected');
  const checkboxContainer = document.getElementById('groupStudentsCheckboxes');
  const selectedIds = new Set((currentGroup.students || []).map((student) => Number(student.id)));

  document.getElementById('groupStudentsTitle').textContent = `Rühma ${currentGroup.code} õpilased`;
  selectedContainer.innerHTML = '';
  checkboxContainer.innerHTML = '';

  if (selectedIds.size) {
    (currentGroup.students || []).forEach((student) => {
      const chip = document.createElement('span');
      chip.className = 'chip';
      chip.textContent = student.name;
      selectedContainer.appendChild(chip);
    });
  } else {
    selectedContainer.innerHTML = '<span class="muted">Õpilasi veel ei ole.</span>';
  }

  students.forEach((student) => {
    const label = document.createElement('label');
    label.className = 'checkbox-card';
    label.innerHTML = `
      <input type="checkbox" data-student-id="${student.id}" ${selectedIds.has(Number(student.id)) ? 'checked' : ''}>
      <div>
        <strong>${escapeHtml(student.name)}</strong>
        <span>${escapeHtml(student.username)}</span>
      </div>
    `;
    checkboxContainer.appendChild(label);
  });

  openModal('groupStudentsModal');
}

function showNotice({ title, body, type = 'info' }) {
  const container = document.getElementById('adminNotice');
  container.hidden = false;
  container.className = `card card-full notice-card${type === 'error' ? ' error-notice' : ''}`;
  container.innerHTML = `
    <h3>${escapeHtml(title)}</h3>
    <p>${escapeHtml(body)}</p>
  `;
  container.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function bindModalHelpers() {
  document.querySelectorAll('[data-close]').forEach((button) => {
    button.addEventListener('click', () => {
      closeModal(button.dataset.close);
      if (button.dataset.close === 'groupModal') resetGroupForm();
    });
  });

  document.querySelectorAll('.modal').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        modal.hidden = true;
        if (modal.id === 'groupModal') resetGroupForm();
      }
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.modal').forEach((modal) => {
      if (!modal.hidden) {
        modal.hidden = true;
        if (modal.id === 'groupModal') resetGroupForm();
      }
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

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value;
  return div.innerHTML;
}
