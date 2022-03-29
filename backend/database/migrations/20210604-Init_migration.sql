/*** AJUSTES A LAS TABLAS DE SESIONES ***/
/*
TRUNCATE TABLE LogSesiones;
ALTER TABLE LogSesiones DROP CONSTRAINT FK_LogSesiones_Sesiones;
ALTER TABLE LogSesiones ALTER COLUMN IdSesion [numeric](18,0);

DROP TABLE Sesiones;
CREATE TABLE Sesiones (
    [IdSesion] [numeric](18,0) NOT NULL IDENTITY(1,1),
	[IdUsuario] [varchar](30) NOT NULL,
	[Token] [varchar](255) NOT NULL,
	[Estado] [bit] NOT NULL,
	[ForzarCierre] [bit] NOT NULL,
	[FechaHora] [datetime] NOT NULL,
	[UltimaActividad] [datetime] NOT NULL,
	[DireccionIP] [varchar](32) NULL,
    CONSTRAINT [PK_Sesiones] PRIMARY KEY([IdSesion])
) ON [PRIMARY];

ALTER TABLE LogSesiones ADD CONSTRAINT FK_LogSesiones_Sesiones FOREIGN KEY (IdSesion) REFERENCES Sesiones(IdSesion);
*/

/*** CAMBIAR NOMBRES A LAS COLUMNAS DE [EstadosActividad] ***/
EXEC sp_rename 'EstadosActividad.idEstadoActividad', 'IdEstadoActividad', 'COLUMN';
EXEC sp_rename 'EstadosActividad.descripcion', 'Descripcion', 'COLUMN';
EXEC sp_rename 'EstadosActividad.baja', 'Baja', 'COLUMN';
EXEC sp_rename 'EstadosActividad.fechaHora', 'FechaHora', 'COLUMN';
EXEC sp_rename 'EstadosActividad.idUsuario', 'IdUsuario', 'COLUMN';
EXEC sp_rename 'EstadosActividad.dias', 'Dias', 'COLUMN';
EXEC sp_rename 'EstadosActividad.accion', 'Accion', 'COLUMN';
EXEC sp_rename 'EstadosActividad.desactivar', 'Desactivar', 'COLUMN';

/*** CREAR USUARIO PARA WEBSERVICES PUBLICOS ***/
INSERT INTO [dbo].[Usuarios] ([IdUsuario], [Nombre], [Contrasenia], [Email], [Estado], [Gestion], [ApruebaVisitas], [SoloLectura], [Administrador], [NuevosContratos], [EnTransitos], [Baja], [UltimaEmpresaDocumento], [UltimaEmpresaIdTipoDocumento], [FechaHoraBaja], [IdUsuarioBaja], [PTC], [PTCRol], [PTCGestion], [PTCAdministrador], [RecibeNotificaciones], [rowguid], [LDAP], [EstadoObservacion])
     VALUES ('fsa', 'FSAcceso Public', '', '', 1, 1, 1, 0, 1, 0, 0, 0, NULL, NULL, NULL, NULL, 1, NULL, 1, 1, 0, NEWID(), 0, NULL)


/******* MIGRACIÓN HASTA AQUÍ *******/