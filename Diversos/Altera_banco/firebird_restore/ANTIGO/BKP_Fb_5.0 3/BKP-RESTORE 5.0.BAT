@ECHO OFF
COLOR A
CLS
ECHO.Matrix Reloaded
ECHO.
ECHO.Renomendo o Banco de Dados
PAUSE
ren TGA.FDB TGAANTIGO.FDB
ECHO.
ECHO.Verificando Erros
PAUSE
gfix -v -full TGAANTIGO.FDB -user sysdba -password masterkey
gfix -v -n TGAANTIGO.FDB -user sysdba -password masterkey
gfix -v -n -i TGAANTIGO.FDB -user sysdba -password masterkey
gfix -me -i TGAANTIGO.FDB -user sysdba -password masterkey
gfix -sweep TGAANTIGO.FDB -user sysdba -password masterkey
ECHO.
ECHO.Startar Backup
PAUSE
gbak -b -v -g -l TGAANTIGO.FDB TGA.fbk -user sysdba -password masterkey
ECHO.
ECHO.Startar Restore
PAUSE
gbak -create -v -p 16384 TGA.fbk TGA.FDB -user sysdba -password masterkey
ECHO.
ECHO.Verificando Banco Novo
PAUSE
gfix -v -full TGA.FDB -user sysdba -password masterkey
ECHO.
ECHO.Concluir
pause