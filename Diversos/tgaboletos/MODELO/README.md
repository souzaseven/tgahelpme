# Central de Configuração de Boletos via API

Painel interno de apoio ao suporte para configurar bancos que emitem boleto via API — banco → portador → configuração → credenciais → teste → validação → documentação.

Stack: **HTML + CSS + JavaScript puro**, sem build e sem dependências. Basta abrir `index.html` no navegador (ou servir a pasta com qualquer servidor estático).

## Estrutura

```
index.html
css/style.css
js/
  tema.js             → alternância de modo claro/escuro (botão da sidebar + persistência).
  data.js             → toda a base de dados (bancos + modelo do Portador). Adicionar banco = adicionar objeto aqui.
  ui.js               → helpers de renderização (cards, badges, campo-card).
  busca.js            → índice de busca client-side (bancos, campos, erros).
  portador-mockup.js  → réplica visual e INTERATIVA da tela real "Portador" (ver abaixo).
  bancos.js           → monta a página de detalhe de um banco.
  app.js              → roteador (hash routing) + dashboard.
```

## Estado atual (23/08/2026)

**Feito**
- **Fase 1 — Base**: layout, sidebar, dashboard, cards dos bancos, busca global. ✅
- **Modo escuro**: segue a preferência do sistema por padrão; botão na sidebar (topo) alterna manualmente e a escolha fica salva no navegador (`localStorage`). ✅
- **Mockup fiel e editável da tela "Portador"**: réplica visual com título/toolbar/abas clicáveis, reproduzindo o layout exato das imagens de referência. Todos os campos são de verdade editáveis (digitar, marcar checkbox, escolher opção) — é uma maquete sem backend, nada é salvo, serve como referência/simulação para o suporte. Vem pré-preenchido com valores de exemplo (Código=1, Descrição="API BOLETO {nome do banco}", Conta/Caixa Vinculada, Nome do Cedente="Razão Social", Tipo Inscrição=CNPJ). Cada rótulo com sublinhado pontilhado mostra uma dica ao passar o mouse, com o texto vindo de `portadorCamposBase` (mesma fonte do "Ver explicação campo a campo", nunca duplicada). Campos relevantes para o banco atual recebem contorno na cor de destaque dele. ✅
- **Aba "Outros Dados" completa**: todos os campos da tela real (Acrescer % Multa, R$ Taxa Boleto, Vl. Mínimo Boleto, Campo Extra, Ultimo Boleto, grupos "Somente para Banco CEF"/"Somente para Banco Sicredi"/"Banco Sicoob", Razão Social/CNPJ/Endereço, Fone Beneficiário, Cod. Avalista, C.Custo Taxa Adm, grupo "SPED Fiscal - Registro 1601", Dias Min. Ven. Boleto e os 7 checkboxes "Usa X") — antes era só um aviso de "pendente". Campos específicos de CEF, Sicredi e Sicoob recebem destaque automático nas páginas desses bancos. ✅
- **Aba Remessa/Retorno é dinâmica**: junto de "Tipo Cobrança API" há uma sub-seção que troca sozinha ao mudar a seleção — com `Nenhum` mostra **Caminho Arquivo** (pastas de remessa/retorno); com qualquer banco mostra **Chave API** (Ambiente, Usa Pix, Imprimir Sem Registrar, client_id/client_secret, e campos extras específicos: Chave Pix, workspace_id/DeveloperKey, Nº Dias Agenda, Versão API, upload de certificado .KEY/.CRT-PEM — variando por banco, ver `chaveApiPorTipo` em `data.js`). Campo **Seq. Remessa é editável** (a tela real não trava esse campo). ✅
- **Fase 2 — Banco do Brasil**: modelo completo a partir das imagens da tela **Portador**, com campos-chave do BB destacados. Os parâmetros gerais de API (Client ID, Client Secret, Application Key, Convênio, Carteira) descritos na seção "Parâmetros de Integração via API" estão marcados como `origem: publica` — vieram de conhecimento público de mercado, **não das imagens**, e precisam de confirmação antes de virar verdade oficial no painel. Os campos da aba "Chave API" (client_id, client_secret, Ambiente, Usa Pix etc.) já vieram das imagens reais e são a fonte de verdade oficial.

**10 bancos mapeados** (Banco do Brasil, Sicredi, Sicoob, Banco Inter, Santander, Itaú, Bradesco, Caixa Econômica, Cresol, C6 Bank) — todos reconhecidos pelo campo `Tipo Cobrança API` do Portador. Documentação detalhada de checklist/passo a passo/erros ainda só existe para o Banco do Brasil; os demais têm o mockup completo (Dados do Cedente + Chave API específica) mas faltam checklist, passo a passo e erros catalogados.

**Ainda não implementado** (fases futuras do master prompt):
- Fase 9 — Busca global mais avançada (filtros por tipo de autenticação/ambiente/status)
- Fase 10 — Base de erros mais completa
- Fase 11 — Testes de API reais (hoje toda tela de teste mostra "Teste ainda não integrado", propositalmente — nunca simular sucesso)
- Fase 12 — Painel administrativo (hoje "Admin" e "Comparador" aparecem no menu como "em breve")

## Observação sobre a tela "Portador"

As imagens enviadas mostram o cadastro único do ERP usado para **qualquer** banco — a escolha do banco/API acontece no campo `Tipo Cobrança API` (aba Remessa/Retorno), que por sua vez decide se aparece "Caminho Arquivo" ou "Chave API" logo abaixo. Por isso o painel trata esse cadastro como um modelo compartilhado (`js/data.js → portadorCamposBase`), e cada página de banco apenas destaca os campos mais relevantes para aquele banco específico. Um campo cuja função foi inferida (não estava explícito na imagem) aparece marcado com a tag **"Inferido"**.
