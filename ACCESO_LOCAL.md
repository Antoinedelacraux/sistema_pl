# Acceso de desarrollo local

La aplicación local se sirve en `http://127.0.0.1:8000` y requiere que MySQL
esté iniciado en el puerto `3306`.

## Cuenta administradora

La base local puede contener una cuenta administradora. Las contraseñas no se
incluyen en el repositorio ni en este documento: son secretos y cada entorno
debe usar una diferente.

Para una base de datos nueva, crea el administrador inicial desde PowerShell:

```powershell
$env:SEED_ADMIN_USUARIO = "admin@local.test"
$env:SEED_ADMIN_PASSWORD = "elige-una-contrasena-segura-local"
php artisan db:seed
```

Después ingresa con el usuario y contraseña que definiste. El seeder asigna el
rol **Administrador** y todos los permisos de módulo. No reutilices una
contraseña local en entornos públicos o de producción.
