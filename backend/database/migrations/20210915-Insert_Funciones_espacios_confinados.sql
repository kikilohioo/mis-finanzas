insert into funciones values('chkPTCEspaciosConfinados', 'Espacios Confinados', 'Permisos de Trabajo', 'PTCEspaciosConfinados', 1, 0, null, null, 1);
insert into UsuariosFunciones (IdFuncion, IdUsuario) select 'chkPTCEspaciosConfinados', idusuario from usuarios where Administrador = 1 or PTCAdministrador = 1