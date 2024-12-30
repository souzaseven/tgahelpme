document.addEventListener('DOMContentLoaded', function () {

    // Função para exibir ou ocultar seções com base na pesquisa
    function filterContent() {
        const searchInput = document.getElementById('searchInput');
        const searchTerm = searchInput.value.toLowerCase();
        const sections = document.querySelectorAll('section');

        sections.forEach(function (section) {
            const title = section.querySelector('h2') ? section.querySelector('h2').innerText.toLowerCase() : '';
            const videoItems = section.querySelectorAll('.Dowloads-item'); // Busca itens com a classe "Dowloads-item"
            let sectionVisible = title.includes(searchTerm); // Verifica se o título da seção contém o termo

            // Filtra vídeos e itens de download dentro de cada seção
            videoItems.forEach(function (videoItem) {
                const videoTitle = videoItem.querySelector('.video-title') ? videoItem.querySelector('.video-title').innerText.toLowerCase() : '';
                const downloadLink = videoItem.querySelector('.download-link') ? videoItem.querySelector('.download-link').innerText.toLowerCase() : '';
                const downloadTitle = videoItem.querySelector('.download-title') ? videoItem.querySelector('.download-title').innerText.toLowerCase() : '';
                const dataTitle = videoItem.querySelector('.data-title') ? videoItem.querySelector('.data-title').innerText.toLowerCase() : '';

                // Verifica se o termo de pesquisa está presente em qualquer campo relevante
                const matchFound = [videoTitle, downloadLink, downloadTitle, dataTitle].some(field => field.includes(searchTerm));

                if (matchFound) {
                    videoItem.style.display = 'block'; // Exibe o item
                } else {
                    videoItem.style.display = 'none'; // Oculta o item
                }
            });

            // Exibe ou oculta a seção com base no título ou itens visíveis
            if (sectionVisible || Array.from(videoItems).some(item => item.style.display === 'block')) {
                section.style.display = 'block'; // Exibe a seção se ela corresponder à pesquisa
            } else {
                section.style.display = 'none'; // Oculta a seção se não houver correspondência
            }
        });
    }

    // Evento para filtrar conteúdo ao digitar na pesquisa
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', filterContent);
});



    // Função para mover o carrossel
    function moveCarousel(button, direction) {
        const carouselTrack = button.closest('.carousel-container').querySelector('.carousel-track');
        const videoItems = carouselTrack.querySelectorAll('.video-item');
        const itemWidth = videoItems[0].offsetWidth;
        const totalItems = videoItems.length;

        // Obtém o índice do primeiro item visível
        const currentTranslateX = parseInt(window.getComputedStyle(carouselTrack).transform.split(',')[4]) || 0;
        const currentIndex = Math.abs(currentTranslateX / itemWidth);
        
        let newIndex;

        // Define o novo índice baseado na direção
        if (direction === 'next') {
            newIndex = (currentIndex + 1) % totalItems;  // Volta para o primeiro item se for o último
        } else if (direction === 'prev') {
            newIndex = (currentIndex - 1 + totalItems) % totalItems;  // Volta para o último item se for o primeiro
        }

        // Calcula a nova posição do carrossel
        const newTranslateX = -newIndex * itemWidth;

        // Move o carrossel
        carouselTrack.style.transition = 'transform 0.5s ease';
        carouselTrack.style.transform = `translateX(${newTranslateX}px)`;
    }

    // Função para iniciar o carrossel automático
   /* function startAutoCarousel() {
        const carousels = document.querySelectorAll('.carousel-container');
        carousels.forEach(carousel => {
            setInterval(function () {
                const nextButton = carousel.querySelector('.carousel-button.next');
                moveCarousel(nextButton, 'next');
            }, 5000); // Muda a cada 5 segundos
        });
    }

    // Inicia o carrossel automático
    startAutoCarousel();*/

    // Adiciona a funcionalidade de clicar nos botões
    const nextButtons = document.querySelectorAll('.carousel-button.next');
    const prevButtons = document.querySelectorAll('.carousel-button.prev');

    nextButtons.forEach(button => {
        button.addEventListener('click', function () {
            moveCarousel(button, 'next');
        });
    });

    prevButtons.forEach(button => {
        button.addEventListener('click', function () {
            moveCarousel(button, 'prev');
        });
    });

document.addEventListener("DOMContentLoaded", () => {
    const track = document.querySelector(".carousel-track");
    const prevButton = document.querySelector(".carousel-button.prev");
    const nextButton = document.querySelector(".carousel-button.next");
    const items = document.querySelectorAll(".video-item");

    let currentPosition = 0;
    const itemsToShow = 5; // Número de itens visíveis por vez
    const totalItems = items.length;
    const itemWidth = items[0].offsetWidth;

    // Atualiza a largura total do track
    track.style.width = `${itemWidth * totalItems}px`;

    // Evento para botão "Avançar"
    nextButton.addEventListener("click", () => {
        if (currentPosition < totalItems - itemsToShow) {
            currentPosition++;
            updateCarousel();
        }
    });

    // Evento para botão "Voltar"
    prevButton.addEventListener("click", () => {
        if (currentPosition > 0) {
            currentPosition--;
            updateCarousel();
        }
    });

    // Função para atualizar a posição do carrossel
    function updateCarousel() {
        const offset = currentPosition * itemWidth;
        track.style.transform = `translateX(-${offset}px)`;
    }
});

// Função para alternar entre os modos claro e escuro
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
        }


function toggleDarkMode() {
    const body = document.body;
    body.classList.toggle('dark-mode');
}

// Obtém o botão
const scrollToTopBtn = document.getElementById("scrollToTopBtn");

// Mostra o botão quando o usuário rola até o final da página
window.onscroll = function() {
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
        scrollToTopBtn.style.display = "block"; // Exibe o botão
    } else {
        scrollToTopBtn.style.display = "none"; // Esconde o botão
    }
};

// Função para rolar até o topo da página
scrollToTopBtn.onclick = function() {
    window.scrollTo({
        top: 0,
        behavior: "smooth" // Rolagem suave
    });
};


/***Função para exibir a mensagem motivacional************************/

function showMotivationalMessage() {
  const now = new Date();
  const hours = now.getHours();
  let message = '';

  // Mensagem com base na hora do dia
  if (hours < 12) {
    message = "Bom dia! Seja bem-vindo(a).<br>Vamos conquistar o dia com energia e foco!";
  } else if (hours < 18) {
    message = "Boa tarde! Seja bem-vindo(a).<br>Continue firme, você está indo muito bem!";
  } else {
    message = "Boa noite! Seja bem-vindo(a).<br>Um excelente descanso para recarregar as energias!";
  }

  // Atualiza o conteúdo da mensagem
  document.getElementById("message").innerHTML = message;

  // Exibe a mensagem
  const messageElement = document.getElementById("motivational-message");
  messageElement.style.display = "flex"; // Torna o div visível

  // Oculta a mensagem após 5 segundos
  setTimeout(() => {
    messageElement.style.display = "none"; // Esconde a mensagem após 5 segundos
  }, 5000);
}



/**botoes para ver o manual */
    const pdfUrl = './telefonia-evolux/manuaispdf/Manual do Operador - Versão 6.75.pdf'; // Caminho para o PDF

    // Função para ver o PDF em segundo plano
    function viewPdfInBackground() {
        // Verifica se o iframe já existe para não adicionar mais de um
        if (!document.getElementById('pdfIframe')) {
            const iframe = document.createElement('iframe');
            iframe.src = pdfUrl;
            iframe.id = 'pdfIframe'; // Atribui um ID único ao iframe
            iframe.style.position = 'absolute';
            iframe.style.top = '0';
            iframe.style.left = '0';
            iframe.style.width = '100%';
            iframe.style.height = '100vh';  // O iframe ocupa toda a altura da tela
            iframe.style.zIndex = '999';   // Coloca o iframe acima de outros elementos
            iframe.style.border = 'none';  // Sem bordas
            document.body.appendChild(iframe); // Adiciona o iframe à página

            // Criar o botão de fechar
            const closeButton = document.createElement('button');
            closeButton.innerText = 'Fechar';
            closeButton.style.position = 'absolute';
            closeButton.style.top = '20px';
            closeButton.style.right = '20px';
            closeButton.style.padding = '10px 15px';
            closeButton.style.backgroundColor = '#FF5C5C';
            closeButton.style.color = 'white';
            closeButton.style.border = 'none';
            closeButton.style.borderRadius = '5px';
            closeButton.style.cursor = 'pointer';
            closeButton.style.zIndex = '1000'; // Garante que o botão fique acima do iframe
            closeButton.addEventListener('click', closePdfInBackground);

            // Adiciona o botão de fechar na página
            document.body.appendChild(closeButton);
        }
    }

    // Função para fechar o PDF em segundo plano
    function closePdfInBackground() {
        const iframe = document.getElementById('pdfIframe');
        if (iframe) {
            iframe.remove(); // Remove o iframe da página
        }

        const closeButton = document.querySelector('button[style*="position: absolute;"]');
        if (closeButton) {
            closeButton.remove(); // Remove o botão de fechar
        }
    }

    // Função para baixar o PDF
    function downloadPdf() {
        const link = document.createElement('a');
        link.href = pdfUrl;
        link.download = 'Manual do Operador - Versão 6.75.pdf'; // Nome do arquivo que será baixado
        link.click();
    }

    // Função para abrir o PDF em uma nova guia
    function openPdfInNewTab() {
        window.open(pdfUrl, '_blank');
    }

// Verifica se a mensagem já foi exibida antes
if (!localStorage.getItem('motivationalMessageShown')) {
  // Se não foi exibida, exibe a mensagem
  showMotivationalMessage();

  // Marca no localStorage que a mensagem foi exibida
  localStorage.setItem('motivationalMessageShown', 'true');
}

 
/*botao de copoiar */
 function copyLinkgmail() {
        const link = "https://myaccount.google.com/apppasswords?utm_source=google-account&utm_medium=myaccountsecurity&utm_campaign=tsv-settings&rapt=AEjHL4OHLAbIlcVML9-0cdvQJf7CDFww6cn7XzilZV1rwmqZy2tOLi_GQ0-OzicbFJiiEANFQi-QP5Sy8gGHiLQWzy6y96OxaKFgks8KkaFWSjUHnWi2J8I"; 
        navigator.clipboard.writeText(link).then(() => {
            alert("Link copiado para a área de transferência!");
        }).catch(err => {
            console.error("Erro ao copiar o link: ", err);
        });
    }

 function copyLinkoutlook() {
        const link = "https://account.live.com/proofs/AppPassword?uaid=0d4fbc709a0b4baa9be4bf7658cea8a4&mpsplit=2&mkt=pt-BR"; 
        navigator.clipboard.writeText(link).then(() => {
            alert("Link copiado para a área de transferência!");
        }).catch(err => {
            console.error("Erro ao copiar o link: ", err);
        });
    }

/* dessa forma vai de jeito direto na aba*/
  // Função para alternar a visibilidade do menu
       /* function toggleMenu() {
            const menu = document.querySelector(".nav");
            menu.style.display = menu.style.display === "block" ? "none" : "block";
        }

        // Fecha o menu ao clicar fora dele
        document.addEventListener("click", function (event) {
            const menu = document.querySelector(".nav");
            const menuButton = document.querySelector(".menu-toggle");

            if (!menu.contains(event.target) && !menuButton.contains(event.target)) {
                menu.style.display = "none";
            }
        });*/

/*teste com rolagem */
 // Função para alternar a visibilidade do menu
/*    function toggleMenu() {
        const menu = document.querySelector(".nav");
        menu.style.display = menu.style.display === "block" ? "none" : "block";
    }

    // Fecha o menu ao clicar fora dele
    document.addEventListener("click", function (event) {
        const menu = document.querySelector(".nav");
        const menuButton = document.querySelector(".menu-toggle");

        if (!menu.contains(event.target) && !menuButton.contains(event.target)) {
            menu.style.display = "none";
        }
    });

    // Adiciona rolagem suave aos links de ancoragem
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);

            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

const menu = document.querySelector(".nav");
const menuButton = document.querySelector(".menu-container");

// Abre o menu ao passar o mouse sobre o botão
menuButton.addEventListener("mouseover", () => {
    menu.classList.remove("hidden"); // Remove a classe que oculta o menu
});

// Fecha o menu ao sair do menu
menu.addEventListener("mouseleave", () => {
    menu.classList.add("hidden"); // Adiciona a classe que oculta o menu
});
*/

// Funcao para alternar a visibilidade do menu ao clicar
function toggleMenu() {
    const menu = document.querySelector(".nav");
    menu.style.display = menu.style.display === "block" ? "none" : "block";
}

// Fecha o menu ao clicar fora dele
document.addEventListener("click", function (event) {
    const menu = document.querySelector(".nav");
    const menuButton = document.querySelector(".menu-toggle");

    if (!menu.contains(event.target) && !menuButton.contains(event.target)) {
        menu.style.display = "none";
    }
});

// Adiciona rolagem suave aos links de ancoragem
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener("click", function (e) {
        e.preventDefault();
        const targetId = this.getAttribute("href");
        const targetElement = document.querySelector(targetId);

        if (targetElement) {
            targetElement.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });
        }
    });
});

// Lógica para abrir o menu ao passar o mouse
const menu = document.querySelector(".nav");
const menuButton = document.querySelector(".menu-container");

// Abre o menu ao passar o mouse sobre o botão
menuButton.addEventListener("mouseover", () => {
    menu.style.display = "block";
});

// Fecha o menu ao sair do menu
menu.addEventListener("mouseleave", () => {
    menu.style.display = "none";
});



/*botao de expandir */
document.getElementById('showMoreBtn').addEventListener('click', function() {
    var moreItems = document.getElementById('moreItems1');
    var button = document.getElementById('showMoreBtn');

    // Verifica se os itens extras estão ocultos
    if (moreItems.style.display === 'none' || moreItems.style.display === '') {
        // Exibe os itens
        moreItems.style.display = 'block';
        button.textContent = 'Ocultar'; // Altera o texto do botão
    } else {
        // Oculta os itens
        moreItems.style.display = 'none';
        button.textContent = 'Mostrar mais'; // Altera o texto do botão
    }
});
