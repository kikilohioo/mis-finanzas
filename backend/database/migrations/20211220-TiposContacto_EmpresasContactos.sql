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
CREATE TABLE dbo.TiposContacto
	(
	IdTipoContacto numeric(18, 0) NOT NULL,
	Nombre varchar(50) NULL,
	Baja bit NULL
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.TiposContacto ADD CONSTRAINT
	PK_TiposContacto PRIMARY KEY CLUSTERED 
	(
	IdTipoContacto
	) WITH( STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]

GO
ALTER TABLE dbo.TiposContacto SET (LOCK_ESCALATION = TABLE)
GO
COMMIT

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
CREATE TABLE dbo.EmpresasContactos
	(
	IdEmpresaContacto numeric(18, 0) IDENTITY(1,1) PRIMARY KEY NOT NULL,
	Documento varchar(20) NULL,
	IdTipoDocumento numeric(18, 0) NULL,
	IdTipoContacto numeric(18, 0) NULL,
	Nombre varchar(50) NULL,
	Celular varchar(50) NULL,
	Email varchar(50) NULL
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.EmpresasContactos ADD CONSTRAINT
	FK_EmpresasContactos_Empresas FOREIGN KEY
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
ALTER TABLE dbo.EmpresasContactos ADD CONSTRAINT
	FK_EmpresasContactos_TiposContacto FOREIGN KEY
	(
	IdTipoContacto
	) REFERENCES dbo.TiposContacto
	(
	IdTipoContacto
	) ON UPDATE  NO ACTION 
	 ON DELETE  NO ACTION 
	
GO
ALTER TABLE dbo.EmpresasContactos SET (LOCK_ESCALATION = TABLE)
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
ALTER TABLE dbo.Empresas ADD
	CantTrabajadores numeric(18, 0) NULL
GO
ALTER TABLE dbo.Empresas ADD
	CantTrabajadoresAloj numeric(18, 0) NULL
GO
ALTER TABLE dbo.Empresas SET (LOCK_ESCALATION = TABLE)
GO
COMMIT

insert into funciones (IdFuncion, Descripcion, Grupo, Entity, Menu, Gestion, Info, Subgrupo, PTC) values ('chkAdmTiposContacto', 'Tipos Contacto', 'Administración', 'TiposContacto', 1, 1, null, null, 0)
-- insert into funciones (IdFuncion, Descripcion, Grupo, Entity, Menu, Gestion, Info, Subgrupo, PTC) values ('chkAdmEmpresasContactos', 'Empresas Contactos', 'Administración', 'EmpresasContactos', 1, 1, null, null, 0)

insert into UsuariosFunciones (IdFuncion, IdUsuario) select 'chkAdmTiposContacto', idusuario from usuarios where Administrador = 1;
-- insert into UsuariosFunciones (IdFuncion, IdUsuario) select 'chkAdmEmpresasContactos', idusuario from usuarios where Administrador = 1;