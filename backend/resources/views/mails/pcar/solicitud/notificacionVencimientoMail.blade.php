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
                    <h3 style="color: #33764b">PERMISO PARA CIRCULAR EN ÁREA RESTRINGIDA</h3>
                </td>
                <td align="right">
                     <img src="https://fsgestion.montesdelplata.com.uy/api/assets/logo-mdp.png" />
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div style="padding: 12px">
                        Estimado/a <b>{{$solicitud->PersonaContacto}}</b>, <br />
                        <br />
                        Le recordamos que el permiso de circulación para <b>{{$solicitud->Matricula}}</b> vence el día <b>{{date_format($solicitud->Hasta, 'd/m/Y')}}</b>.<br />
                        <br />
                        Saludos
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>