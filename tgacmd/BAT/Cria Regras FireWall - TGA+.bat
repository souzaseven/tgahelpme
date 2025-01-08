::  Cria Regras de entrada no FireWall do Windows
netsh advfirewall firewall add rule name="FireBird TCP" dir=in action=allow protocol=TCP localport=3050
netsh advfirewall firewall add rule name="FireBird UDP" dir=in action=allow protocol=UDP localport=3050
netsh advfirewall firewall add rule name="TGA-PDV TCP 2517" dir=in action=allow protocol=TCP localport=2517
netsh advfirewall firewall add rule name="TGA-PDV UDP 2517" dir=in action=allow protocol=UDP localport=2517
netsh advfirewall firewall add rule name="TGA-PDV TCP 2525" dir=in action=allow protocol=TCP localport=2525
netsh advfirewall firewall add rule name="TGA-PDV UDP 2525" dir=in action=allow protocol=UDP localport=2525
netsh advfirewall firewall add rule name="TGA-FV-ON TCP 8093" dir=in action=allow protocol=TCP localport=8093
netsh advfirewall firewall add rule name="TGA-FV-ON UDP 8093" dir=in action=allow protocol=UDP localport=8093
netsh advfirewall firewall add rule name="TGA-MOBILE TCP 3010" dir=in action=allow protocol=TCP localport=3010
netsh advfirewall firewall add rule name="TGA-MOBILE UDP 3010" dir=in action=allow protocol=UDP localport=3010
netsh advfirewall firewall add rule name="TGA-ERP TCP 8080" dir=in action=allow protocol=TCP localport=8080
netsh advfirewall firewall add rule name="TGA-ERP UDP 8080" dir=in action=allow protocol=UDP localport=8080
netsh advfirewall firewall add rule name="TGA-ERP-OLD TCP 30500" dir=in action=allow protocol=TCP localport=30500
netsh advfirewall firewall add rule name="TGA-ERP-OLD UDP 30500" dir=in action=allow protocol=UDP localport=30500
netsh advfirewall firewall add rule name="TGA Contabil" dir=in action=allow program="C:\TGA\Contabil.exe" enable=yes
netsh advfirewall firewall add rule name="TGA Estoque" dir=in action=allow program="C:\TGA\Estoque.exe" enable=yes
netsh advfirewall firewall add rule name="TGA Financeiro" dir=in action=allow program="C:\TGA\Financeiro.exe" enable=yes
netsh advfirewall firewall add rule name="TGA Folha" dir=in action=allow program="C:\TGA\Folha.exe" enable=yes
netsh advfirewall firewall add rule name="TGA Hotel" dir=in action=allow program="C:\TGA\Hotel.exe" enable=yes
netsh advfirewall firewall add rule name="TGA PDVOFF" dir=in action=allow program="C:\TGA\PDVOFF\PDVOff.exe" enable=yes
netsh advfirewall firewall add rule name="TGA PDVCLIENT" dir=in action=allow program="C:\TGA\PDVOFF\PDVClient.exe" enable=yes
::  Cria Regras de Saída no FireWall do Windows
netsh advfirewall firewall add rule name="FireBird TCP" dir=out action=allow protocol=TCP localport=3050
netsh advfirewall firewall add rule name="FireBird UDP" dir=out action=allow protocol=UDP localport=3050
netsh advfirewall firewall add rule name="TGA-PDV TCP 2517" dir=out action=allow protocol=TCP localport=2517
netsh advfirewall firewall add rule name="TGA-PDV UDP 2517" dir=out action=allow protocol=UDP localport=2517
netsh advfirewall firewall add rule name="TGA-PDV TCP 2525" dir=out action=allow protocol=TCP localport=2525
netsh advfirewall firewall add rule name="TGA-PDV UDP 2525" dir=out action=allow protocol=UDP localport=2525
netsh advfirewall firewall add rule name="TGA-FV-ON TCP 8093" dir=out action=allow protocol=TCP localport=8093
netsh advfirewall firewall add rule name="TGA-FV-ON UDP 8093" dir=out action=allow protocol=UDP localport=8093
netsh advfirewall firewall add rule name="TGA-MOBILE TCP 3010" dir=out action=allow protocol=TCP localport=3010
netsh advfirewall firewall add rule name="TGA-MOBILE UDP 3010" dir=out action=allow protocol=UDP localport=3010
netsh advfirewall firewall add rule name="TGA-ERP TCP 8080" dir=out action=allow protocol=TCP localport=8080
netsh advfirewall firewall add rule name="TGA-ERP UDP 8080" dir=out action=allow protocol=UDP localport=8080
netsh advfirewall firewall add rule name="TGA-ERP-OLD TCP 30500" dir=out action=allow protocol=TCP localport=30500
netsh advfirewall firewall add rule name="TGA-ERP-OLD UDP 30500" dir=out action=allow protocol=UDP localport=30500
netsh advfirewall firewall add rule name="TGA Contabil" dir=out action=allow program="C:\TGA\Contabil.exe" enable=yes
netsh advfirewall firewall add rule name="TGA Estoque" dir=out action=allow program="C:\TGA\Estoque.exe" enable=yes
netsh advfirewall firewall add rule name="TGA Financeiro" dir=out action=allow program="C:\TGA\Financeiro.exe" enable=yes
netsh advfirewall firewall add rule name="TGA Folha" dir=out action=allow program="C:\TGA\Folha.exe" enable=yes
netsh advfirewall firewall add rule name="TGA Hotel" dir=out action=allow program="C:\TGA\Hotel.exe" enable=yes
netsh advfirewall firewall add rule name="TGA PDVOFF" dir=out action=allow program="C:\TGA\PDVOFF\PDVOff.exe" enable=yes
netsh advfirewall firewall add rule name="TGA PDVCLIENT" dir=out action=allow program="C:\TGA\PDVOFF\PDVClient.exe" enable=yes
Pause