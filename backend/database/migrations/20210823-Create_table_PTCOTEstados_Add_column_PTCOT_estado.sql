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
CREATE TABLE dbo.PTCOTEstados
	(
	Codigo varchar(8) NOT NULL,
	Nombre varchar(50) NOT NULL,
	Orden int
	)  ON [PRIMARY]
GO
ALTER TABLE dbo.PTCOTEstados ADD CONSTRAINT
	PK_PTCOTEstados PRIMARY KEY CLUSTERED 
	(
	Codigo
	) WITH( STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]

GO
ALTER TABLE dbo.PTCOTEstados SET (LOCK_ESCALATION = TABLE)
GO
insert into PTCOTEstados values('SE', 'Sin ejecutar', 1);
insert into PTCOTEstados values('EP', 'Esperando por', 2);
insert into PTCOTEstados values('E', 'En ejecución', 3);
insert into PTCOTEstados values('EI', 'Esperando Inspección', 4);
insert into PTCOTEstados values('F', 'Finalizado', 5);
insert into PTCOTEstados values('INO', 'Inspeccionado no ok', 6);
GO
alter table PTCOT add Estado varchar(8);
GO
COMMIT
