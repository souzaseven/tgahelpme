/* Quallit Manager — gráfico do dashboard (Chart.js).
   Lê os dados de <script id="chart-data" type="application/json"> renderizado
   pelo servidor, então nenhuma informação fica embutida neste arquivo. */

(function () {
    "use strict";

    var canvas = document.getElementById("usersChart");
    var raw = document.getElementById("chart-data");

    if (!canvas || !raw || typeof Chart === "undefined") {
        return;
    }

    var dados;
    try {
        dados = JSON.parse(raw.textContent);
    } catch (e) {
        return;
    }

    var TEAL = "#1b9ba8";
    var ORANGE = "#fd811f";

    new Chart(canvas, {
        type: "bar",
        data: {
            labels: dados.labels,
            datasets: [
                {
                    label: "Novos",
                    data: dados.novos,
                    backgroundColor: TEAL,
                    borderRadius: 2,
                    maxBarThickness: 22,
                },
                {
                    label: "Desativados",
                    data: dados.desativados,
                    backgroundColor: ORANGE,
                    borderRadius: 2,
                    maxBarThickness: 22,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: "index", intersect: false },
            plugins: {
                legend: {
                    position: "top",
                    align: "center",
                    labels: { boxWidth: 14, boxHeight: 14, padding: 16 },
                },
                tooltip: { mode: "index", intersect: false },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        maxRotation: 0,
                        autoSkip: true,
                        autoSkipPadding: 8,
                        font: { size: 10 },
                    },
                },
                y: {
                    beginAtZero: true,
                    max: 216,
                    ticks: { stepSize: 8 },
                    grid: { color: "rgba(0,0,0,.06)" },
                },
            },
        },
    });
})();
