--- Cambiar concepto de tercerizado a contratista
-- update Funciones set IdFuncion = 'chkAdmEmpresaContratista', Descripcion = 'Empresa Contratista' where IdFuncion like 'chkAdmEmpresasTercerizada'
-- update UsuariosFunciones set IdFuncion = 'chkAdmEmpresaContratista' where IdFuncion like 'chkAdmEmpresasTercerizada'

-- Agregar nueva funcionalidad
insert into funciones (IdFuncion, Descripcion, Grupo, Entity, Menu, Gestion, Info, Subgrupo, PTC) values ('chkAdmEmpresaContratista', 'Empresa Contratista', 'Administración', 'EmpresaContratista', 1, 1, null, null, 0)
insert into UsuariosFunciones (IdFuncion, IdUsuario) select 'chkAdmEmpresaContratista', idusuario from usuarios where Administrador = 1;

--- Crear nuevo campo para indicar si una categoría va a estar disponible para los contratistas
ALTER TABLE Categorias ADD ContratistaDisponible BIT DEFAULT 0;
UPDATE Categorias SET ContratistaDisponible = 0;
ALTER TABLE Categorias ALTER COLUMN ContratistaDisponible BIT NOT NULL;

--- Agregar nuevos campos en tabla Empresas
ALTER TABLE Empresas
	ADD NroOrdenCompra VARCHAR(50),
	ReferenteMDP VARCHAR(100),
	ProtocoloCovidArchivo VARCHAR(255),
	ProtocoloCovidEstado TINYINT DEFAULT 0;