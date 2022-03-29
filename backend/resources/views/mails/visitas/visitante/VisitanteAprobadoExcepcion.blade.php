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
                        <p>Su solicitud de ingreso al complejo industrial Punta Pereira fue aprobada.</p>
                        <p>Para facilitar su ingreso al complejo industrial Punta Pereira por favor verifique que sus datos sean correctos:</p>
                        <p>Nombre completo: <strong>{{$visitante['Nombres']}} {{$visitante['Apellidos']}}</strong><br />Documento de identidad: <strong>{{$visitante['Documento']}}</strong><br />Período habilitado para ingreso: <strong>{{date_format($solicitud['FechaHoraDesde'], 'd/m/Y')}}</strong> - <strong>{{date_format($solicitud['FechaHoraHasta'], 'd/m/Y')}}</strong></p>
                        <p>Si sus datos son incorrecto por favor comuníquese con la persona responsable de su visita (<strong>{{$visitante['Solicitud']['PersonaContacto']}}</strong> - <strong>{{$visitante['Solicitud']['TelefonoContacto']}}</strong>).</p>
                        <p>Adjunto encontrará las pautas básicas para visitas y su pase de acceso con un <strong>código QR que deberá presentar desde su celular o impreso junto a su documento de identidad al ingresar.</strong></p>
                        <p><strong>Es importate que lo conserve durante su visita ya que podrá ser requerido por el personal de Montes del Plata.</strong></p>
                        <p>Esta acreditación es individual. Por favor, si viene acompañado contáctese con la persona responsable de su visita para gestionar el ingreso de su acompañante.</p>
                        <p>Si desea información adicional de cómo llegar <a href="https://goo.gl/maps/G2e8Yi6wNNkwjrPaA" target="_blank">ingrese aquí</a>.</p>
                        <p>Ante cualquier duda comuníquese con el área de Acceso Zona Franca (<a href="tel:+59845775089" target="_blank">+598 4577 5089</a> - <a href="mailto:acceso.zfpp@montesdelplata.com.uy">acceso.zfpp@montesdelplata.com.uy</a>).</p>
                        <p>Saludos</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>