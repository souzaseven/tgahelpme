document.getElementById("chaveAcesso").addEventListener("input", function () {
    const chave = this.value;

    // Limpa os blocos se a chave estiver vazia
    if (chave.length === 0) {
        limparBlocos();
        return;
    }

    // Estrutura da chave de acesso
    const estrutura = {
        UF: chave.slice(0, 2),
        AnoMes: chave.slice(2, 6),
        CNPJ: chave.slice(6, 20),
        Modelo: chave.slice(20, 22),
        Serie: chave.slice(22, 25),
        Numero: chave.slice(25, 34),
        TipoEmissao: chave.slice(34, 35),
        CodigoNumerico: chave.slice(35, 43),
        DigitoVerificador: chave.slice(43, 44)
    };

    // Mapeamento dos modelos de documentos fiscais
    const modelos = {
        "55": "NF-e (Nota Fiscal Eletrônica)",
        "65": "NFC-e (Nota Fiscal de Consumidor Eletrônica)",
        "57": "CT-e (Conhecimento de Transporte Eletrônico)",
        "58": "MDF-e (Manifesto de Documentos Fiscais Eletrônicos)",
        "59": "CF-e (Cupom Fiscal Eletrônico)"
    };

    // Mapeamento dos códigos de UF
    const ufs = {
        "11": "Rondônia", "12": "Acre", "13": "Amazonas", "14": "Roraima", "15": "Pará",
        "16": "Amapá", "17": "Tocantins", "21": "Maranhão", "22": "Piauí", "23": "Ceará",
        "24": "Rio Grande do Norte", "25": "Paraíba", "26": "Pernambuco", "27": "Alagoas",
        "28": "Sergipe", "29": "Bahia", "31": "Minas Gerais", "32": "Espírito Santo",
        "33": "Rio de Janeiro", "35": "São Paulo", "41": "Paraná", "42": "Santa Catarina",
        "43": "Rio Grande do Sul", "50": "Mato Grosso do Sul", "51": "Mato Grosso",
        "52": "Goiás", "53": "Distrito Federal"
    };

    // Atualiza os blocos com as informações disponíveis


    atualizarBloco("uf", ufs[estrutura.UF] ? `${ufs[estrutura.UF]} (${estrutura.UF})` : "-");
    atualizarBloco("anoMes", estrutura.AnoMes || "-");
    atualizarBloco("cnpj", estrutura.CNPJ || "-");
   atualizarBloco("tipoNota", estrutura.Modelo + " - " + (modelos[estrutura.Modelo] || "-"));
    atualizarBloco("serie", estrutura.Serie || "-");
    atualizarBloco("numero", estrutura.Numero || "-");
    atualizarBloco("tipoEmissao", estrutura.TipoEmissao || "-");
    atualizarBloco("codigoNumerico", estrutura.CodigoNumerico || "-");
    atualizarBloco("digitoVerificador", estrutura.DigitoVerificador || "-");

    // Atualiza o nome do documento
    document.getElementById("nomeDocumento").textContent = modelos[estrutura.Modelo] || "-";
});

function atualizarBloco(id, valor) {
    const bloco = document.getElementById(id);
    if (bloco) {
        bloco.querySelector("p").textContent = valor;
    }
}

function limparBlocos() {
    const blocos = document.querySelectorAll(".bloco p");
    blocos.forEach(bloco => {
        bloco.textContent = "-";
    });
    document.getElementById("nomeDocumento").textContent = "-";
}