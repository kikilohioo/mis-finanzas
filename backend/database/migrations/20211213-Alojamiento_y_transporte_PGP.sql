/* To prevent any potential data loss issues, you should review this script in detail before running it outside the context of the database designer.*/
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
CREATE TABLE dbo.TiposAlojamientos
	(
	IdTipoAlojamiento numeric(18, 0) NOT NULL,
	Nombre varchar(50) NULL,
	Casa bit NULL,
	Baja bit NULL
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.TiposAlojamientos SET (LOCK_ESCALATION = TABLE)
GO
ALTER TABLE dbo.TiposAlojamientos ADD CONSTRAINT
	PK_TiposAlojamiento PRIMARY KEY CLUSTERED 
	(
	IdTipoAlojamiento
	) WITH( STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]

GO
COMMIT


/* To prevent any potential data loss issues, you should review this script in detail before running it outside the context of the database designer.*/
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
ALTER TABLE dbo.TiposVehiculos ADD
	PGP bit NULL
GO
ALTER TABLE dbo.TiposVehiculos SET (LOCK_ESCALATION = TABLE)
GO
COMMIT

/* To prevent any potential data loss issues, you should review this script in detail before running it outside the context of the database designer.*/
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
CREATE TABLE dbo.Alojamientos
	(
	IdAlojamiento numeric(18, 0) IDENTITY(1,1) PRIMARY KEY NOT NULL,
	IdTipoAlojamiento numeric(18, 0) NOT NULL,
	Nombre varchar(50) NULL,
	Direccion varchar(50) NULL,
	Localidad varchar(50) NULL,
	Telefono varchar(50) NULL,
	Baja bit NULL
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.Alojamientos SET (LOCK_ESCALATION = TABLE)
GO
ALTER TABLE dbo.Alojamientos ADD CONSTRAINT
	FK_Alojamientos_TiposAlojamientos FOREIGN KEY
	(
	IdTipoAlojamiento
	) REFERENCES dbo.TiposAlojamientos
	(
	IdTipoAlojamiento
	) ON UPDATE  NO ACTION 
	 ON DELETE  NO ACTION 
GO
COMMIT

/* To prevent any potential data loss issues, you should review this script in detail before running it outside the context of the database designer.*/
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
CREATE TABLE dbo.Transportistas
	(
	IdTransportista numeric(18, 0) IDENTITY(1,1) PRIMARY KEY NOT NULL,
	Nombre varchar(50) NULL,
	Baja bit NULL
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.Transportistas SET (LOCK_ESCALATION = TABLE)
GO
COMMIT

/* To prevent any potential data loss issues, you should review this script in detail before running it outside the context of the database designer.*/
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
CREATE TABLE dbo.SegurosSalud
	(
	IdSeguroSalud numeric(18, 0) IDENTITY(1,1) PRIMARY KEY NOT NULL,
	Nombre varchar(50) NULL,
	Baja bit NULL
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.SegurosSalud SET (LOCK_ESCALATION = TABLE)
GO
COMMIT

/* To prevent any potential data loss issues, you should review this script in detail before running it outside the context of the database designer.*/
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
ALTER TABLE dbo.Empresas SET (LOCK_ESCALATION = TABLE)
GO
COMMIT
BEGIN TRANSACTION
GO
CREATE TABLE dbo.EmpresasAlojamientos
	(
	IdEmpresasAlojamiento numeric(18, 0) IDENTITY(1,1) PRIMARY KEY NOT NULL,
	Documento varchar(20) NOT NULL,
	IdTipoDocumento numeric(18, 0) NOT NULL,
	IdAlojamiento numeric(18, 0) NULL,
	IdTipoAlojamiento numeric(18, 0) NOT NULL,
	Direccion varchar(50) NULL,
	Localidad varchar(50) NULL,
	CantidadPersonas numeric(18, 0) NULL,
	Ubicacion varchar(150) NULL,
	TipoReserva bit NULL
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.EmpresasAlojamientos ADD CONSTRAINT
	FK_EmpresasAlojamientos_Empresas FOREIGN KEY
	(
	Documento,
	IdTipoDocumento
	) REFERENCES dbo.Empresas
	(
	Documento,
	IdTipoDocumento
	) ON UPDATE  NO ACTION 
	 ON DELETE  NO ACTION 
	
GO
ALTER TABLE dbo.EmpresasAlojamientos ADD CONSTRAINT
	FK_EmpresasAlojamientos_Alojamientos FOREIGN KEY
	(
	IdAlojamiento
	) REFERENCES dbo.Alojamientos
	(
	IdAlojamiento
	) ON UPDATE  NO ACTION 
	 ON DELETE  NO ACTION 
	
GO
ALTER TABLE dbo.EmpresasAlojamientos SET (LOCK_ESCALATION = TABLE)
GO
COMMIT

/* To prevent any potential data loss issues, you should review this script in detail before running it outside the context of the database designer.*/
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
ALTER TABLE dbo.TiposVehiculos SET (LOCK_ESCALATION = TABLE)
GO
COMMIT
BEGIN TRANSACTION
GO
ALTER TABLE dbo.Empresas SET (LOCK_ESCALATION = TABLE)
GO
COMMIT
BEGIN TRANSACTION
GO
CREATE TABLE dbo.EmpresasTransportes
	(
	Documento varchar(20) NOT NULL,
	IdTipoDocumento numeric(18, 0) NOT NULL,
	IdTransportista numeric(18, 0) NULL,
	IdTipoVehiculo numeric(18, 0) NULL,
	Matricula varchar(10) NOT NULL,
	Chofer varchar(50) NULL,
	Particular bit NULL,
	TipoReserva bit NULL
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.EmpresasTransportes ADD CONSTRAINT
	PK_Alojamientos PRIMARY KEY CLUSTERED 
	(
	Documento,IdTipoDocumento,Matricula
	) WITH( STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]

GO
ALTER TABLE dbo.EmpresasTransportes ADD CONSTRAINT
	FK_EmpresasTransportes_Empresas FOREIGN KEY
	(
	Documento,
	IdTipoDocumento
	) REFERENCES dbo.Empresas
	(
	Documento,
	IdTipoDocumento
	) ON UPDATE  NO ACTION 
	 ON DELETE  NO ACTION 
	
GO
ALTER TABLE dbo.EmpresasTransportes ADD CONSTRAINT
	FK_EmpresasTransportes_Transportistas FOREIGN KEY
	(
	IdTransportista
	) REFERENCES dbo.Transportistas
	(
	IdTransportista
	) ON UPDATE  NO ACTION 
	 ON DELETE  NO ACTION 
	
GO
ALTER TABLE dbo.EmpresasTransportes ADD CONSTRAINT
	FK_EmpresasTransportes_TiposVehiculos FOREIGN KEY
	(
	IdTipoVehiculo
	) REFERENCES dbo.TiposVehiculos
	(
	IdTipoVehiculo
	) ON UPDATE  NO ACTION 
	 ON DELETE  NO ACTION 
	
GO
ALTER TABLE dbo.EmpresasTransportes SET (LOCK_ESCALATION = TABLE)
GO
COMMIT


insert into funciones (IdFuncion, Descripcion, Grupo, Entity, Menu, Gestion, Info, Subgrupo, PTC) values ('chkAdmTiposAlojamientos', 'Tipos Alojamientos', 'Administración', 'TiposAlojamientos', 1, 1, null, null, 0)
insert into funciones (IdFuncion, Descripcion, Grupo, Entity, Menu, Gestion, Info, Subgrupo, PTC) values ('chkAdmAlojamientos', 'Alojamientos', 'Administración', 'Alojamientos', 1, 1, null, null, 0)
-- insert into funciones (IdFuncion, Descripcion, Grupo, Entity, Menu, Gestion, Info, Subgrupo, PTC) values ('chkAdmTransportistas', 'Transportistas', 'Administración', 'Transportistas', 1, 1, null, null, 0)
-- insert into funciones (IdFuncion, Descripcion, Grupo, Entity, Menu, Gestion, Info, Subgrupo, PTC) values ('chkAdmSegurosSalud', 'Seguro Salud', 'Administración', 'SegurosSalud', 1, 1, null, null, 0)
-- insert into funciones (IdFuncion, Descripcion, Grupo, Entity, Menu, Gestion, Info, Subgrupo, PTC) values ('chkAdmEmpresasAlojamientos', 'Empresas Alojamientos', 'Administración', 'EmpresasAlojamientos', 1, 1, null, null, 0)
-- insert into funciones (IdFuncion, Descripcion, Grupo, Entity, Menu, Gestion, Info, Subgrupo, PTC) values ('chkAdmEmpresasTransportes', 'Empresas Transportes', 'Administración', 'EmpresasTransportes', 1, 1, null, null, 0)

insert into UsuariosFunciones (IdFuncion, IdUsuario) select 'chkAdmTiposAlojamientos', idusuario from usuarios where Administrador = 1;
insert into UsuariosFunciones (IdFuncion, IdUsuario) select 'chkAdmAlojamientos', idusuario from usuarios where Administrador = 1;
-- insert into UsuariosFunciones (IdFuncion, IdUsuario) select 'chkAdmTransportistas', idusuario from usuarios where Administrador = 1;
-- insert into UsuariosFunciones (IdFuncion, IdUsuario) select 'chkAdmSegurosSalud', idusuario from usuarios where Administrador = 1;
-- insert into UsuariosFunciones (IdFuncion, IdUsuario) select 'chkAdmEmpresasAlojamientos', idusuario from usuarios where Administrador = 1;
-- insert into UsuariosFunciones (IdFuncion, IdUsuario) select 'chkAdmEmpresasTransportes', idusuario from usuarios where Administrador = 1;