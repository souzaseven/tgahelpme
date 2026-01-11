program Project1;

uses
  Vcl.Forms,
  teste in '..\..\..\OneDrive\Documentos\Embarcadero\Studio\Projects\teste.pas' {Form1};

{$R *.res}

begin
  Application.Initialize;
  Application.MainFormOnTaskbar := True;
  Application.CreateForm(TForm1, Form1);
  Application.Run;
end.
