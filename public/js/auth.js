async function checkAuth() {
  try {
    const data = await apiFetch('/api/auth/me');
    return data.user;
  } catch {
    return null;
  }
}

function redirectByRole(user) {
  if (!user) {
    if (!window.location.pathname.endsWith('login.html')) {
      window.location.href = 'login.html';
    }
    return;
  }
  if (window.location.pathname.endsWith('login.html')) {
    if (user.role === 'ADMIN_TEACHER') window.location.href = 'admin.html';
    else if (user.role === 'TEACHER') window.location.href = 'teacher.html';
    else if (user.role === 'STUDENT') window.location.href = 'student.html';
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  const loginForm = document.getElementById('loginForm');
  const logoutBtn = document.getElementById('logoutBtn');
  const userNameEl = document.getElementById('userName');
  const errorEl = document.getElementById('error');

  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      errorEl.hidden = true;
      const username = loginForm.username.value.trim();
      const password = loginForm.password.value;
      try {
        const data = await apiFetch('/api/auth/login', {
          method: 'POST',
          body: JSON.stringify({ username, password }),
        });
        setCsrfToken(data.csrfToken);
        sessionStorage.setItem('user', JSON.stringify(data.user));
        if (data.user.role === 'STUDENT') window.location.href = 'student.html';
        else if (data.user.role === 'ADMIN_TEACHER') window.location.href = 'admin.html';
        else window.location.href = 'teacher.html';
      } catch (err) {
        errorEl.textContent = err.message || 'Viga';
        errorEl.hidden = false;
      }
    });
  }

  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      try {
        await apiFetch('/api/auth/logout', { method: 'POST' });
      } catch {}
      sessionStorage.clear();
      window.location.href = 'login.html';
    });
  }

  if (userNameEl) {
    let user = JSON.parse(sessionStorage.getItem('user') || 'null');
    if (!user) {
      const me = await checkAuth();
      if (me) {
        user = me;
        sessionStorage.setItem('user', JSON.stringify(user));
      }
    }
    if (user) {
      userNameEl.textContent = user.name || user.username;
    } else if (!loginForm) {
      window.location.href = 'login.html';
    }
  }

  if (!loginForm) {
    const user = await checkAuth();
    if (!user) window.location.href = 'login.html';
  }
});
