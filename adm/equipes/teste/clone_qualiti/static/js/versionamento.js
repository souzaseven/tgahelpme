/* Quallit Manager — tela Versionamento: gráfico de barras (distribuição por
   versão) + rosca (tempo desde a última troca). Dados vêm do <script> JSON
   renderizado pelo servidor. */

(function () {
    "use strict";

    var raw = document.getElementById("versionamento-data");
    if (!raw || typeof Chart === "undefined") {
        return;
    }

    var dados;
    try {
        dados = JSON.parse(raw.textContent);
    } catch (e) {
        return;
    }

    var barra = document.getElementById("distChart");
    if (barra) {
        new Chart(barra, {
            type: "bar",
            data: {
                labels: dados.distribuicao.labels,
                datasets: [{
                    label: "Clientes",
                    data: dados.distribuicao.valores,
                    backgroundColor: "#5aa07f",
                    maxBarThickness: 620,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: "rgba(0,0,0,.06)" } },
                },
            },
        });
    }

    var rosca = document.getElementById("trocaChart");
    if (rosca) {
        new Chart(rosca, {
            type: "doughnut",
            data: {
                labels: dados.tempo_troca.labels,
                datasets: [{
                    data: dados.tempo_troca.valores,
                    backgroundColor: dados.tempo_troca.cores,
                    borderWidth: 2,
                    borderColor: "#fff",
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "62%",
                plugins: {
                    legend: { position: "bottom", labels: { boxWidth: 14, padding: 12 } },
                },
            },
        });
    }
})();
