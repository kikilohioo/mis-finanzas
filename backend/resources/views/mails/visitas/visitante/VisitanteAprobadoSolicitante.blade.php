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
                        <p>Estimado/a <strong>{{$solicitante['Nombre']}}</strong></p>
                        <p><strong>{{$usuario['Nombre']}}</strong> ha aprobado su solicitud de <strong>{{$tipoVisita}}</strong> para que Nombre: <strong>{{$visitante['Nombres']}} {{$visitante['Apellidos']}}</strong>, Documento: <strong>{{$visitante['Documento']}}</strong>, Perteneciente a la empresa: <strong>{{$solicitud['EmpresaVisitante']}}</strong> ingrese al complejo industrial Punta Pereira con motivo: {{$solicitud['Motivo']}}, en la fecha ({{date_format($solicitud['FechaHoraDesde'], 'd/m/Y')}} - {{date_format($solicitud['FechaHoraHasta'], 'd/m/Y')}}).</p>
                        <p><strong>{{$visitante['Nombres']}} {{$visitante['Apellidos']}}</strong> recibió un correo con su código QR e instrucciones para ingresar al complejo industrial Punta Pereira.</p>
                        <p>Saludos</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>