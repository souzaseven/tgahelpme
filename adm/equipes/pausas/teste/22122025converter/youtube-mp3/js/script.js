
  // Elementos principais da interface
  const themeToggle = document.getElementById('themeToggle');
  const urlInput = document.getElementById('url');
  const urlError = document.getElementById('urlError');
  const loading = document.getElementById('loading');
  const result = document.getElementById('result');
  const downloadBtn = document.getElementById('downloadBtn');

  // Elementos do preview do vídeo
  const videoInfo = document.getElementById('videoInfo');
  const videoTitle = document.getElementById('videoTitle');
  const videoThumbnail = document.getElementById('videoThumbnail');

  // Alterna entre modo claro e escuro ao clicar no botão
  themeToggle.onclick = () => {
    document.body.classList.toggle('dark-mode');
    const icon = themeToggle.querySelector('i');
    icon.classList.toggle('fa-moon');
    icon.classList.toggle('fa-sun');
  };

  // Verifica se a URL informada é do YouTube
  function isYouTube(url) {
    return /^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/.test(url);
  }

  // Evento de envio do formulário de download
  document.getElementById('downloadForm').addEventListener('submit', async e => {
    e.preventDefault();

    const url = urlInput.value.trim();

    // Validação da URL
    if (!isYouTube(url)) {
      urlError.style.display = 'block';
      return;
    }

    urlError.style.display = 'none';
    loading.style.display = 'block';
    result.style.display = 'none';

    try {
      // Envia a URL para o backend via POST
      const res = await fetch('baixar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ url, formato: 'mp3' })
      });

      const data = await res.json();
      loading.style.display = 'none';

      // Exibe botão de download se tudo der certo
      if (data.success && data.file) {
        result.style.display = 'block';
        downloadBtn.onclick = () => window.open(data.file, '_blank');
      } else {
        alert('Erro: ' + (data.error || 'Falha no download. Tente novamente.'));
      }
    } catch (err) {
      loading.style.display = 'none';
      alert('Erro de rede ou no servidor. Tente novamente.');
    }
  });

  // Evento para mostrar o título e thumbnail ao digitar uma URL do YouTube
  urlInput.addEventListener('input', async () => {
    const url = urlInput.value.trim();
    const match = url.match(/(?:v=|\/)([0-9A-Za-z_-]{11})/); // Extrai o ID do vídeo

    if (match) {
      const videoId = match[1];
      const oEmbedUrl = `https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=${videoId}&format=json`;

      try {
        const res = await fetch(oEmbedUrl);
        const data = await res.json();

        // Atualiza as informações do vídeo na interface
        videoInfo.style.display = 'block';
        videoTitle.textContent = data.title;
        videoThumbnail.src = `https://img.youtube.com/vi/${videoId}/hqdefault.jpg`;
      } catch {
        // Esconde caso ocorra erro (URL inválida, vídeo removido etc.)
        videoInfo.style.display = 'none';
      }
    } else {
      videoInfo.style.display = 'none';
    }
  });
