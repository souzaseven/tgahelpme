@echo off
echo Configurando regras de firewall para o Atualizador TGA Funcionar...

netsh advfirewall firewall add rule name="Atualizador TGA Entrada" dir=in protocol=TCP localport=20002 action=allow
netsh advfirewall firewall add rule name="Atualizador TGA Saída" dir=out protocol=TCP localport=20002 action=allow

echo Regras de firewall configuradas com sucesso!
exit
