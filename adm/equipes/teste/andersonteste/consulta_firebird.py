# Exemplo de conexão Python com Firebird e exibição de dados no terminal
# Requer o pacote 'fdb' (instale com: pip install fdb)

import fdb

# Configurações de conexão
DATABASE = r'C:\TGA\CELEIRO.FDB'
USER = 'SYSDBA'
PASSWORD = 'masterkey'

# Conecta ao banco
con = fdb.connect(dsn=DATABASE, user=USER, password=PASSWORD)
cur = con.cursor()

# Consulta exemplo (ajuste o nome da tabela para uma existente)
cur.execute('SELECT FIRST 10 * FROM SUA_TABELA')

print('Resultados:')
for row in cur.fetchall():
    print(row)

cur.close()
con.close()
