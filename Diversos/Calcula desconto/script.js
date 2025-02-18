  // Seleciona os elementos do DOM
        const originalPriceInput = document.getElementById('originalPrice');
        const discountPercentageInput = document.getElementById('discountPercentage');

        // Elementos da tabela
        const originalValueTd = document.getElementById('originalValue');
        const discountValueTd = document.getElementById('discountValue');
        const finalValueTd = document.getElementById('finalValue');
        const differenceValueTd = document.getElementById('differenceValue');

        // Função para calcular o desconto
        function calcularDesconto() {
            const originalPrice = parseFloat(originalPriceInput.value);
            const discountPercentage = parseFloat(discountPercentageInput.value);

            // Valida se os campos possuem valores corretos
            if (isNaN(originalPrice) || isNaN(discountPercentage) || originalPrice <= 0 || discountPercentage < 0) {
                originalValueTd.textContent = '-';
                discountValueTd.textContent = '-';
                finalValueTd.textContent = '-';
                differenceValueTd.textContent = '-';
                return;
            }

            // Cálculo do valor com desconto e a diferença
            const discountAmount = (originalPrice * discountPercentage) / 100;
            const finalPrice = originalPrice - discountAmount;

            // Atualiza a tabela com os valores calculados
            originalValueTd.textContent = `R$ ${originalPrice.toFixed(2)}`;
            discountValueTd.textContent = `${discountPercentage.toFixed(2)}%`;
            finalValueTd.textContent = `R$ ${finalPrice.toFixed(2)}`;
            differenceValueTd.textContent = `R$ ${discountAmount.toFixed(2)}`;
        }

        // Adiciona event listeners aos inputs para calcular automaticamente ao digitar
        originalPriceInput.addEventListener('input', calcularDesconto);
        discountPercentageInput.addEventListener('input', calcularDesconto);