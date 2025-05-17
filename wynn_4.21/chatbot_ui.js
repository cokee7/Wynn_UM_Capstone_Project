// chatbot_ui.js

// Grab DOM elements
const form  = document.getElementById('chat-form');
const input = document.getElementById('msg-input');
const msgs  = document.getElementById('messages');

// 0) On page load, request a hidden “__init__” greeting
window.addEventListener('load', async () => {
  try {
    const r = await fetch('chatbot_endpoint.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'message=' + encodeURIComponent('__init__')
    });
    const d = await r.json();
    if (d.reply) appendMessage('Bot', d.reply, 'msg-bot');
  } catch (err) {
    console.error(err);
  }
});

// 1) Handle user submissions
form.addEventListener('submit', async e => {
  e.preventDefault();
  const text = input.value.trim();
  if (!text) return;

  appendMessage('You', text, 'msg-user');
  input.value = '';

  try {
    const res = await fetch('chatbot_endpoint.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'message=' + encodeURIComponent(text)
    });
    // Check for non‑JSON
    const ctype = res.headers.get('Content-Type')||'';
    if (!res.ok) throw new Error(`Server ${res.status}`);
    if (!ctype.includes('application/json')) {
      const txt = await res.text();
      throw new Error('Invalid response: ' + txt.slice(0,200));
    }

    const data = await res.json();
    if (data.reply) appendMessage('Bot', data.reply, 'msg-bot');
    else appendMessage('Error', data.error||'Unknown error','msg-error');
  } catch (err) {
    appendMessage('Error', err.message, 'msg-error');
  }
});

// 2) Utility to render Markdown and scroll
function appendMessage(author, text, cls) {
  const container = document.createElement('div');
  container.className = `message ${cls}`;

  const name = document.createElement('span');
  name.className = 'author';
  name.textContent = `${author}:`;
  container.appendChild(name);

  const md = document.createElement('div');
  md.className = 'markdown-content';
  md.innerHTML = marked.parse(text);
  container.appendChild(md);

  msgs.appendChild(container);
  msgs.scrollTop = msgs.scrollHeight;
}


