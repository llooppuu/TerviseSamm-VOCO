const API_BASE = '';

let csrfToken = sessionStorage.getItem('csrfToken') || '';

function getCsrfToken() {
  return csrfToken;
}

function setCsrfToken(token) {
  csrfToken = token || '';
  if (token) sessionStorage.setItem('csrfToken', token);
}

async function apiFetch(path, options = {}) {
  const url = API_BASE + path;
  const headers = {
    'Content-Type': 'application/json',
    ...options.headers,
  };
  if (csrfToken && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method || 'GET')) {
    headers['X-CSRF-Token'] = csrfToken;
  }
  const res = await fetch(url, {
    ...options,
    headers,
    credentials: 'same-origin',
  });

  if (res.status === 401) {
    sessionStorage.removeItem('csrfToken');
    sessionStorage.removeItem('user');
    if (!window.location.pathname.endsWith('login.html')) {
      window.location.href = 'login.html';
    }
    throw new Error('Unauthorized');
  }

  const text = await res.text();
  let data;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    throw new Error('Invalid JSON response');
  }

  if (!res.ok) {
    const msg = data?.error?.message || res.statusText || 'Viga';
    const err = new Error(msg);
    err.status = res.status;
    err.code = data?.error?.code;
    err.data = data;
    throw err;
  }

  if (data?.csrfToken) {
    setCsrfToken(data.csrfToken);
  }

  return data;
}
