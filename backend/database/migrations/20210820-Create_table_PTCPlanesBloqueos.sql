BEGIN TRANSACTION
SET QUOTED_IDENTIFIER ON
SET ARITHABORT ON
SET NUMERIC_ROUNDABORT OFF
SET CONCAT_NULL_YIELDS_NULL ON
SET ANSI_NULLS ON
SET ANSI_PADDING ON
SET ANSI_WARNINGS ON
COMMIT
BEGIN TRANSACTION
GO
CREATE TABLE dbo.PTCPlanesBloqueos
	(
	IdPlanBloqueo numeric(18, 0) NOT NULL,
	IdArea numeric(18, 0) NOT NULL,
	Nombre varchar(255) NOT NULL,
	AnhoPGP numeric(18,0) NOT NULL,
	IdUsuario varchar(30) NOT NULL,
	FechaHora datetime NOT NULL
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.PTCPlanesBloqueos ADD CONSTRAINT
	PK_PTCPlanesBloqueos PRIMARY KEY CLUSTERED 
	(
	IdPlanBloqueo
	) WITH( STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]

GO
ALTER TABLE dbo.PTCPlanesBloqueos SET (LOCK_ESCALATION = TABLE)
GO
insert into funciones (IdFuncion, Descripcion, Grupo, Entity, Menu, Gestion, Info, Subgrupo, PTC) values ('chkPTCPlanesBloqueos', 'Planes Bloqueos', 'Permisos de Trabajo', 'PTCPlanesBloqueos', 1, 0, null, null, 1)
GO
-- insert into usuariosfunciones (IdUsuario, idfuncion) select idUsuario, 'chkPTCPlanesBloqueos' from usuariosfunciones where idfuncion = 'chkPTCAreas';
-- insert into UsuariosFunciones (IdFuncion, IdUsuario) select 'chkPTCPlanesBloqueos', idusuario from usuarios where Administrador = 1 or PTCAdministrador = 1
GO
COMMIT