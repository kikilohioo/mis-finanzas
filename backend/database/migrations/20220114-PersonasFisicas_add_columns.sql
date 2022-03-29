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
ALTER TABLE dbo.PersonasFisicas ADD
	CelularContacto varchar(50) NULL,
	TransporteDesdeAeropuerto bit NULL,
	IdPaisOrigen numeric(18, 0) NULL,
	FechaVuelo datetime NULL,
	FechaArribo datetime NULL,
	FechaRetorno datetime NULL
GO
ALTER TABLE dbo.PersonasFisicas SET (LOCK_ESCALATION = TABLE)
GO
COMMIT

alter table personasFisicas DROP  COLUMN IdSeguroSalud;
alter table personasfisicas add SeguroSalud varchar(50);