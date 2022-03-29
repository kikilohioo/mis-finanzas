drop table empresasTransportes;

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
ALTER TABLE dbo.Vehiculos SET (LOCK_ESCALATION = TABLE)
GO
COMMIT
BEGIN TRANSACTION
GO
ALTER TABLE dbo.PersonasFisicas SET (LOCK_ESCALATION = TABLE)
GO
COMMIT
BEGIN TRANSACTION
GO
CREATE TABLE dbo.EmpresasTransportes
	(
	IdEmpresaTransporte numeric(18, 0) IDENTITY(1,1) PRIMARY KEY NOT NULL,
	Documento varchar(20) NULL,
	IdTipoDocumento numeric(18, 0) NULL,
	Serie char(5) NULL,
	Numero varchar(50) NULL,
	DocumentoChofer1 varchar(20) NULL,
	IdTipoDocumentoChofer1 numeric(18, 0) NULL,
	DocumentoChofer2 varchar(20) NULL,
	IdTipoDocumentoChofer2 numeric(18, 0) NULL,
	TipoReserva bit NULL
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.EmpresasTransportes ADD CONSTRAINT
	FK_EmpresasTransportes_PersonasFisicas FOREIGN KEY
	(
	DocumentoChofer1,
	IdTipoDocumentoChofer1
	) REFERENCES dbo.PersonasFisicas
	(
	Documento,
	IdTipoDocumento
	) ON UPDATE  NO ACTION 
	 ON DELETE  NO ACTION 
	
GO
ALTER TABLE dbo.EmpresasTransportes ADD CONSTRAINT
	FK_EmpresasTransportes_PersonasFisicas1 FOREIGN KEY
	(
	DocumentoChofer2,
	IdTipoDocumentoChofer2
	) REFERENCES dbo.PersonasFisicas
	(
	Documento,
	IdTipoDocumento
	) ON UPDATE  NO ACTION 
	 ON DELETE  NO ACTION 
	
GO
ALTER TABLE dbo.EmpresasTransportes ADD CONSTRAINT
	FK_EmpresasTransportes_Vehiculos FOREIGN KEY
	(
	Serie,
	Numero
	) REFERENCES dbo.Vehiculos
	(
	Serie,
	Numero
	) ON UPDATE  NO ACTION 
	 ON DELETE  NO ACTION 
	
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
ALTER TABLE dbo.EmpresasTransportes SET (LOCK_ESCALATION = TABLE)
GO
COMMIT

insert into parametros (idparametro, Descripcion, valor, ParametroUsuario)values('Chofer', 'Personas fisicas que son choferes','130,131,145',null);
