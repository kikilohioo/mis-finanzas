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
                    <h3 style="color: #33764b; text-transform: uppercase">{{$title}}</h3>
                </td>
                <td align="right">
                     <img src="https://fsgestion.montesdelplata.com.uy/api/assets/logo-mdp.png" />
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div style="padding: 12px">
                        <p>Estimado/a <strong>{{$visitante['Nombres']}} {{$visitante['Apellidos']}}</strong></p>
                        <p>Tiene pendiente la realización de una inducción presencial el día <strong>{{date_format($solicitud->FechaHoraDesde, 'd/m/Y')}}</strong> correspondiente a la solicitud realizada por <strong>{{$solicitud['Solicitante']['Nombre']}}</strong>, para que Ud. ingrese al complejo industrial Punta Pereira con motivo: <strong>{{$solicitud['Motivo']}}</strong>.</p>
                        <p>Una vez que se encuentre en la oficina de acceso, será notificado para coordinar/realizar la misma.</p>
                        <p>Si desea información adicional de cómo llegar <a href="https://goo.gl/maps/G2e8Yi6wNNkwjrPaA" target="_blank">ingrese aquí</a>.</p>
                        <p>Ante cualquier duda sobre el proceso comuníquese con el área de Acceso Zona Franca Punta Pereira (<a href="tel:+59845775089" target="_blank">+598 4577 5089</a> - <a href="mailto:acceso.zfpp@montesdelplata.com.uy" target="_blank">acceso.zfpp@montesdelplata.com.uy</a>>).</p>
                        <p>Saludos</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>