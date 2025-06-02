# 📤 Instrucciones para Subir a GitHub

## 1️⃣ Crear Repositorio en GitHub

1. Ve a [GitHub.com](https://github.com)
2. Click en el botón verde **"New"** o **"Nuevo repositorio"**
3. Configura el repositorio:
   ```
   Repository name: crecepyme-mvp
   Description: Sistema ERP completo para PyMEs chilenas con facturación electrónica SII
   Visibility: Private (o Public según prefieras)
   ❌ NO marques "Initialize repository with README"
   ❌ NO agregues .gitignore
   ❌ NO agregues licencia
   ```
4. Click en **"Create repository"**

## 2️⃣ Conectar y Subir el Código

GitHub te mostrará instrucciones. Usa estos comandos en tu terminal:

```bash
# Navegar al proyecto
cd /Users/juanpablobasualdo/Desktop/MIDD/midd

# Agregar el remote (reemplaza TU_USUARIO con tu usuario de GitHub)
git remote add origin https://github.com/TU_USUARIO/midd-mvp.git

# O si usas SSH:
git remote add origin git@github.com:TU_USUARIO/midd-mvp.git

# Verificar el remote
git remote -v

# Subir el código
git push -u origin main
```

## 3️⃣ Si el Branch es 'master' en lugar de 'main'

```bash
# Renombrar branch local
git branch -M main

# Luego hacer push
git push -u origin main
```

## 4️⃣ Si te pide autenticación

### Opción A: Token de Acceso Personal (Recomendado)
1. Ve a GitHub → Settings → Developer settings → Personal access tokens
2. Click "Generate new token (classic)"
3. Dale un nombre descriptivo
4. Selecciona permisos: `repo` (todos)
5. Genera el token y cópialo
6. Úsalo como contraseña cuando Git te lo pida

### Opción B: GitHub CLI
```bash
# Instalar GitHub CLI
brew install gh

# Autenticarse
gh auth login

# Seguir las instrucciones
```

## 5️⃣ Comandos Útiles Post-Subida

```bash
# Ver estado
git status

# Ver historial
git log --oneline

# Ver remotes
git remote -v

# Crear un tag para la versión
git tag -a v1.0.0-mvp -m "MVP Release v1.0.0"
git push origin v1.0.0-mvp
```

## 6️⃣ Configurar el Repositorio en GitHub

Después de subir, en GitHub:

1. **Agregar descripción detallada**
2. **Configurar Topics:** `laravel`, `vue`, `erp`, `chile`, `sii`, `facturacion-electronica`
3. **Activar Issues** si quieres trackear bugs/features
4. **Configurar Branch protection** para `main`
5. **Agregar colaboradores** si trabajas en equipo

## 7️⃣ Archivo README en GitHub

El repositorio ya incluye `README_MVP.md`. Para que se muestre en GitHub:

```bash
# Renombrar para que GitHub lo reconozca
mv README_MVP.md README.md
git add README.md
git rm README_MVP.md
git commit -m "📝 Renombrar README para GitHub"
git push
```

## 📌 Notas Importantes

- El archivo `.env` NO se sube (está en .gitignore)
- Los certificados NO se suben
- La base de datos SQLite NO se sube
- Los logs NO se suben
- node_modules NO se sube

## 🚨 Si hay problemas

```bash
# Si el push falla por historia divergente
git pull origin main --rebase
git push origin main

# Si hay conflictos
git status
# Resolver conflictos manualmente
git add .
git commit -m "Resolver conflictos"
git push
```

## ✅ Verificación Final

Después de subir, verifica en GitHub:
- ✅ Todos los archivos están presentes
- ✅ El README se muestra correctamente
- ✅ No hay archivos sensibles (.env, certificados)
- ✅ El .gitignore está funcionando

---

¡Listo! Tu MVP está ahora en GitHub y listo para colaboración.

**Siguiente paso:** Compartir el link del repositorio para continuar el desarrollo.