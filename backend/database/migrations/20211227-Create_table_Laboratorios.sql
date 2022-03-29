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
CREATE TABLE dbo.Laboratorios
	(
	IdLaboratorio numeric(18, 0) NULL,
	Nombre varchar(100) NULL
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.Laboratorios SET (LOCK_ESCALATION = TABLE)
GO
COMMIT

insert into Laboratorios values (1, 'AstraZeneca');
insert into Laboratorios values (2, 'Sputnik V');
insert into Laboratorios values (3, 'Moderna');
insert into Laboratorios values (4, 'Pfizer');
insert into Laboratorios values (5, 'Sinopharm');
insert into Laboratorios values (6, 'Sinovac');
insert into Laboratorios values (7, 'CoronaVac');
insert into Laboratorios values (8, 'Janssen');