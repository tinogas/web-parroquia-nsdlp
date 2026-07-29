# Datos personales y privacidad

## Por qué este documento existe

Un sitio parroquial parece inofensivo hasta que se mira lo que realmente maneja: nombres,
fechas de nacimiento, teléfonos y correos de niños inscritos a catequesis o a un curso, y
de sus padres. Son datos personales de **menores de edad**, la categoría que la Ley
Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP) trata
con más cuidado.

Las reglas de este documento **no son recomendaciones**. Son requisitos del sistema, y
están implementadas en el código. Cualquiera que agregue una vista pública que toque
inscripciones, mensajes o fotografías debe leer esto antes.

## Aviso de privacidad

Vive en la tabla `paginas` con el slug `aviso-de-privacidad`, y está enlazado desde el
footer de todas las páginas y desde cada formulario.

Debe ser un **aviso integral**, es decir, contener:

- Identidad y domicilio del responsable, que es la parroquia.
- Finalidades del tratamiento, con las **primarias separadas de las secundarias**.
- Qué datos se recaban.
- Transferencias a terceros, si las hubiera. Idealmente ninguna.
- Los medios para ejercer los **derechos ARCO** —acceso, rectificación, cancelación y
  oposición—: un correo electrónico y el domicilio de la oficina parroquial.
- Mención expresa del tratamiento de datos de menores y del consentimiento del padre,
  madre o tutor.
- El procedimiento para revocar el consentimiento.

## Consentimiento

### Versionado

El aviso de privacidad cambiará con el tiempo. Sin registrar qué versión aceptó cada
persona, no hay forma de demostrar a qué dio su consentimiento.

Por eso existe la constante `AVISO_VERSION` en `config/app.php`, espejada en la clave
`aviso_privacidad_version` de la tabla `configuracion`. Cada registro de
`inscripciones_curso` y `mensajes_contacto` guarda tres columnas:

| Columna | Qué guarda |
|---|---|
| `consentimiento` | Que la persona marcó la casilla |
| `consentimiento_ip` | Desde dónde |
| `aviso_version` | Qué texto exacto aceptó |

Al publicar una versión nueva del aviso se incrementa la constante. Los consentimientos
anteriores quedan asociados a su versión.

### La casilla

Está en el parcial reutilizable `shared/views/parciales/consentimiento_privacidad.php`:

- **Nunca premarcada.** Un consentimiento premarcado no es consentimiento.
- Texto: *"He leído y acepto el Aviso de Privacidad"*, con enlace que abre en una pestaña
  nueva para no perder lo que la persona ya escribió.
- **Validada en el servidor.** Si no viene marcada, no se inserta el registro. La
  validación en JavaScript es cortesía para el usuario, no un control.

## Menores de edad

Si la fecha de nacimiento capturada implica menos de 18 años:

1. El servidor pone `es_menor = 1`. **Se calcula en el servidor**, nunca se confía en lo
   que mande el formulario.
2. Los campos `tutor_nombre`, `tutor_parentesco` y `tutor_telefono` pasan a ser
   obligatorios, validados en el servidor y no solo en el navegador.
3. El texto del consentimiento cambia a *"Como padre, madre o tutor, autorizo el
   tratamiento de los datos personales del menor…"*.

## Minimización

Solo se pide lo que hace falta para el trámite.

**No se pide** CURP, RFC, número de seguridad social, ni datos sensibles en el sentido de
la ley —origen étnico, creencias religiosas, preferencias sexuales, opiniones políticas—,
con una única excepción documentada abajo: el estado de salud en el registro de MESC.

## Dato sensible: MESC (issue #3)

El módulo de Ministros Extraordinarios de la Comunión (`mesc_visitas`) registra visitas a
enfermos para llevarles la comunión. El solo hecho de que alguien aparezca ahí revela que
está enfermo, que la LFPDPPP clasifica como **dato personal sensible** (Art. 3, fr. VI),
con un estándar de protección más alto que el de cualquier otro dato que maneja este
sitio, empezando por el consentimiento: debe ser **expreso**, no basta uno tácito o
implícito como el de una casilla web.

Por diseño, este módulo **no tiene ningún formulario público**: nadie llena su propio
registro de MESC en el sitio. El alta la hace el equipo pastoral desde el panel, después
de que la familia solicita la visita **en persona o por teléfono**, momento en el que se
obtiene ese consentimiento expreso fuera del sistema — igual que ya ocurre hoy en la
práctica pastoral real, sin necesidad de digitalizarlo. Por eso `mesc_visitas` no tiene
columnas de `consentimiento`/`consentimiento_ip`/`aviso_version` como sí las tiene
`inscripciones_curso`: ese patrón asume un formulario web que aquí no existe.

Protecciones en el código:

- **Nunca se expone públicamente.** `MescModel` no tiene un solo método de lectura sin
  autenticación; a diferencia de avisos, eventos o galería, no hay `MescPublicoController`.
- **Alcance más estrecho que el resto del contenido pastoral.** Se rige por
  `requireAlcancePastoral()`, igual que avisos o eventos, pero el permiso `mesc.*` **no**
  se concede a secretaría (que sí ve inscripciones y mensajes): es una actividad de la
  propia pastoral de MESC, no un trámite administrativo general, y cuantas menos cuentas
  puedan verlo, mejor.
- **El aviso de privacidad** (`paginas.aviso-de-privacidad`) menciona esta finalidad y su
  base de consentimiento por separado del resto. Ver la plantilla sembrada en
  `install.sql`.

## Qué nunca se publica

Esta es la lista corta, y se aplica en el código, no solo en el criterio de quien
administre:

**Nombres, fotografías ni datos de menores en el sitio público.** La tabla
`galeria_imagenes` tiene la columna `autorizacion_imagen`, que arranca en 0, y la consulta
pública filtra `WHERE publicada = 1 AND autorizacion_imagen = 1`. Una fotografía sin
autorización registrada no puede llegar al sitio ni por descuido.

**Listas de inscritos o folios.** Viven exclusivamente en el panel autenticado. No hay ni
habrá una vista pública de "quiénes se inscribieron".

**Domicilios y teléfonos particulares** de feligreses o del equipo pastoral. En la tabla
`personas` se publica únicamente el correo institucional.

## Control de acceso

| Rol | Ve datos personales |
|---|---|
| Administrador | Sí |
| Secretaría | Sí |
| Editor | No |
| Coordinador | **No**, ni siquiera de su propia pastoral |

El rol de secretaría existe precisamente para esto: separar a quien administra trámites de
quien edita la web. Un coordinador de pastoral juvenil publica avisos y sube fotos de sus
actividades, pero no tiene por qué ver las actas de nacimiento de los niños de catequesis.

Esta tabla no aplica a MESC: ahí la regla es distinta (ver la sección "Dato sensible:
MESC" arriba). Un coordinador **sí** ve las visitas, pero únicamente de la pastoral de
MESC que tenga asignada; secretaría **no** las ve, aunque sí vea el resto de datos
personales del sitio.

## Trazabilidad

La función `auditoria()` se llama **también en la lectura** de datos personales, no solo
al escribirlos:

- Al abrir el listado o el detalle de una inscripción a un curso: `accion = 'consultar'`.
- En **cada exportación a CSV**, que es el punto por donde los datos salen del sistema.

Es lo que permite responder con hechos si alguien pregunta quién consultó su información.

**Impersonación ("Usar como…").** El administrador puede operar el panel con la sesión de
otra cuenta, incluida secretaría. Sin más, eso podría volver ambigua la pregunta "¿quién
consultó este dato?": la fila de auditoría mostraría a secretaría, aunque quien realmente
haya abierto el registro fuera el administrador actuando como ella. La columna
`admin_real_id` de `auditoria` resuelve esto: queda el administrador real detrás de
cualquier consulta o exportación hecha durante una impersonación. Ver
[`ARQUITECTURA.md`](ARQUITECTURA.md), sección "Impersonación".

## Retención

**Pendiente, no implementado.** La fase 1 original tenía un mecanismo de retención
—`configuracion.retencion_meses_solicitudes` y la acción `solicitudes.purgar`, que
anonimizaba (nunca borraba) las solicitudes de sacramento ya cerradas— pero se eliminó por
completo junto con el formulario de solicitud en línea (issue #3, ver
[`ARQUITECTURA.md`](ARQUITECTURA.md)).

Hoy **no existe ningún mecanismo automático de retención o anonimización** para los datos
que el sitio sigue recolectando (`inscripciones_curso`, `mensajes_contacto`). Si la
parroquia decide que hace falta uno, hay que construirlo de nuevo — el patrón ya probado
(anonimizar campos personales, conservar folio/estado/fechas para estadística, dejar
constancia en la auditoría) sigue siendo válido como referencia, pero el código que lo
implementaba ya no existe.

## Transporte

En producción, HTTPS obligatorio: redirección forzada y cabecera HSTS en `.htaccess`. La
clase `Session` ya marca la cookie como `secure` cuando detecta HTTPS.

Un formulario con datos de menores viajando en texto plano no es aceptable, y hoy no hay
excusa: los certificados son gratuitos y cPanel los emite en dos clics.

## Checklist antes de publicar el sitio

- [ ] El aviso de privacidad está redactado, revisado y publicado.
- [ ] `AVISO_VERSION` y la clave en `configuracion` coinciden.
- [ ] Todos los formularios públicos muestran la casilla de consentimiento sin premarcar.
- [ ] Un envío sin consentimiento se rechaza del lado del servidor.
- [ ] Una fecha de nacimiento de menor exige los datos del tutor.
- [ ] La galería pública no muestra imágenes sin `autorizacion_imagen`.
- [ ] Ninguna URL pública devuelve datos de inscripciones.
- [ ] Un usuario con rol coordinador no puede abrir el módulo de inscripciones.
- [ ] La auditoría registra la consulta y la exportación de datos personales.
- [ ] Ningún rol distinto de administrador o coordinador de la pastoral de MESC puede ver `mesc_visitas`.
- [ ] HTTPS está activo y forzado.
