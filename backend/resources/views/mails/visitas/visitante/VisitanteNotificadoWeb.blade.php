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
                        <p>Para culminar el proceso de ingreso al complejo industrial Punta Pereira deberá realizar la inducción web a través del siguiente enlace antes de día <strong>{{date_format($solicitud->FechaHoraDesde, 'd/m/Y')}}</strong>:</p>
                        <p><a href="https://cursos.montesdelplata.com.uy/enrol/index.php?id=3" target="_blank">https://cursos.montesdelplata.com.uy/enrol/index.php?id=3</a></p>
                        <p>Adjunto encontrará los pasos para realizarla.</p>
                        <p><strong>Luego de finalizada la misma deberá notificar al personal del <strong>Acceso ZFPP</strong> &lt;<a href="mailto:acceso.zfpp@montesdelplata.com.uy" target="_blank">acceso.zfpp@montesdelplata.com.uy</a>&gt; para que apruebe su ingreso.</strong></p>
                        <p>El acceso al complejo industrial Punta Pereira es individual. Por favor, si viene acompañado contáctese con la persona responsabile de su visita (<strong>{{$solicitud['PersonaContacto']}}</strong> - <strong>{{$solicitud['TelefonoContacto']}}</strong>) para gestionar el ingreso de su acompañante.</p>
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