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
                                Estimado/a<b>&nbsp;{{$solicitud->PersonaContacto}}</b>, 
								<br />
								<br />
								<b>{{$solicitud->Usuario->Nombre}}</b> ha realizado una solicitud para que su vehículo 
								<b>{{$solicitud->Matricula}}</b> circule dentro del área restringida del complejo industrial de Montes del Plata con motivo
                    
								<b>{{$solicitud->Motivo}}</b> en la fecha (
								<b>{{date_format($solicitud->Desde, 'd/m/Y')}}</b>-
								<b>{{date_format($solicitud->Hasta, 'd/m/Y')}}</b>).
								<br />
								<br />
                                La solicitud se encuentra en proceso de autorización.
								<br />
								<br />
                                Se le notificará vía mail cuando la misma sea autorizada y/o rechazada.
								<br />
								<br />
                                Saludos
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</body>
	</html>