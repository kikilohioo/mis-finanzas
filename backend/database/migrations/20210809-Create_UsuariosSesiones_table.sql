CREATE TABLE UsuariosSesiones (
    [IdSesion] [numeric](18,0) NOT NULL IDENTITY(1,1),
	[IdUsuario] [varchar](30) NOT NULL,
	[Token] [varchar](255) NOT NULL,
	[Estado] [bit] NOT NULL,
	[ForzarCierre] [bit] NOT NULL,
	[FechaHora] [datetime] NOT NULL,
	[UltimaActividad] [datetime] NOT NULL,
	[DireccionIP] [varchar](32) NULL,
    CONSTRAINT [PK_UsuariosSesiones] PRIMARY KEY([IdSesion])
) ON [PRIMARY];