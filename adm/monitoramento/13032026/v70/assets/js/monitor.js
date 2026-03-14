/*
|--------------------------------------------------------------------------
| CARREGAR AGENTES DA API
|--------------------------------------------------------------------------
| Esta função chama nosso endpoint PHP que consulta a API Evolux
*/

async function carregarAgentes() {

    const res = await fetch('/api/agentes.php');
    const agents = await res.json();

    console.log(agents);

}


/*
|--------------------------------------------------------------------------
| ATUALIZAÇÃO AUTOMÁTICA
|--------------------------------------------------------------------------
| Atualiza os dados a cada 5 segundos
*/

setInterval(carregarAgentes, 5000);


/*
|--------------------------------------------------------------------------
| PRIMEIRA EXECUÇÃO
|--------------------------------------------------------------------------
*/

carregarAgentes();