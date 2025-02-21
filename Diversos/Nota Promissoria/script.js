 // Evento para imprimir os dados preenchidos
        document.getElementById('imprimir').addEventListener('click', function() {
            const inputs = document.querySelectorAll('.recibo input');
            let emptyFields = false;

            inputs.forEach(input => {
                if (input.value.trim() === '') {
                    emptyFields = true;
                }
            });

            if (emptyFields) {
                alert("Por favor, preencha todos os campos antes de imprimir.");
                return;
            }

            window.print();
        });

        // Evento para limpar os campos preenchidos
        document.getElementById('limpar').addEventListener('click', function() {
            const inputs = document.querySelectorAll('.recibo input');
            inputs.forEach(input => input.value = '');
            inputs[0].focus();  // Coloca o foco no primeiro campo após limpar
        });

        // Evento para imprimir a nota em branco
        document.getElementById('imprimir-em-branco').addEventListener('click', function() {
            const inputs = document.querySelectorAll('.recibo input');
            inputs.forEach(input => input.value = '');  // Limpa os campos antes de imprimir em branco
            window.print();
        });