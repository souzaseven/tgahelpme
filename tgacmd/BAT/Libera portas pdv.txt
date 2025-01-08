@echo off
echo Configurando regras de firewall para as portas 2517, 2525, 3050 (UDP e TCP)...

:: Porta 2517 (Entrada e Saída TCP/UDP)
netsh advfirewall firewall add rule name="TGA-PDV Entrada TCP 2517" dir=in action=allow protocol=TCP localport=2517
netsh advfirewall firewall add rule name="TGA-PDV Saída TCP 2517" dir=out action=allow protocol=TCP localport=2517
netsh advfirewall firewall add rule name="TGA-PDV Entrada UDP 2517" dir=in action=allow protocol=UDP localport=2517
netsh advfirewall firewall add rule name="TGA-PDV Saída UDP 2517" dir=out action=allow protocol=UDP localport=2517

:: Porta 2525 (Entrada e Saída TCP/UDP)
netsh advfirewall firewall add rule name="TGA-PDV Entrada TCP 2525" dir=in action=allow protocol=TCP localport=2525
netsh advfirewall firewall add rule name="TGA-PDV Saída TCP 2525" dir=out action=allow protocol=TCP localport=2525
netsh advfirewall firewall add rule name="TGA-PDV Entrada UDP 2525" dir=in action=allow protocol=UDP localport=2525
netsh advfirewall firewall add rule name="TGA-PDV Saída UDP 2525" dir=out action=allow protocol=UDP localport=2525

:: Porta 3050 (Entrada e Saída TCP/UDP)
netsh advfirewall firewall add rule name="TGA-PDV Entrada TCP 3050" dir=in action=allow protocol=TCP localport=3050
netsh advfirewall firewall add rule name="TGA-PDV Saída TCP 3050" dir=out action=allow protocol=TCP localport=3050
netsh advfirewall firewall add rule name="TGA-PDV Entrada UDP 3050" dir=in action=allow protocol=UDP localport=3050
netsh advfirewall firewall add rule name="TGA-PDV Saída UDP 3050" dir=out action=allow protocol=UDP localport=3050

echo Regras de firewall configuradas com sucesso para as portas 2517, 2525, 3050 (UDP e TCP)!
