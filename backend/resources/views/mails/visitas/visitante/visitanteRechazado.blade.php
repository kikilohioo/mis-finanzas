<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>FSAcceso</title>
</head>

<body margin="0" padding="12" style="background: white; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
    <table align="center" cellspacing="0" margin="auto" width="550">
        <tbody>
            <tr>
                <td style="padding: 0 12px;">
                    <h3 style="color: #33764b; text-transform: uppercase">Solicitud de ingreso al complejo industrial Punta Pereira rechazada</h3>
                </td>
                <td align="right">
                     <img src="https://fsgestion.montesdelplata.com.uy/api/assets/logo-mdp.png" />
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div style="padding: 12px">
                        <p>Estimado/a <strong>{{$solicitante['Nombre']}}</strong></p>
                        <p>Su solicitud de <strong>{{$tipoVisita}}</strong> para que Nombre: <strong>{{$visitante->Nombres}} {{$visitante->Apellidos}}</strong>, Documento: <strong>{{$solicitud['Documento']}}</strong>, Perteneciente a la empresa: <strong>{{$solicitud['EmpresaVisitante']}}</strong> ingrese al complejo industrial Punta Pereira con motivo: <strong>{{$solicitud['Motivo']}}</strong>, en la fecha (<strong>{{date_format($solicitud->FechaHoraDesde, 'd/m/Y')}}</strong> - <strong>{{date_format($solicitud->FechaHoraHasta, 'd/m/Y')}}</strong>) ha sido rechazada por el siguiente motivo: <strong>{{$visitante->ComentariosRechazo}}</strong>.</p>
                        <p>Saludos</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>
 