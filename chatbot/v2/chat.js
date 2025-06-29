document.addEventListener('DOMContentLoaded', function () {
  const chatBox = document.getElementById('chat-box');
  const chatForm = document.getElementById('chat-form');
  const userInput = document.getElementById('user-input');
  const chatHeader = document.getElementById('chat-header');
  const chatConteudo = document.getElementById('chat-conteudo');
  const toggleButton = document.getElementById('toggle-theme');
  const imageInput = document.getElementById('image-input');
  const imageBtn = document.getElementById('image-btn');
  const emojiBtn = document.getElementById('emoji-btn');
  const previewContainer = document.getElementById('preview-container');
  let shouldAutoScroll = true;

  function linkify(text) {
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    return text.replace(urlRegex, url => `<a href="${url}" target="_blank">${url}</a>`);
  }

  function getCurrentTime() {
    const now = new Date();
    return `${now.getHours()}:${now.getMinutes().toString().padStart(2, '0')}`;
  }

  function scrollToBottom() {
    chatBox.scrollTop = chatBox.scrollHeight;
  }

  function addMessage(text, className, time = null) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${className}`;
    messageDiv.innerHTML = linkify(text);
    const timestamp = document.createElement('div');
    timestamp.className = 'timestamp';
    timestamp.textContent = time || getCurrentTime();
    messageDiv.appendChild(timestamp);
    chatBox.appendChild(messageDiv);
    if (shouldAutoScroll) setTimeout(scrollToBottom, 10);
  }

  function showTypingIndicator() {
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message bot-message thinking';
    typingDiv.id = 'typing-indicator';
    typingDiv.innerHTML = `
      <div class="typing-indicator">
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
      </div>`;
    chatBox.appendChild(typingDiv);
    scrollToBottom();
  }

  function removeTypingIndicator() {
    const typing = document.getElementById('typing-indicator');
    if (typing) typing.remove();
  }

  function salvarNoHistorico(text, className, time = null) {
    const historicoAtual = JSON.parse(localStorage.getItem('chatHistorico')) || [];
    historicoAtual.push({ text, className, time: time || getCurrentTime() });
    localStorage.setItem('chatHistorico', JSON.stringify(historicoAtual));
  }

  function enviarMensagem(pergunta, imagemBase64 = null) {
    const nome = localStorage.getItem('chatUserName') || 'chat';
    const hora = getCurrentTime();
    addMessage(pergunta, 'user-message', hora);
    salvarNoHistorico(pergunta, 'user-message', hora);
    showTypingIndicator();

    const bodyData = new URLSearchParams();
    bodyData.append('pergunta', pergunta);
    bodyData.append('nome', nome);
    if (imagemBase64) bodyData.append('imagem', imagemBase64);

    fetch('responder.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: bodyData.toString()
    })
      .then(res => res.json())
      .then(data => {
        removeTypingIndicator();
        const resposta = data.resposta || 'Ainda não sei responder, mas estou aprendendo.';
        const horaResp = getCurrentTime();
        addMessage(resposta, 'bot-message', horaResp);
        salvarNoHistorico(resposta, 'bot-message', horaResp);
      })
      .catch(err => {
        removeTypingIndicator();
        const erro = 'Erro ao consultar a resposta.';
        addMessage(erro, 'bot-message', getCurrentTime());
        salvarNoHistorico(erro, 'bot-message', getCurrentTime());
      });
  }

  // Evento de envio de texto
  chatForm.addEventListener('submit', e => {
    e.preventDefault();
    const pergunta = userInput.value.trim();
    if (!pergunta) return;
    userInput.value = '';
    previewContainer.innerHTML = '';
    enviarMensagem(pergunta);
  });

  // Emojis
  emojiBtn.addEventListener('click', () => {
    const emoji = prompt('Digite um emoji para adicionar:');
    if (emoji) userInput.value += emoji;
  });

  // Imagem via botão
  imageBtn.addEventListener('click', () => imageInput.click());

  // Imagem via input
  imageInput.addEventListener('change', () => {
    const file = imageInput.files[0];
    if (file) handleImage(file);
  });

  // Imagem via colagem
  document.addEventListener('paste', e => {
    const item = [...e.clipboardData.items].find(i => i.type.startsWith('image'));
    if (item) handleImage(item.getAsFile());
  });

  // Imagem via arrastar
  chatBox.addEventListener('dragover', e => e.preventDefault());
  chatBox.addEventListener('drop', e => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) handleImage(file);
  });

  // Função de leitura de imagem base64
  function handleImage(file) {
    const reader = new FileReader();
    reader.onload = () => {
      const base64 = reader.result;
      previewContainer.innerHTML = `<p><strong>Imagem enviada:</strong></p><img src="${base64}" style="max-width:100%;margin:10px 0;border-radius:8px;">`;
      enviarMensagem('[Imagem enviada]', base64);
    };
    reader.readAsDataURL(file);
  }

  // Scroll automático
  chatBox.addEventListener('scroll', () => {
    shouldAutoScroll = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 10;
  });

  // Carrega histórico salvo
  const historico = JSON.parse(localStorage.getItem('chatHistorico')) || [];
  historico.forEach(msg => addMessage(msg.text, msg.className, msg.time));
  if (historico.length === 0) {
    const boasVindas = 'Olá! Sou o assistente virtual do TGAMEAJUDA. Como posso ajudar você hoje?';
    addMessage(boasVindas, 'bot-message');
    salvarNoHistorico(boasVindas, 'bot-message');
  }

  // Alternância de tema
  toggleButton.addEventListener('click', () => {
    document.body.classList.toggle('light-mode');
    toggleButton.textContent = document.body.classList.contains('light-mode') ? 'Modo Escuro' : 'Modo Claro';
    localStorage.setItem('modo', document.body.classList.contains('light-mode') ? 'light' : 'dark');
  });
  if (localStorage.getItem('modo') === 'light') {
    document.body.classList.add('light-mode');
    toggleButton.textContent = 'Modo Escuro';
  }

  // Minimizar chat
  chatHeader.addEventListener('click', () => {
    chatConteudo.classList.toggle('oculto-box');
  });
});
