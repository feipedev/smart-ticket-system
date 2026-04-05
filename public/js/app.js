/**
 * Vanilla JS App - Smart Ticket System
 * Gerencia UI State, JWT Auth e integrações com API.
 */

// ── CONSTANTES & ESTADO ──────────────────────────────────────────
const API_URL = '/projeto/php-ticket-system/public/api';
let authToken = localStorage.getItem('ticket_jwt') || null;
let currentUser = null;

// ── ELEMENTOS DA UI ──────────────────────────────────────────────
const views = {
  auth: document.getElementById('auth-view'),
  dashboard: document.getElementById('dashboard-view')
};

// Auth Elements
const authForm = document.getElementById('auth-form');
const btnToggleAuth = document.getElementById('btn-toggle-auth');
const groupName = document.getElementById('group-name');
const btnSubmit = document.getElementById('btn-submit');
const inputName = document.getElementById('name');
const inputEmail = document.getElementById('email');
const inputPassword = document.getElementById('password');

// Dashboard Elements
const userNameDisplay = document.getElementById('user-name-display');
const userAvatarDisplay = document.getElementById('user-avatar-display');
const btnLogout = document.getElementById('btn-logout');
const ticketList = document.getElementById('ticket-list');
const ticketForm = document.getElementById('ticket-form');
const btnRefresh = document.getElementById('btn-refresh');

// Modal Elements
const ticketModal = document.getElementById('ticket-modal');
const modalForm = document.getElementById('modal-form');
const btnCloseModal = document.getElementById('btn-close-modal');

let isLoginMode = true;

// ── INICIALIZAÇÃO ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initApp();
});

async function initApp() {
  if (authToken) {
    const valid = await fetchCurrentUser();
    if (valid) {
      showDashboard();
      fetchTickets();
    } else {
      logout();
    }
  } else {
    showAuth();
  }
}

// ── EVENT LISTENERS (AUTH) ───────────────────────────────────────
btnToggleAuth.addEventListener('click', () => {
  isLoginMode = !isLoginMode;
  if (isLoginMode) {
    groupName.classList.add('hidden');
    inputName.removeAttribute('required');
    btnSubmit.innerText = 'Entrar no Sistema';
    btnToggleAuth.innerText = 'Não tem uma conta? Cadastre-se';
  } else {
    groupName.classList.remove('hidden');
    inputName.setAttribute('required', 'true');
    btnSubmit.innerText = 'Criar Conta';
    btnToggleAuth.innerText = 'Já tem uma conta? Faça Login';
  }
});

authForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const payload = {
    email: inputEmail.value,
    password: inputPassword.value
  };

  if (!isLoginMode) {
    payload.name = inputName.value;
  }

  const endpoint = isLoginMode ? '/auth/login' : '/auth/register';
  
  try {
    const btnText = btnSubmit.innerText;
    btnSubmit.innerText = 'Aguarde...';
    btnSubmit.disabled = true;

    const res = await fetch(`${API_URL}${endpoint}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    
    if (data.error) {
      showToast(Array.isArray(data.messages) ? data.messages[0] : data.message, 'error');
    } else {
      showToast(data.message, 'success');
      
      if (data.token) {
        authToken = data.token;
        localStorage.setItem('ticket_jwt', authToken);
        if (data.user) currentUser = data.user;
        
        inputEmail.value = '';
        inputPassword.value = '';
        
        await fetchCurrentUser(); // Pega dados caso não tenha vindo
        showDashboard();
        fetchTickets();
      }
    }
  } catch (err) {
    showToast('Erro de conexão com o servidor.', 'error');
  } finally {
    btnSubmit.disabled = false;
    btnSubmit.innerText = isLoginMode ? 'Entrar no Sistema' : 'Criar Conta';
  }
});

// ── EVENT LISTENERS (DASHBOARD) ──────────────────────────────────
btnLogout.addEventListener('click', logout);
btnRefresh.addEventListener('click', fetchTickets);

ticketForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const title = document.getElementById('ticket-title').value;
  const desc = document.getElementById('ticket-desc').value;
  const priority = document.getElementById('ticket-priority').value;

  try {
    const res = await fetch(`${API_URL}/tickets`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authToken}`
      },
      body: JSON.stringify({
        title,
        description: desc,
        priority
      })
    });

    const data = await res.json();
    
    if (data.error) {
      showToast(Array.isArray(data.messages) ? data.messages[0] : data.message, 'error');
    } else {
      showToast('O.S. Criada com sucesso!', 'success');
      ticketForm.reset();
      fetchTickets();
    }
  } catch (err) {
    showToast('Erro ao criar chamado.', 'error');
  }
});

// ── EVENT LISTENERS (MODAL) ──────────────────────────────────────
btnCloseModal.addEventListener('click', () => {
  ticketModal.classList.remove('active');
});

modalForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = document.getElementById('modal-ticket-id').value;
  const status = document.getElementById('modal-status').value;
  const priority = document.getElementById('modal-priority').value;

  try {
    const res = await fetch(`${API_URL}/tickets/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authToken}`
      },
      body: JSON.stringify({ status, priority })
    });

    const data = await res.json();
    if (data.error) {
      showToast(Array.isArray(data.messages) ? data.messages[0] : data.message, 'error');
    } else {
      showToast('O.S. atualizada com sucesso!', 'success');
      ticketModal.classList.remove('active');
      fetchTickets();
    }
  } catch (err) {
    showToast('Erro ao atualizar chamado.', 'error');
  }
});

// ── INTEGRAÇÕES API & UI ─────────────────────────────────────────

async function fetchCurrentUser() {
  if (!authToken) return false;
  try {
    const res = await fetch(`${API_URL}/auth/me`, {
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    
    // Se o token for inválido, cai no 401
    if (res.status === 401) return false;

    const data = await res.json();
    if (data.data) {
      currentUser = data.data;
      userNameDisplay.innerText = currentUser.name;
      userAvatarDisplay.innerText = currentUser.name.charAt(0).toUpperCase();
      return true;
    }
    return false;
  } catch (err) {
    return false;
  }
}

async function fetchTickets() {
  ticketList.innerHTML = '<p style="color:var(--text-muted)">Carregando...</p>';
  try {
    const res = await fetch(`${API_URL}/tickets`, {
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    
    const json = await res.json();
    
    if (json.data && Array.isArray(json.data)) {
      renderTickets(json.data);
    } else if (json.error) {
       showToast(json.message, 'error');
       if (res.status === 401) logout();
    }
  } catch (err) {
    ticketList.innerHTML = '<p style="color:#ef4444">Erro ao carregar chamados.</p>';
  }
}

function renderTickets(tickets) {
  if (tickets.length === 0) {
    ticketList.innerHTML = '<div style="color:var(--text-muted); grid-column: 1/-1;">Nenhuma O.S. aberta no momento. Parabéns!</div>';
    return;
  }

  ticketList.innerHTML = tickets.map(t => {
    // Traduções de interface
    const pLabels = { low: 'Baixa', medium: 'Média', high: 'Alta', urgent: 'Urgente' };
    const sLabels = { open: 'Aberto', in_progress: 'Em Progresso', resolved: 'Resolvido', closed: 'Fechado' };

    const date = new Date(t.createdAt).toLocaleDateString('pt-BR');
    
    // Escapa as aspas duplas do JSON para uso no HTML
    const tJson = JSON.stringify(t).replace(/"/g, '&quot;');

    return `
      <div class="ticket-card" onclick="openTicketModal(${tJson})">
        <div style="display:flex; justify-content: space-between; align-items: flex-start;">
          <h4 style="font-size: 1.1rem; margin-bottom:0.25rem;">${t.title}</h4>
          <span class="badge status-${t.status}">${sLabels[t.status]}</span>
        </div>
        
        <p style="color:var(--text-muted); font-size:0.9rem; flex:1; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
          ${t.description}
        </p>

        <div style="display:flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border); padding-top: 1rem;">
          <small style="color:var(--text-muted);">#${t.id} • ${date}</small>
          <span class="badge priority-${t.priority}">${pLabels[t.priority]}</span>
        </div>
      </div>
    `;
  }).join('');
}

// ── UTILITÁRIOS ──────────────────────────────────────────────────

function showAuth() {
  views.auth.classList.remove('hidden');
  views.dashboard.classList.add('hidden');
}

function showDashboard() {
  views.auth.classList.add('hidden');
  views.dashboard.classList.remove('hidden');
}

function logout() {
  authToken = null;
  currentUser = null;
  localStorage.removeItem('ticket_jwt');
  showAuth();
}

function openTicketModal(ticket) {
  document.getElementById('modal-title').innerText = ticket.title;
  document.getElementById('modal-desc').innerText = ticket.description;
  document.getElementById('modal-ticket-id').value = ticket.id;
  document.getElementById('modal-status').value = ticket.status;
  document.getElementById('modal-priority').value = ticket.priority;
  
  ticketModal.classList.add('active');
}

/**
 * Toast Notification nativo em JS
 */
function showToast(message, type = 'success') {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerText = message;
  
  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('fade-out');
    toast.addEventListener('animationend', () => toast.remove());
  }, 3500);
}
