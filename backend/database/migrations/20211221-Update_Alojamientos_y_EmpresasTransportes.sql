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
ALTER TABLE dbo.Alojamientos ADD
	RequiereUnidad bit NULL
GO
ALTER TABLE dbo.Alojamientos SET (LOCK_ESCALATION = TABLE)
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
ALTER TABLE dbo.EmpresasTransportes
	DROP CONSTRAINT FK_EmpresasTransportes_TiposVehiculos
GO
ALTER TABLE dbo.TiposVehiculos SET (LOCK_ESCALATION = TABLE)
GO
COMMIT
BEGIN TRANSACTION
GO
ALTER TABLE dbo.EmpresasTransportes
	DROP CONSTRAINT FK_EmpresasTransportes_Transportistas
GO
ALTER TABLE dbo.Transportistas SET (LOCK_ESCALATION = TABLE)
GO
COMMIT
BEGIN TRANSACTION
GO
ALTER TABLE dbo.EmpresasTransportes
	DROP CONSTRAINT FK_EmpresasTransportes_Empresas
GO
ALTER TABLE dbo.Empresas SET (LOCK_ESCALATION = TABLE)
GO
COMMIT
BEGIN TRANSACTION
GO
CREATE TABLE dbo.Tmp_EmpresasTransportes
	(
	IdEmpresaTransporte numeric(18, 0) NOT NULL IDENTITY (1, 1),
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
ALTER TABLE dbo.Tmp_EmpresasTransportes SET (LOCK_ESCALATION = TABLE)
GO
SET IDENTITY_INSERT dbo.Tmp_EmpresasTransportes ON
GO
IF EXISTS(SELECT * FROM dbo.EmpresasTransportes)
	 EXEC('INSERT INTO dbo.Tmp_EmpresasTransportes (IdEmpresaTransporte, Documento, IdTipoDocumento, IdTransportista, IdTipoVehiculo, Matricula, Chofer, Particular, TipoReserva)
		SELECT IdEmpresaTransporte, Documento, IdTipoDocumento, IdTransportista, IdTipoVehiculo, Matricula, Chofer, Particular, TipoReserva FROM dbo.EmpresasTransportes WITH (HOLDLOCK TABLOCKX)')
GO
SET IDENTITY_INSERT dbo.Tmp_EmpresasTransportes OFF
GO
DROP TABLE dbo.EmpresasTransportes
GO
EXECUTE sp_rename N'dbo.Tmp_EmpresasTransportes', N'EmpresasTransportes', 'OBJECT' 
GO
ALTER TABLE dbo.EmpresasTransportes ADD CONSTRAINT
	PK_EmpresasTransportes PRIMARY KEY CLUSTERED 
	(
	IdEmpresaTransporte
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
COMMIT
