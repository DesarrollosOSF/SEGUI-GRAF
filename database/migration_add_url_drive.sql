-- Migración: Agregar campo url_drive a la tabla solicitudes
-- Para almacenar URLs de Google Drive u otros servicios cuando el archivo es muy grande

ALTER TABLE solicitudes 
ADD COLUMN url_drive VARCHAR(500) NULL AFTER observaciones;

