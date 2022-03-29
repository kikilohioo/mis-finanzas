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
                    <h3 style="color: #33764b; text-transform: uppercase">Nueva solicitud de {{$tipoVisita}} creada recientemente</h3>
                </td>
                <td align="right">
                     <img src="https://fsgestion.montesdelplata.com.uy/api/assets/logo-mdp.png" />
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div style="padding: 12px">
                        <p>Estimado/a <strong>{{$autorizante->Nombre}}</strong></p>
                        <p><strong>{{$solicitud->solicitante->Nombre}}</strong> ha realizado una solicitud de <strong>{{$tipoVisita}}</strong> para ingresar al complejo industrial Punta Pereira con motivo: <strong>{{$solicitud->motivo}}</strong>, en la fecha (<strong>{{date_format($solicitud->FechaHoraDesde, 'd/m/Y')}}</strong> - <strong>{{date_format($solicitud->FechaHoraHasta, 'd/m/Y')}}</strong>).</p>
                        <p>Personas en la solicitud: <br />
                        @foreach ($personas as $persona)
                            Nombre: <strong>{{$persona['Nombres']}} {{$persona['Apellidos']}}</strong>, documento: <strong>{{$persona['Documento']}}</strong>, pertenece a la empresa: <strong>{{$solicitud->EmpresaVisitante}}</strong><br />
                        @endforeach
                        </p>
                        <p>La misma se encuentra en proceso de autorización.</p>
                        <p>Para <strong>AUTORIZAR/RECHAZAR</strong> la solicitud ingrese <a href="{{ $baseUrlAutorizante }}/visitas" target="_Blank">aquí</a>.</p>
                        @if ($solicitud->TipoVisita == 3)
                            <p><strong>Nota:</strong> Recuerde que usted podrá solicitar el acceso al comedor en caso de que corresponda.</p>
                        @endif
                        <p>Saludos</p>
                    </div> 
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>
 