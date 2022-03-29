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
                    <h3 style="color: #33764b; text-transform: uppercase">Solicitud de ingreso al complejo industrial Punta Pereira pendiente de inducción presencial</h3>
                </td>
                <td align="right">
                     <img src="https://fsgestion.montesdelplata.com.uy/api/assets/logo-mdp.png" />
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div style="padding: 12px">
                        <p>Estimados Técnicos SYSO</p>
                        <p>Tienen pendiente la realización de una inducción presencial el día <strong>{{date_format($solicitud->FechaHoraDesde, 'd/m/Y')}}</strong> correspondiente a la solicitud realizada por <strong>{{$solicitud['solicitante']['Nombre']}}</strong>, para que <strong>{{$visitante['Nombres']}} {{$visitante['Apellidos']}}</strong> documento: <strong>{{$visitante['Documento']}}</strong>, perteneciente a la empresa: <strong>{{$solicitud['EmpresaVisitante']}}</strong> ingrese al complejo industrial Punta Pereira con motivo: <strong>{{$solicitud['Motivo']}}</strong>.</p>
                        <p>Una vez que <strong>{{$visitante['Nombres']}} {{$visitante['Apellidos']}}</strong> se encuentre en la oficina de acceso, serán notificado para coordinar/realizar la misma.</p>
                        <p>Saludos</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>