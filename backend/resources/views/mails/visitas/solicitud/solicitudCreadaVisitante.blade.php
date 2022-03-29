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
                    <h3 style="color: #33764b; text-transform: uppercase">Solicitud de ingreso al complejo industrial Punta Pereira</h3>
                </td>
                <td align="right">
                     <img src="https://fsgestion.montesdelplata.com.uy/api/assets/logo-mdp.png" />
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div style="padding: 12px">
                        <p>Estimado/a <strong>{{$persona->Nombres}} {{$persona->Apellidos}}</strong></p>
                        <p><strong>{{$solicitud->Solicitante->Nombre}}</strong> ha realizado una solicitud para usted (nombre: <strong>{{$persona->Nombres.' '.$persona->Apellidos}}</strong>, documento: <strong>{{$persona->Documento}}</strong>, perteneciente a la empresa: <strong>{{$solicitud->EmpresaVisitante}}</strong>) ingrese al complejo industrial Punta Pereira con motivo: <strong>{{$solicitud->Motivo}}</strong>, en la fecha (<strong>{{date_format($solicitud->FechaHoraDesde, 'd/m/Y')}}</strong> - <strong>{{date_format($solicitud->FechaHoraHasta, 'd/m/Y')}}</strong>).</p>
                        <p>La solicitud se encuentra en proceso de autorización.</p>
                        <p>Se le notificará vía mail cuando la misma sea aprobada o rechazada.</p>
                        <p>Si sus datos son incorrectos comuníquese con la persona responsable de su visita en el complejo industrial Punta Pereira: <strong>{{$solicitud->PersonaContacto}}</strong> - <strong>{{$solicitud->TelefonoContacto}}</strong>.</p>
                        <p>Saludos</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>