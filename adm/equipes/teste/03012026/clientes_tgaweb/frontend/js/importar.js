const modalImportar = document.getElementById("modalImportar");
const btnImportar   = document.getElementById("btnImportar");
const btnEnviarCSV  = document.getElementById("btnEnviarCSV");
const arquivoCSV    = document.getElementById("arquivoCSV");

btnImportar.onclick = () => {
  modalImportar.classList.add("show");
};

function fecharModalImportar(){
  modalImportar.classList.remove("show");
  arquivoCSV.value = "";
}

btnEnviarCSV.onclick = async () => {
  if (!arquivoCSV.files.length) {
    alert("Selecione um arquivo CSV");
    return;
  }

  const formData = new FormData();
  formData.append("arquivo", arquivoCSV.files[0]);

  const res = await fetch("backend/importar_clientes.php", {
    method: "POST",
    body: formData
  });

  const json = await res.json();

  if (!json.success) {
    alert("Erro ao importar: " + json.error);
    return;
  }

  alert(
    `Importação concluída!\n\n` +
    `Inseridos: ${json.inseridos}\n` +
    `Atualizados: ${json.atualizados}\n` +
    `Erros: ${json.erros}`
  );

  fecharModalImportar();
  carregarClientes();
};
