-- ============================================================
-- system_pl · Esquema base (baseline) · generado 2026-09-29
-- Nombres EXACTOS usados por el código (MySQL en Linux distingue mayúsculas).
-- Solo estructura: tablas, índices, FKs y vistas vw_*. Sin datos.
-- Las tablas de Laravel (cache, jobs, migrations...) las crean las migraciones 0001_*.
-- Lo ejecuta la migración 2026_09_29_000000_esquema_base solo si no existe la tabla Usuario.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- ------------------------------------------------------------
-- Tabla Almacen
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Almacen` (
  `idAlmacen` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SAFCO, SF, FC',
  `nomAlmacen` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` tinyint unsigned NOT NULL DEFAULT '0',
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idAlmacen`),
  UNIQUE KEY `uk_almacen_codigo` (`codigo`),
  UNIQUE KEY `uk_almacen_nombre` (`nomAlmacen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Area
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Area` (
  `idArea` int unsigned NOT NULL AUTO_INCREMENT,
  `nomArea` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idArea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla AuditoriaLog
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `AuditoriaLog` (
  `idAuditoria` int unsigned NOT NULL AUTO_INCREMENT,
  `idUsuario` int unsigned DEFAULT NULL COMMENT 'Usuario que ejecutó la acción',
  `accion` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idEntidad` int unsigned DEFAULT NULL,
  `descripcion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `datosAnteriores` json DEFAULT NULL,
  `datosNuevos` json DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `userAgent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idAuditoria`),
  KEY `idx_auditoria_usuario` (`idUsuario`),
  KEY `idx_auditoria_accion` (`accion`),
  KEY `idx_auditoria_fecha` (`fechaRegistro`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`idUsuario`) REFERENCES `Usuario` (`idUsuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla CantidadCaja
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `CantidadCaja` (
  `idCantidadCaja` int unsigned NOT NULL AUTO_INCREMENT,
  `cantidad` decimal(12,7) NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idCantidadCaja`),
  UNIQUE KEY `uk_cantidad_caja` (`cantidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Cargo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Cargo` (
  `idCargo` int unsigned NOT NULL AUTO_INCREMENT,
  `nomCargo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idCargo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Cliente
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Cliente` (
  `idCliente` int unsigned NOT NULL AUTO_INCREMENT,
  `nomCliente` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idCliente`),
  UNIQUE KEY `uk_Cliente_nombre` (`nomCliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Familia
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Familia` (
  `idFamilia` int unsigned NOT NULL AUTO_INCREMENT,
  `nomFamilia` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idFamilia`),
  UNIQUE KEY `uk_Familia_nombre` (`nomFamilia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla MaterialProgramaPL
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `MaterialProgramaPL` (
  `idMaterialPL` int unsigned NOT NULL AUTO_INCREMENT,
  `CodigoPL` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nomMaterial` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fechaRegistro` date NOT NULL DEFAULT (curdate()),
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idMaterialPL`),
  UNIQUE KEY `uk_material_codigo_pl` (`CodigoPL`),
  KEY `idx_material_codigo` (`CodigoPL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla MaterialStockConfig
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `MaterialStockConfig` (
  `idMaterialStockConfig` int unsigned NOT NULL AUTO_INCREMENT,
  `idMaterialPL` int unsigned NOT NULL,
  `stockMinimo` decimal(12,2) NOT NULL DEFAULT '0.00',
  `fechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idMaterialStockConfig`),
  UNIQUE KEY `uk_material_stock_config` (`idMaterialPL`),
  CONSTRAINT `fk_material_stock_config_material` FOREIGN KEY (`idMaterialPL`) REFERENCES `MaterialProgramaPL` (`idMaterialPL`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_material_stock_minimo_no_negativo` CHECK ((`stockMinimo` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Mercado
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Mercado` (
  `idMercado` int unsigned NOT NULL AUTO_INCREMENT,
  `nomMercado` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idMercado`),
  UNIQUE KEY `uk_Mercado_nombre` (`nomMercado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Modulo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Modulo` (
  `idModulo` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` tinyint unsigned NOT NULL DEFAULT '0',
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idModulo`),
  UNIQUE KEY `uk_modulo_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla MovimientoStock
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `MovimientoStock` (
  `idMovimiento` int unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('ingreso','egreso') COLLATE utf8mb4_unicode_ci NOT NULL,
  `idAlmacen` int unsigned NOT NULL,
  `idMaterialPL` int unsigned NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `fecha` date NOT NULL,
  `observacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idTipoConsumo` int unsigned DEFAULT NULL COMMENT 'Motivo de consumo (solo egresos/consumos manuales)',
  `idPrograma` int unsigned DEFAULT NULL COMMENT 'Programa de Receta (consumos)',
  `idPresentacion` int unsigned DEFAULT NULL COMMENT 'Presentación de Receta (consumos)',
  `facturaGuia` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Factura o guía',
  `factura` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de factura',
  `guia` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de guía',
  `archivoOneDriveId` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID archivo OneDrive',
  `archivoOneDriveUrl` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL web OneDrive',
  `archivoNombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre archivo en OneDrive',
  `idTraslado` int unsigned DEFAULT NULL COMMENT 'Enlace si proviene de un traslado',
  `idUsuario` int unsigned DEFAULT NULL COMMENT 'Usuario que registró',
  `fechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=anulado',
  PRIMARY KEY (`idMovimiento`),
  KEY `idx_movimiento_almacen` (`idAlmacen`),
  KEY `idx_movimiento_material` (`idMaterialPL`),
  KEY `idx_movimiento_almacen_material` (`idAlmacen`,`idMaterialPL`),
  KEY `idx_movimiento_tipo` (`tipo`),
  KEY `idx_movimiento_fecha` (`fecha`),
  KEY `idx_movimiento_traslado` (`idTraslado`),
  KEY `idx_movimiento_usuario` (`idUsuario`),
  KEY `idx_movimiento_estado` (`estado`),
  KEY `fk_movimiento_tipo_consumo` (`idTipoConsumo`),
  KEY `fk_movimiento_programa` (`idPrograma`),
  KEY `fk_movimiento_presentacion` (`idPresentacion`),
  KEY `idx_movimiento_consumo_receta` (`tipo`,`idPrograma`,`idPresentacion`,`idMaterialPL`,`estado`),
  KEY `idx_movimiento_stock_calc` (`idAlmacen`,`idMaterialPL`,`estado`,`tipo`),
  KEY `idx_movimiento_tipo_estado_traslado_fecha` (`tipo`,`estado`,`idTraslado`,`fecha`,`idMovimiento`),
  CONSTRAINT `fk_movimiento_almacen` FOREIGN KEY (`idAlmacen`) REFERENCES `Almacen` (`idAlmacen`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_movimiento_material` FOREIGN KEY (`idMaterialPL`) REFERENCES `MaterialProgramaPL` (`idMaterialPL`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_movimiento_presentacion` FOREIGN KEY (`idPresentacion`) REFERENCES `Presentacion` (`idPresentacion`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_movimiento_programa` FOREIGN KEY (`idPrograma`) REFERENCES `Programa` (`idPrograma`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_movimiento_tipo_consumo` FOREIGN KEY (`idTipoConsumo`) REFERENCES `TipoConsumo` (`idTipoConsumo`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_movimiento_traslado` FOREIGN KEY (`idTraslado`) REFERENCES `Traslado` (`idTraslado`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_movimiento_usuario` FOREIGN KEY (`idUsuario`) REFERENCES `Usuario` (`idUsuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_movimiento_cantidad_positiva` CHECK ((`cantidad` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Persona
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Persona` (
  `idPersona` int unsigned NOT NULL AUTO_INCREMENT,
  `Nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Apellidos` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Correo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idArea` int unsigned NOT NULL,
  `idCargo` int unsigned NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idPersona`),
  UNIQUE KEY `uk_persona_correo` (`Correo`),
  KEY `idx_persona_area` (`idArea`),
  KEY `idx_persona_cargo` (`idCargo`),
  CONSTRAINT `fk_persona_area` FOREIGN KEY (`idArea`) REFERENCES `Area` (`idArea`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_persona_cargo` FOREIGN KEY (`idCargo`) REFERENCES `Cargo` (`idCargo`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla planConsumo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `planConsumo` (
  `idPlanConsumo` int unsigned NOT NULL AUTO_INCREMENT,
  `idMaterialPL` int unsigned NOT NULL,
  `idSemConsumo` int unsigned NOT NULL,
  `cantidad` decimal(12,2) NOT NULL DEFAULT '0.00',
  `Estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`idPlanConsumo`),
  UNIQUE KEY `uq_planconsumo_material_sem` (`idMaterialPL`,`idSemConsumo`),
  KEY `idx_planconsumo_sem` (`idSemConsumo`),
  CONSTRAINT `fk_planconsumo_material` FOREIGN KEY (`idMaterialPL`) REFERENCES `MaterialProgramaPL` (`idMaterialPL`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_planconsumo_sem` FOREIGN KEY (`idSemConsumo`) REFERENCES `semConsumo` (`idSemConsumo`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla planConsumoOrden
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `planConsumoOrden` (
  `idMaterialPL` int unsigned NOT NULL,
  `nroOrden` smallint unsigned NOT NULL,
  PRIMARY KEY (`idMaterialPL`),
  KEY `idx_planconsumo_orden` (`nroOrden`),
  CONSTRAINT `fk_planconsumo_orden_material` FOREIGN KEY (`idMaterialPL`) REFERENCES `MaterialProgramaPL` (`idMaterialPL`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla planIngreso
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `planIngreso` (
  `idPlanIngreso` int unsigned NOT NULL AUTO_INCREMENT,
  `idMaterialPL` int unsigned NOT NULL,
  `idSemConsumo` int unsigned NOT NULL,
  `cantidad` decimal(12,2) NOT NULL DEFAULT '0.00',
  `Estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`idPlanIngreso`),
  UNIQUE KEY `uq_planingreso_material_sem` (`idMaterialPL`,`idSemConsumo`),
  KEY `idx_planingreso_sem` (`idSemConsumo`),
  CONSTRAINT `fk_planingreso_material` FOREIGN KEY (`idMaterialPL`) REFERENCES `MaterialProgramaPL` (`idMaterialPL`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_planingreso_sem` FOREIGN KEY (`idSemConsumo`) REFERENCES `semConsumo` (`idSemConsumo`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla planProduccion
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `planProduccion` (
  `idPlanProduccion` int unsigned NOT NULL AUTO_INCREMENT,
  `idProgramaGeneral` int unsigned NOT NULL,
  `idSemProduccion` int unsigned NOT NULL,
  `cantidad` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `calificacion` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nroOrden` smallint unsigned NOT NULL DEFAULT '0',
  `Estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`idPlanProduccion`),
  UNIQUE KEY `uq_planprod_pg_sem` (`idProgramaGeneral`,`idSemProduccion`),
  KEY `idx_planprod_sem` (`idSemProduccion`),
  KEY `idx_planprod_orden` (`nroOrden`),
  CONSTRAINT `fk_planprod_pg` FOREIGN KEY (`idProgramaGeneral`) REFERENCES `ProgramaGeneral` (`idProgramaGeneral`),
  CONSTRAINT `fk_planprod_sem` FOREIGN KEY (`idSemProduccion`) REFERENCES `semProduccion` (`idSemProduccion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla planProduccionMaterial
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `planProduccionMaterial` (
  `idPlanProduccionMaterial` int unsigned NOT NULL AUTO_INCREMENT,
  `idProgramaGeneral` int unsigned NOT NULL,
  `idMaterialPL` int unsigned NOT NULL,
  `idSemProduccion` int unsigned NOT NULL,
  `cantidad` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `Estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`idPlanProduccionMaterial`),
  UNIQUE KEY `uq_planprod_mat` (`idProgramaGeneral`,`idMaterialPL`,`idSemProduccion`),
  KEY `idx_planprod_mat_sem` (`idSemProduccion`),
  KEY `fk_planprodmat_mat` (`idMaterialPL`),
  CONSTRAINT `fk_planprodmat_mat` FOREIGN KEY (`idMaterialPL`) REFERENCES `MaterialProgramaPL` (`idMaterialPL`),
  CONSTRAINT `fk_planprodmat_pg` FOREIGN KEY (`idProgramaGeneral`) REFERENCES `ProgramaGeneral` (`idProgramaGeneral`),
  CONSTRAINT `fk_planprodmat_sem` FOREIGN KEY (`idSemProduccion`) REFERENCES `semProduccion` (`idSemProduccion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Presentacion
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Presentacion` (
  `idPresentacion` int unsigned NOT NULL AUTO_INCREMENT,
  `nomPresentacion` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idPresentacion`),
  UNIQUE KEY `uk_Presentacion_nombre` (`nomPresentacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Programa
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Programa` (
  `idPrograma` int unsigned NOT NULL AUTO_INCREMENT,
  `nomPrograma` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  `idProgramaGeneral` int unsigned DEFAULT NULL,
  PRIMARY KEY (`idPrograma`),
  UNIQUE KEY `uk_Programa_nombre` (`nomPrograma`),
  KEY `fk_programa_programageneral` (`idProgramaGeneral`),
  CONSTRAINT `fk_programa_programageneral` FOREIGN KEY (`idProgramaGeneral`) REFERENCES `ProgramaGeneral` (`idProgramaGeneral`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla ProgramaFila
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ProgramaFila` (
  `idProgramaFila` int unsigned NOT NULL AUTO_INCREMENT,
  `nroFila` int unsigned NOT NULL,
  `idMercado` int unsigned NOT NULL,
  `idCliente` int unsigned NOT NULL,
  `idVariedad` int unsigned NOT NULL,
  `idPrograma` int unsigned NOT NULL,
  `idProgramaGeneral` int unsigned DEFAULT NULL,
  `idPresentacion` int unsigned NOT NULL,
  `idMaterialPL` int unsigned NOT NULL,
  `idCantidadCaja` int unsigned NOT NULL,
  `idCantidadCajaRefExcel` int unsigned DEFAULT NULL,
  `idUso` int unsigned NOT NULL,
  `idSituado` int unsigned NOT NULL,
  `idFamilia` int unsigned NOT NULL,
  `imagen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ruta relativa en storage/public',
  `Estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`idProgramaFila`),
  UNIQUE KEY `uk_programa_fila_nro` (`nroFila`),
  KEY `idx_programa_fila_mercado` (`idMercado`),
  KEY `idx_programa_fila_cliente` (`idCliente`),
  KEY `idx_programa_fila_programa` (`idPrograma`),
  KEY `fk_programa_fila_variedad` (`idVariedad`),
  KEY `fk_programa_fila_presentacion` (`idPresentacion`),
  KEY `fk_programa_fila_material` (`idMaterialPL`),
  KEY `fk_programa_fila_cantidad` (`idCantidadCaja`),
  KEY `fk_programa_fila_uso` (`idUso`),
  KEY `fk_programa_fila_situado` (`idSituado`),
  KEY `fk_programa_fila_familia` (`idFamilia`),
  KEY `idx_programa_fila_programa_presentacion` (`idPrograma`,`idPresentacion`,`Estado`,`idMaterialPL`),
  KEY `fk_programafila_programageneral` (`idProgramaGeneral`),
  CONSTRAINT `fk_programa_fila_cantidad` FOREIGN KEY (`idCantidadCaja`) REFERENCES `CantidadCaja` (`idCantidadCaja`),
  CONSTRAINT `fk_programa_fila_cliente` FOREIGN KEY (`idCliente`) REFERENCES `Cliente` (`idCliente`),
  CONSTRAINT `fk_programa_fila_familia` FOREIGN KEY (`idFamilia`) REFERENCES `Familia` (`idFamilia`),
  CONSTRAINT `fk_programa_fila_material` FOREIGN KEY (`idMaterialPL`) REFERENCES `MaterialProgramaPL` (`idMaterialPL`),
  CONSTRAINT `fk_programa_fila_mercado` FOREIGN KEY (`idMercado`) REFERENCES `Mercado` (`idMercado`),
  CONSTRAINT `fk_programa_fila_presentacion` FOREIGN KEY (`idPresentacion`) REFERENCES `Presentacion` (`idPresentacion`),
  CONSTRAINT `fk_programa_fila_programa` FOREIGN KEY (`idPrograma`) REFERENCES `Programa` (`idPrograma`),
  CONSTRAINT `fk_programa_fila_situado` FOREIGN KEY (`idSituado`) REFERENCES `Situado` (`idSituado`),
  CONSTRAINT `fk_programa_fila_uso` FOREIGN KEY (`idUso`) REFERENCES `Uso` (`idUso`),
  CONSTRAINT `fk_programa_fila_variedad` FOREIGN KEY (`idVariedad`) REFERENCES `Variedad` (`idVariedad`),
  CONSTRAINT `fk_programafila_programageneral` FOREIGN KEY (`idProgramaGeneral`) REFERENCES `ProgramaGeneral` (`idProgramaGeneral`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla ProgramaGeneral
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ProgramaGeneral` (
  `idProgramaGeneral` int unsigned NOT NULL AUTO_INCREMENT,
  `CodigoProGeneral` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date DEFAULT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`idProgramaGeneral`),
  UNIQUE KEY `uq_codigoprogramageneral` (`CodigoProGeneral`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla ProgramaPresentacionParam
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ProgramaPresentacionParam` (
  `idProgramaPresentacionParam` int unsigned NOT NULL AUTO_INCREMENT,
  `idPrograma` int unsigned NOT NULL,
  `idPresentacion` int unsigned NOT NULL,
  `pallets` decimal(10,2) NOT NULL DEFAULT '0.00',
  `conformacion` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idProgramaPresentacionParam`),
  UNIQUE KEY `uk_programa_presentacion_param` (`idPrograma`,`idPresentacion`),
  KEY `fk_ppp_presentacion` (`idPresentacion`),
  CONSTRAINT `fk_ppp_presentacion` FOREIGN KEY (`idPresentacion`) REFERENCES `Presentacion` (`idPresentacion`),
  CONSTRAINT `fk_ppp_programa` FOREIGN KEY (`idPrograma`) REFERENCES `Programa` (`idPrograma`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Proveedor
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Proveedor` (
  `idProveedor` int unsigned NOT NULL AUTO_INCREMENT,
  `nroFila` int unsigned NOT NULL,
  `idFamilia` int unsigned DEFAULT NULL,
  `idMaterialPL` int unsigned DEFAULT NULL,
  `nomProveedor` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nomMaterial` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idProveedor`),
  UNIQUE KEY `uk_proveedor_fila_nombre` (`nroFila`,`nomProveedor`),
  KEY `idx_proveedor_fila` (`nroFila`),
  KEY `idx_proveedor_familia` (`idFamilia`),
  KEY `idx_proveedor_material` (`idMaterialPL`),
  KEY `idx_proveedor_nombre` (`nomProveedor`),
  KEY `idx_proveedor_codigo` (`codigo`),
  CONSTRAINT `fk_proveedor_familia` FOREIGN KEY (`idFamilia`) REFERENCES `Familia` (`idFamilia`),
  CONSTRAINT `fk_proveedor_material` FOREIGN KEY (`idMaterialPL`) REFERENCES `MaterialProgramaPL` (`idMaterialPL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla ProveedorCatalogo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ProveedorCatalogo` (
  `idProveedorCatalogo` int unsigned NOT NULL AUTO_INCREMENT,
  `nomProveedor` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idProveedorCatalogo`),
  UNIQUE KEY `uk_proveedor_catalogo_nombre` (`nomProveedor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Rol
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rol` (
  `idRol` int unsigned NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idRol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla semConsumo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `semConsumo` (
  `idSemConsumo` int unsigned NOT NULL AUTO_INCREMENT,
  `etiqueta` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigoSemana` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fechaInicio` date NOT NULL,
  `nroOrden` tinyint unsigned NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`idSemConsumo`),
  UNIQUE KEY `uq_semconsumo_etiqueta` (`etiqueta`),
  UNIQUE KEY `uq_semconsumo_orden` (`nroOrden`),
  KEY `idx_semconsumo_fecha` (`fechaInicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla semProduccion
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `semProduccion` (
  `idSemProduccion` int unsigned NOT NULL AUTO_INCREMENT,
  `etiqueta` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigoSemana` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fechaInicio` date NOT NULL,
  `nroOrden` tinyint unsigned NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`idSemProduccion`),
  UNIQUE KEY `uq_semproduccion_etiqueta` (`etiqueta`),
  UNIQUE KEY `uq_semproduccion_orden` (`nroOrden`),
  KEY `idx_semproduccion_fecha` (`fechaInicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Situado
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Situado` (
  `idSituado` int unsigned NOT NULL AUTO_INCREMENT,
  `nomSituado` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idSituado`),
  UNIQUE KEY `uk_Situado_nombre` (`nomSituado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla TablaComercialDia
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `TablaComercialDia` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `idProgramaGeneral` int unsigned NOT NULL,
  `idSemProduccion` int unsigned NOT NULL,
  `dia` tinyint unsigned NOT NULL COMMENT '1=Lunes … 7=Domingo',
  `Estado` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pg_sem` (`idProgramaGeneral`,`idSemProduccion`),
  KEY `fk_tcd_sem` (`idSemProduccion`),
  CONSTRAINT `fk_tcd_pg` FOREIGN KEY (`idProgramaGeneral`) REFERENCES `ProgramaGeneral` (`idProgramaGeneral`) ON DELETE CASCADE,
  CONSTRAINT `fk_tcd_sem` FOREIGN KEY (`idSemProduccion`) REFERENCES `semProduccion` (`idSemProduccion`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla TablaComercialEstado
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `TablaComercialEstado` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `idProgramaGeneral` int unsigned NOT NULL,
  `idSemProduccion` int unsigned NOT NULL,
  `estado` enum('proceso','realizado','despachado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proceso',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pg_sem` (`idProgramaGeneral`,`idSemProduccion`),
  KEY `fk_tce_sem` (`idSemProduccion`),
  CONSTRAINT `fk_tce_pg` FOREIGN KEY (`idProgramaGeneral`) REFERENCES `ProgramaGeneral` (`idProgramaGeneral`) ON DELETE CASCADE,
  CONSTRAINT `fk_tce_sem` FOREIGN KEY (`idSemProduccion`) REFERENCES `semProduccion` (`idSemProduccion`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla TipoConsumo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `TipoConsumo` (
  `idTipoConsumo` int unsigned NOT NULL AUTO_INCREMENT,
  `nomTipoConsumo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idTipoConsumo`),
  UNIQUE KEY `uk_TipoConsumo_nombre` (`nomTipoConsumo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Traslado
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Traslado` (
  `idTraslado` int unsigned NOT NULL AUTO_INCREMENT,
  `idMaterialPL` int unsigned NOT NULL,
  `idAlmacenOrigen` int unsigned NOT NULL,
  `idAlmacenDestino` int unsigned NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `fecha` date NOT NULL,
  `observacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facturaGuia` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Factura o guía',
  `factura` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de factura',
  `guia` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de guía',
  `archivoOneDriveId` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID archivo OneDrive',
  `archivoOneDriveUrl` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL web OneDrive',
  `archivoNombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre archivo OneDrive',
  `idUsuario` int unsigned DEFAULT NULL COMMENT 'Usuario que registró',
  `fechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=anulado',
  PRIMARY KEY (`idTraslado`),
  KEY `idx_traslado_material` (`idMaterialPL`),
  KEY `idx_traslado_origen` (`idAlmacenOrigen`),
  KEY `idx_traslado_destino` (`idAlmacenDestino`),
  KEY `idx_traslado_fecha` (`fecha`),
  KEY `idx_traslado_usuario` (`idUsuario`),
  KEY `idx_traslado_estado_fecha` (`estado`,`fecha`,`idTraslado`),
  CONSTRAINT `fk_traslado_almacen_destino` FOREIGN KEY (`idAlmacenDestino`) REFERENCES `Almacen` (`idAlmacen`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_traslado_almacen_origen` FOREIGN KEY (`idAlmacenOrigen`) REFERENCES `Almacen` (`idAlmacen`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_traslado_material` FOREIGN KEY (`idMaterialPL`) REFERENCES `MaterialProgramaPL` (`idMaterialPL`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_traslado_usuario` FOREIGN KEY (`idUsuario`) REFERENCES `Usuario` (`idUsuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_traslado_cantidad_positiva` CHECK ((`cantidad` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Uso
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Uso` (
  `idUso` int unsigned NOT NULL AUTO_INCREMENT,
  `nomUso` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idUso`),
  UNIQUE KEY `uk_Uso_nombre` (`nomUso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Usuario
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Usuario` (
  `idUsuario` int unsigned NOT NULL AUTO_INCREMENT,
  `idPersona` int unsigned NOT NULL,
  `idRol` int unsigned NOT NULL,
  `Usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idUsuario`),
  UNIQUE KEY `uk_usuario_login` (`Usuario`),
  UNIQUE KEY `uk_usuario_persona` (`idPersona`),
  KEY `idx_usuario_rol` (`idRol`),
  CONSTRAINT `fk_usuario_persona` FOREIGN KEY (`idPersona`) REFERENCES `Persona` (`idPersona`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`idRol`) REFERENCES `Rol` (`idRol`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla UsuarioPermiso
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `UsuarioPermiso` (
  `idUsuarioPermiso` int unsigned NOT NULL AUTO_INCREMENT,
  `idUsuario` int unsigned NOT NULL,
  `idModulo` int unsigned NOT NULL,
  `puede_ver` tinyint(1) NOT NULL DEFAULT '0',
  `puede_crear` tinyint(1) NOT NULL DEFAULT '0',
  `puede_editar` tinyint(1) NOT NULL DEFAULT '0',
  `puede_eliminar` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`idUsuarioPermiso`),
  UNIQUE KEY `uk_usuario_modulo` (`idUsuario`,`idModulo`),
  KEY `idx_usuario_permiso_usuario` (`idUsuario`),
  KEY `idx_usuario_permiso_modulo` (`idModulo`),
  CONSTRAINT `fk_usuario_permiso_modulo` FOREIGN KEY (`idModulo`) REFERENCES `Modulo` (`idModulo`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_permiso_usuario` FOREIGN KEY (`idUsuario`) REFERENCES `Usuario` (`idUsuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla Variedad
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Variedad` (
  `idVariedad` int unsigned NOT NULL AUTO_INCREMENT,
  `nomVariedad` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=activo, 0=inactivo',
  PRIMARY KEY (`idVariedad`),
  UNIQUE KEY `uk_Variedad_nombre` (`nomVariedad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Vistas de stock (SQL SECURITY INVOKER, sin DEFINER: apto para hosting compartido)
-- ------------------------------------------------------------
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_StockAlmacen` AS select `ms`.`idMaterialPL` AS `idMaterialPL`,`ms`.`idAlmacen` AS `idAlmacen`,sum((case when (`ms`.`tipo` = 'ingreso') then `ms`.`cantidad` when (`ms`.`tipo` = 'egreso') then -(`ms`.`cantidad`) else 0 end)) AS `stock` from `MovimientoStock` `ms` where (`ms`.`estado` = 1) group by `ms`.`idMaterialPL`,`ms`.`idAlmacen`;

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_StockMaterial` AS select `ms`.`idMaterialPL` AS `idMaterialPL`,sum((case when (`ms`.`tipo` = 'ingreso') then `ms`.`cantidad` when (`ms`.`tipo` = 'egreso') then -(`ms`.`cantidad`) else 0 end)) AS `stockTotal` from `MovimientoStock` `ms` where (`ms`.`estado` = 1) group by `ms`.`idMaterialPL`;

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_StockAlmacenDetalle` AS select `mpl`.`idMaterialPL` AS `idMaterialPL`,`mpl`.`CodigoPL` AS `CodigoPL`,`mpl`.`nomMaterial` AS `nomMaterial`,`a`.`idAlmacen` AS `idAlmacen`,`a`.`codigo` AS `codigoAlmacen`,`a`.`nomAlmacen` AS `nomAlmacen`,coalesce(`s`.`stock`,0) AS `stock`,coalesce(`cfg`.`stockMinimo`,0) AS `stockMinimo` from (((`MaterialProgramaPL` `mpl` join `Almacen` `a`) left join `vw_StockAlmacen` `s` on(((`s`.`idMaterialPL` = `mpl`.`idMaterialPL`) and (`s`.`idAlmacen` = `a`.`idAlmacen`)))) left join `MaterialStockConfig` `cfg` on(((`cfg`.`idMaterialPL` = `mpl`.`idMaterialPL`) and (`cfg`.`estado` = 1)))) where ((`mpl`.`Estado` = 1) and (`a`.`Estado` = 1));

SET FOREIGN_KEY_CHECKS=1;
