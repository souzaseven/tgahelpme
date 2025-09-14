// Dados dos operadores
const operatorsData = [
    {
        name: "Emerson Hoffmann Cassemiro",
        monitor: "Alex Sandro Braulio",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/23"
    },
    {
        name: "Jesiane Gabriele Campos da Silva",
        monitor: "Alex Sandro Braulio",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/29"
    },
    {
        name: "João Pedro Alves de Oliveira",
        monitor: "Alex Sandro Braulio",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/31"
    },
    {
        name: "Lindomar Gimenes Junior",
        monitor: "Alex Sandro Braulio",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/32"
    },
    {
        name: "Luiz Henrique Camargo Moura",
        monitor: "Alex Sandro Braulio",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/33"
    },
    {
        name: "Matheus Xavier dos Santos",
        monitor: "Alex Sandro Braulio",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/37"
    },
    {
        name: "Pedro Henrique de Souza Egues",
        monitor: "Alex Sandro Braulio",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/41"
    },
    {
        name: "Rafael Felipe Santos Machado",
        monitor: "Alex Sandro Braulio",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/72"
    },
    {
        name: "Renan Canachiro dos Santos",
        monitor: "Alex Sandro Braulio",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/75"
    },
    {
        name: "Rodrigo de Moraes Ribeiro",
        monitor: "Alex Sandro Braulio",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/42"
    },
    {
        name: "Vinicius D'César Lira Ladeia",
        monitor: "Alex Sandro Braulio",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/45"
    },
    {
        name: "Antonio Oliveira",
        monitor: "Daniel Feix",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/20"
    },
    {
        name: "Anderson de Souza",
        monitor: "Daniel Feix",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/18"
    },
    {
        name: "Carlos Eduardo Nascimento Silva",
        monitor: "Daniel Feix",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/59"
    },
    {
        name: "Gabriel Sanini",
        monitor: "Daniel Feix",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/26"
    },
    {
        name: "Jessica Bergue",
        monitor: "Daniel Feix",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/30"
    },
    {
        name: "Moisés Vinicius da Silva Moura",
        monitor: "Daniel Feix",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/38"
    },
    {
        name: "Pablo de Freitas Sanches de Souza",
        monitor: "Daniel Feix",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/40"
    },
    {
        name: "Suzana Ferreira da Silva Leão",
        monitor: "Daniel Feix",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/43"
    },
    {
        name: "Uanderson Almeida",
        monitor: "Daniel Feix",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/44"
    },
    {
        name: "Victor Luan Francisco de Souza",
        monitor: "Daniel Feix",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/74"
    },
    {
        name: "Alexsandro Matsushita",
        monitor: "Willian Pereira Reis",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/17"
    },
    {
        name: "Andrey Mayer",
        monitor: "Willian Pereira Reis",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/19"
    },
    {
        name: "Daniel Magalhães Batista",
        monitor: "Willian Pereira Reis",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/21"
    },
    {
        name: "Diogo de Lima Neves",
        monitor: "Willian Pereira Reis",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/22"
    },
    {
        name: "Felipe Vargas Maldonado de Souza",
        monitor: "Willian Pereira Reis",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/24"
    },
    {
        name: "Flavio Vinicius da Costa Marchetti",
        monitor: "Willian Pereira Reis",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/25"
    },
    {
        name: "Igor Henrique Lazaroto",
        monitor: "Willian Pereira Reis",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/27"
    },
    {
        name: "Matheus Feliphe Silva Siqueira",
        monitor: "Willian Pereira Reis",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/35"
    },
    {
        name: "Matheus Henrique Moreira",
        monitor: "Willian Pereira Reis",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/36"
    },
    {
        name: "Wender Domingos de Jesus",
        monitor: "Willian Pereira Reis",
        queues: ["Suporte Matriz", "Fila Matriz Chat/Whats"],
        link: "https://tgasistemas.evolux.io/callcenter/agent/edit/46"
    }
];