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
	Dosis1 bit NULL,
	FechaDosis1 datetime NULL,
	Dosis2 bit NULL,
	FechaDosis2 datetime NULL,
	Dosis3 bit NULL,
	FechaDosis3 datetime NULL,
	Vacunado bit NULL,
	CicloCompleto bit NULL,
	InmunizacionVigente bit NULL,
	FuePositivo bit NULL,
	FechaPositivo datetime NULL,
	PermUYMenorA7Dias bit NULL,
	CuarentenaOblig bit NULL,
	PCRIngresoPais datetime NULL,
	PCRSeptimoDia bit NULL,
	FechaPCRSeptimoDia datetime NULL,
	ResultadoPCRSeptimoDia bit NULL,
	FechaHabilitadoAIngresarPlanta datetime NULL,
	AntigenoEnPlanta bit NULL,
	FechaAntigenoEnPlanta datetime NULL,
	ResultadoAntgEnPlanta bit NULL,
	IdAlojamiento numeric(18, 0) NULL,
	IdTransportista numeric(18, 0) NULL,
	AlojamientoNroUnidad numeric(18, 0) NULL,
	IdSeguroSalud numeric(18, 0) NULL
GO
ALTER TABLE dbo.PersonasFisicas SET (LOCK_ESCALATION = TABLE)
GO
COMMIT
