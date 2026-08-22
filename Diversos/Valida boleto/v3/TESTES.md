# Testes manuais — Validador de Boleto

Todos os exemplos abaixo foram gerados e conferidos executando o próprio
`js/boleto.js` (não são exemplos de memória) — abra
`validaboletoauto.html` no navegador e cole cada entrada no campo principal
para conferir se o resultado bate com o esperado.

Sempre que um novo exemplo for necessário no futuro, gere-o da mesma forma:
monte o código de barras primeiro (fonte da verdade), calcule os DVs com
`BoletoLib.calcularModulo10`/`calcularModulo11` e converta com
`BoletoLib.codigoBarrasParaLinhaDigitavel` — nunca digite uma linha "de
cabeça", pois um único dígito errado invalida o teste.

---

## Caso 1 — Linha de cobrança válida (ciclo antigo)

**Entrada:**
```
34191.23454 67890.123457 67890.123457 8 07000000123456
```
(sem formatação: `34191234546789012345767890123457807000000123456`)

**Esperado:**
- Status: **VÁLIDO**
- Banco: `001`... na verdade `341` — **Itaú Unibanco**
- Valor: **R$ 1.234,56**
- Vencimento: **07/09/1999** (sem aviso de ciclo, pois fator < 1000)
- DV Campo 1, DV Campo 2, DV Campo 3 e DV Geral: todos **OK**

---

## Caso 2 — Mesma linha do Caso 1 com 1 dígito alterado no meio (campo livre)

**Entrada:**
```
34191234546789092345767890123457807000000123456
```

**Esperado:**
- Status: **INVÁLIDO**
- Pelo menos um DV de campo com `informado ≠ calculado` no diagnóstico (o dígito alterado está dentro do dado do Campo 2, então **DV Campo 2** deve falhar)

---

## Caso 3 — Mesma linha do Caso 1 com o DV do Campo 1 alterado

**Entrada:**
```
34191234596789012345767890123457807000000123456
```

**Esperado:**
- Status: **INVÁLIDO**
- Diagnóstico: **DV Campo 1** → informado `9`, calculado `4`, **ERRO**
- Os demais DVs continuam OK (o erro é isolado e identificado corretamente)

---

## Caso 4 — Código de barras de 44 dígitos (equivalente ao Caso 1)

**Entrada:**
```
34198070000001234561234567890123456789012345
```

**Esperado:**
- Reconhecido diretamente como código de barras (sem precisar da linha digitável)
- Status: **VÁLIDO**, mesmos dados do Caso 1 (banco Itaú, R$ 1.234,56, 07/09/1999)
- Resultado indica que a entrada veio de um código de barras

---

## Caso 5 — Documento de arrecadação válido, indicador de valor = 6 (módulo 10)

**Entrada:**
```
816400000013234511111119111111111113111111111113
```

**Esperado:**
- Status: **VÁLIDO**
- Tipo: **Documento de arrecadação**
- Segmento: `1`
- Valor: **R$ 123,45**
- Todos os DVs de bloco (1 a 4) e o DV geral: **OK**, calculados por módulo 10

---

## Caso 6 — Documento de arrecadação válido, indicador de valor = 7 (módulo 11)

**Entrada:**
```
827800000091876599999994999999999997999999999997
```

**Esperado:**
- Status: **VÁLIDO**
- Valor: **R$ 987,65**
- Todos os DVs calculados por **módulo 11** (regra de arrecadação: resto 0/1 → DV 0, resto 10 → DV 1)

---

## Caso 7 — Entrada incompleta

**Entrada:**
```
34191234546789012345
```
(20 dígitos, um prefixo do Caso 1)

**Esperado:**
- Status: **INCOMPLETO**
- Contador exibe **"20 / 47 dígitos"**
- Nenhum diagnóstico de DV é exibido ainda

---

## Caso 8 — Letras misturadas no meio da linha

**Entrada:**
```
3419123454AB6789012345767890123457807000000123456
```
(mesma linha do Caso 1, com "AB" inserido no meio)

**Esperado:**
- A normalização remove as letras automaticamente
- Resultado idêntico ao **Caso 1** (VÁLIDO, Itaú, R$ 1.234,56)

---

## Caso 9 — Pontuação e espaços variados

**Entrada:**
```
34191.23454 67890.123457 67890.123457 8 07000000123456
```

**Esperado:**
- Idêntico ao Caso 1 — a normalização ignora pontos, espaços e qualquer caractere não numérico

---

## Caso 10 — Valor zero

**Entrada:**
```
34191234546789012345767890123457707000000000000
```
(mesma estrutura do Caso 1, com o campo de valor zerado e os DVs recalculados)

**Esperado:**
- Não quebra a interface
- Valor exibido: **R$ 0,00**
- Status: **VÁLIDO** (estrutura e DVs continuam corretos, só o valor é zero)

---

## Caso 11 — Fator de vencimento no ciclo novo (pós 22/02/2025)

**Entrada:**
```
34191234546789012345767890123457510000000123456
```
(mesma linha do Caso 1, com fator de vencimento = `1000`)

**Esperado:**
- Status: **VÁLIDO**
- Vencimento: **22/02/2025**
- Um **aviso** deve aparecer explicando que a data assume o ciclo pós-22/02/2025 e que boletos antigos com fator ≥ 1000 emitidos antes dessa data podem exibir uma data incorreta

---

## Resumo rápido para conferência

| Caso | Status esperado | Observação principal |
|---|---|---|
| 1 | VÁLIDO | Linha de cobrança normal |
| 2 | INVÁLIDO | Dígito de dado alterado |
| 3 | INVÁLIDO | DV de campo alterado, identificado no diagnóstico |
| 4 | VÁLIDO | Código de barras (44) reconhecido direto |
| 5 | VÁLIDO | Arrecadação, módulo 10 |
| 6 | VÁLIDO | Arrecadação, módulo 11 |
| 7 | INCOMPLETO | Contador 20/47 |
| 8 | VÁLIDO | Letras ignoradas na normalização |
| 9 | VÁLIDO | Pontuação ignorada na normalização |
| 10 | VÁLIDO | Valor R$ 0,00 sem quebrar |
| 11 | VÁLIDO | Vencimento no ciclo 2025 + aviso |
