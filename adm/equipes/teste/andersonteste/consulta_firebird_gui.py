# Exemplo de interface gráfica com Tkinter para consulta ao Firebird
# Requer o pacote 'fdb' (pip install fdb)
# Python 3.x

import tkinter as tk
from tkinter import ttk, messagebox
import datetime
import calendar
import fdb

# Configurações de conexão
DATABASE = r'C:\TGA\CELEIRO.FDB'
USER = 'SYSDBA'
PASSWORD = 'masterkey'


# Opções de consulta
TIPOS_CONSULTA = {
    'Clientes ativos': "SELECT * FROM FCFO WHERE ATIVO = 'T' AND TIPO = 'C'",
    'Clientes inativos': "SELECT * FROM FCFO WHERE ATIVO = 'F' AND TIPO = 'C'",
    'Todos clientes': "SELECT * FROM FCFO WHERE TIPO = 'C'",
    'Fornecedores': "SELECT * FROM FCFO WHERE TIPO = 'F'",
    'Ambos (Cliente/Fornecedor)': "SELECT * FROM FCFO WHERE TIPO = 'A'",
    'Todos cadastros (principais colunas)': "SELECT * FROM FCFO",
    'Todos dados da tabela': "SELECT * FROM FCFO",
}

COLUNAS_PRINCIPAIS = [
    'CODCFO', 'NOMEFANTASIA', 'RAZAOSOCIAL', 'CNPJCPF', 'TIPO', 'ATIVO', 'EMAIL', 'TELEFONE', 'ENDERECO', 'CIDADE', 'UF'
]



# Nova janela para exibir relatório tabular
class RelatorioWindow(tk.Toplevel):
    CAMPOS_RELATORIO = [
        ('CODCFO', 'Código'),
        ('NOMEFANTASIA', 'Nome Fantasia'),
        ('NOME', 'Razão Social'),
        ('RUA', 'Endereço'),
        ('NUMERO', 'Número'),
        ('COMPLEMENTO', 'Complemento'),
        ('BAIRRO', 'Bairro'),
        ('CIDADE', 'Cidade'),
        ('CODETD', 'UF'),
        ('CGCCFO', 'CNPJ/CPF'),
        ('EMAIL', 'E-mail'),
        ('TELEFONE', 'Telefone 1'),
        ('TELEFONE2', 'Telefone 2'),
        ('FAX', 'Fax'),
        ('DATANASC', 'Data Nasc.'),
        ('CONTATO', 'Contato'),
        ('CEP', 'Cep'),
    ]

# ==== DASHBOARD WINDOW ====
class DashboardWindow(tk.Toplevel):
    def __init__(self, master=None):
        super().__init__(master)
        self.title("Dashboard - Análise de Vendas")
        self.geometry("1100x650")
        self.configure(bg="#f7f7f7")
        self.create_widgets()

    def create_widgets(self):
        # Top frame: Ano/Mês e filial
        top_frame = tk.Frame(self, bg="#f7f7f7")
        top_frame.pack(fill=tk.X, padx=20, pady=(15, 5))

        tk.Label(top_frame, text="Dados referente à filial:", font=("Segoe UI", 10, "bold"), bg="#f7f7f7").pack(side=tk.LEFT)
        tk.Label(top_frame, text="1 - RAZAO SOCIAL", font=("Segoe UI", 10), bg="#f7f7f7").pack(side=tk.LEFT, padx=(5, 30))

        # Ano
        tk.Label(top_frame, text="Ano:", font=("Segoe UI", 10), bg="#f7f7f7").pack(side=tk.LEFT)
        self.ano_var = tk.StringVar()
        ano_atual = datetime.datetime.now().year
        anos = [str(ano) for ano in range(ano_atual-5, ano_atual+2)]
        self.ano_cb = ttk.Combobox(top_frame, textvariable=self.ano_var, values=anos, width=6, state="readonly")
        self.ano_cb.set(str(ano_atual))
        self.ano_cb.pack(side=tk.LEFT, padx=(2, 15))

        # Mês
        tk.Label(top_frame, text="Mês:", font=("Segoe UI", 10), bg="#f7f7f7").pack(side=tk.LEFT)
        self.mes_var = tk.StringVar()
        meses = list(calendar.month_name)[1:]
        self.mes_cb = ttk.Combobox(top_frame, textvariable=self.mes_var, values=meses, width=10, state="readonly")
        self.mes_cb.set(meses[datetime.datetime.now().month-1])
        self.mes_cb.pack(side=tk.LEFT, padx=(2, 15))

        # Título
        title_frame = tk.Frame(self, bg="#f7f7f7")
        title_frame.pack(fill=tk.X, padx=20, pady=(0, 10))
        tk.Label(title_frame, text="Análise de Vendas", font=("Segoe UI", 28, "bold"), fg="#222", bg="#f7f7f7").pack(side=tk.LEFT)
        tk.Label(title_frame, text=str(datetime.datetime.now().year), font=("Segoe UI", 28, "bold"), fg="#222", bg="#f7f7f7").pack(side=tk.RIGHT)

        # Indicadores (cards)
        cards_frame = tk.Frame(self, bg="#f7f7f7")
        cards_frame.pack(fill=tk.X, padx=20, pady=(0, 10))

        indicadores = [
            ("Nº de Vendas", "0", "#1abc9c"),
            ("Número de Devoluções", "0", "#f1c40f"),
            ("Total de Devoluções", "R$ 0,00", "#e74c3c"),
            ("Lucro Bruto", "R$ 0,00", "#16a085"),
            ("Total Geral Vendas", "R$ 0,00", "#3498db"),
            ("Nº de Orçamentos", "0", "#2980b9"),
            ("Orçamentos Atendidos", "0", "#27ae60"),
            ("Orçamentos Pendentes", "0", "#c0392b"),
            ("Quantidade Produtos Vendidos", "0,00", "#8e44ad"),
            ("Ticket Médio", "R$ NAN", "#7f8c8d"),
        ]

        # 2 linhas de cards
        for i in range(2):
            row = tk.Frame(cards_frame, bg="#f7f7f7")
            row.pack(fill=tk.X, pady=3)
            for j in range(5):
                idx = i*5 + j
                if idx >= len(indicadores):
                    break
                nome, valor, cor = indicadores[idx]
                card = tk.Frame(row, bg=cor, width=200, height=70, bd=0, relief=tk.RIDGE)
                card.pack(side=tk.LEFT, padx=7, pady=2, fill=tk.BOTH, expand=True)
                card.pack_propagate(False)
                tk.Label(card, text=nome, font=("Segoe UI", 11, "bold"), fg="#fff", bg=cor).pack(anchor="w", padx=10, pady=(8,0))
                tk.Label(card, text=valor, font=("Segoe UI", 18, "bold"), fg="#fff", bg=cor).pack(anchor="w", padx=10, pady=(0,8))

        # Placeholder para gráfico
        graph_frame = tk.Frame(self, bg="#f7f7f7", height=250)
        graph_frame.pack(fill=tk.BOTH, expand=True, padx=20, pady=(10, 10))
        tk.Label(graph_frame, text="[Gráfico de Vendas/Devoluções aqui]", font=("Segoe UI", 13, "italic"), fg="#888", bg="#f7f7f7").pack(expand=True)

        # Rodapé
        footer = tk.Frame(self, bg="#f7f7f7")
        footer.pack(fill=tk.X, padx=20, pady=(0, 10))
        tk.Label(footer, text="Mês de " + self.mes_var.get(), font=("Segoe UI", 11), fg="#444", bg="#f7f7f7").pack(side=tk.LEFT)

        # Atualiza rodapé ao mudar mês
        def on_mes_change(event=None):
            footer.pack_forget()
            footer.pack(fill=tk.X, padx=20, pady=(0, 10))
            for widget in footer.winfo_children():
                widget.destroy()
            tk.Label(footer, text="Mês de " + self.mes_var.get(), font=("Segoe UI", 11), fg="#444", bg="#f7f7f7").pack(side=tk.LEFT)
        self.mes_cb.bind("<<ComboboxSelected>>", on_mes_change)

        # Futuro: carregar dados reais ao mudar ano/mês

class FirebirdApp(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title('Consulta de Cadastros FCFO')
        self.geometry('900x500')


        # Menu de relatórios
        menubar = tk.Menu(self)
        relatorio_menu = tk.Menu(menubar, tearoff=0)
        relatorio_menu.add_command(label='Cliente/Fornecedor - Completo', command=self.abrir_relatorio)
        menubar.add_cascade(label='Relatórios', menu=relatorio_menu)
        self.config(menu=menubar)

        self.label = tk.Label(self, text='Selecione o tipo de cadastro:')
        self.label.pack(pady=5)
    def abrir_relatorio(self):
        RelatorioWindow(self)

        self.tipo_var = tk.StringVar(value='Todos clientes')
        self.combo = ttk.Combobox(self, textvariable=self.tipo_var, values=list(TIPOS_CONSULTA.keys()), state='readonly', width=40)
        self.combo.pack(pady=5)


        filtro_frame = tk.Frame(self)
        filtro_frame.pack(pady=5)
        tk.Label(filtro_frame, text='Filtrar por texto:').pack(side='left')
        self.filtro_var = tk.StringVar()
        self.filtro_entry = tk.Entry(filtro_frame, textvariable=self.filtro_var, width=40)
        self.filtro_entry.pack(side='left', padx=5)
        self.filtro_entry.bind('<KeyRelease>', lambda e: self.aplicar_filtro())

        self.btn = tk.Button(self, text='Consultar', command=self.consultar)
        self.btn.pack(pady=5)


        frame_tree = tk.Frame(self)
        frame_tree.pack(expand=True, fill='both', padx=10, pady=10)

        self.tree = ttk.Treeview(frame_tree)
        self.tree.pack(side='left', expand=True, fill='both')

        # Barras de rolagem
        vsb = ttk.Scrollbar(frame_tree, orient='vertical', command=self.tree.yview)
        vsb.pack(side='right', fill='y')
        hsb = ttk.Scrollbar(self, orient='horizontal', command=self.tree.xview)
        hsb.pack(fill='x')
        self.tree.configure(yscroll=vsb.set, xscroll=hsb.set)

    def consultar(self):
        self._dados = []  # Armazena todos os dados para filtragem
        sql = TIPOS_CONSULTA.get(self.tipo_var.get(), TIPOS_CONSULTA['Todos clientes'])
        try:
            con = fdb.connect(dsn=DATABASE, user=USER, password=PASSWORD)
            cur = con.cursor()
            cur.execute(sql)
            rows = cur.fetchall()
            desc = [d[0] for d in cur.description]

            # Se a opção for 'Todos dados da tabela', mostra todas as colunas
            if self.tipo_var.get() == 'Todos dados da tabela':
                colunas_exibir = desc
            else:
                colunas_exibir = [c for c in COLUNAS_PRINCIPAIS if c in desc]
                if not colunas_exibir:
                    colunas_exibir = desc  # fallback para todas

            idxs = [desc.index(c) for c in colunas_exibir]

            self._dados = [[row[i] for i in idxs] for row in rows]
            self._colunas_exibir = colunas_exibir
            self._atualizar_tree(self._dados)

            cur.close()
            con.close()
        except Exception as e:
            messagebox.showerror('Erro', str(e))

    def _atualizar_tree(self, dados):
        self.tree.delete(*self.tree.get_children())
        self.tree['columns'] = self._colunas_exibir
        self.tree['show'] = 'headings'
        largura = 120
        if len(self._colunas_exibir) > 10:
            largura = 100
        for col in self._colunas_exibir:
            self.tree.heading(col, text=col)
            self.tree.column(col, width=largura, anchor='w', stretch=True)
        for row in dados:
            self.tree.insert('', 'end', values=row)

    def aplicar_filtro(self):
        texto = self.filtro_var.get().lower()
        if not hasattr(self, '_dados') or not self._dados:
            return
        if not texto:
            self._atualizar_tree(self._dados)
            return
        filtrados = []
        for row in self._dados:
            for campo in row:
                if campo is not None and texto in str(campo).lower():
                    filtrados.append(row)
                    break
        self._atualizar_tree(filtrados)

class FirebirdApp(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title('Consulta de Cadastros FCFO')
        self.geometry('900x500')

        # Menu de relatórios
        menubar = tk.Menu(self)
        relatorio_menu = tk.Menu(menubar, tearoff=0)
        relatorio_menu.add_command(label='Cliente/Fornecedor - Completo', command=self.abrir_relatorio)
        relatorio_menu.add_command(label='Dashboard de Vendas', command=self.abrir_dashboard)
        menubar.add_cascade(label='Relatórios', menu=relatorio_menu)
        self.config(menu=menubar)

        self.label = tk.Label(self, text='Selecione o tipo de cadastro:')
        self.label.pack(pady=5)
    def abrir_relatorio(self):
        RelatorioWindow(self)

    def abrir_dashboard(self):
        DashboardWindow(self)

if __name__ == '__main__':
    app = FirebirdApp()
    app.mainloop()
