(() => {
  'use strict';
  const menuToggle = document.getElementById('menuToggle');
  const mainNav = document.getElementById('mainNav');
  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', () => {
      const open = mainNav.classList.toggle('open');
      menuToggle.setAttribute('aria-expanded', String(open));
      menuToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    });
    mainNav.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
      mainNav.classList.remove('open');
      menuToggle.setAttribute('aria-expanded', 'false');
      menuToggle.setAttribute('aria-label', 'Open navigation');
    }));
  }

  document.getElementById('currentYear')?.replaceChildren(document.createTextNode(String(new Date().getFullYear())));

  const form = document.getElementById('feedbackForm');
  const status = document.getElementById('feedbackStatus');
  const message = document.getElementById('feedbackMessage');
  const charCount = document.getElementById('charCount');
  const downloadButton = document.getElementById('downloadFeedback');
  const clearButton = document.getElementById('clearFeedback');
  const STORAGE_KEY = 'salondiva_feedback_v1';

  function getFeedback() {
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      return Array.isArray(saved) ? saved : [];
    } catch (_) {
      return [];
    }
  }
  function showStatus(text, type) {
    if (!status) return;
    status.textContent = text;
    status.className = `form-status show ${type}`;
  }
  function syncCount() {
    if (charCount && message) charCount.textContent = String(message.value.length);
  }
  message?.addEventListener('input', syncCount);
  syncCount();

  form?.addEventListener('submit', event => {
    event.preventDefault();
    if (!form.reportValidity()) {
      showStatus('Please complete the required fields and enter a valid email address.', 'error');
      return;
    }
    const data = new FormData(form);
    const entry = {
      name: String(data.get('name') || '').trim(),
      email: String(data.get('email') || '').trim(),
      topic: String(data.get('topic') || 'Other'),
      message: String(data.get('message') || '').trim(),
      submittedAt: new Date().toISOString()
    };
    if (!entry.name || !entry.email || !entry.message) {
      showStatus('Please complete the required fields.', 'error');
      return;
    }
    try {
      const saved = getFeedback();
      saved.push(entry);
      localStorage.setItem(STORAGE_KEY, JSON.stringify(saved));
      form.reset();
      syncCount();
      showStatus('Thank you! Your feedback has been saved in this browser. To share it with the salon, download the saved feedback file and send it to the salon team.', 'success');
    } catch (_) {
      showStatus('This browser could not save the feedback. Please allow local storage or download your message separately.', 'error');
    }
  });

  downloadButton?.addEventListener('click', () => {
    const saved = getFeedback();
    if (!saved.length) {
      showStatus('There is no saved feedback to download on this device yet.', 'error');
      return;
    }
    const blob = new Blob([JSON.stringify(saved, null, 2)], { type: 'application/json;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `salondiva-feedback-${new Date().toISOString().slice(0, 10)}.json`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    showStatus(`Downloaded ${saved.length} saved feedback entr${saved.length === 1 ? 'y' : 'ies'}.`, 'success');
  });

  clearButton?.addEventListener('click', () => {
    const saved = getFeedback();
    if (!saved.length) {
      showStatus('There is no saved feedback to clear.', 'error');
      return;
    }
    if (window.confirm(`Delete all ${saved.length} feedback entr${saved.length === 1 ? 'y' : 'ies'} saved in this browser? This cannot be undone.`)) {
      localStorage.removeItem(STORAGE_KEY);
      showStatus('Saved feedback was cleared from this browser.', 'success');
    }
  });
})();
